<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\UploadPhotoRequest;
use App\Models\Evaluation;
use App\Models\Photo;
use App\Services\AuditLog;
use App\Services\SecureFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Handles patient photo uploads during intake.
 * Photos are stored in S3 (KMS encrypted). Metadata only in the DB.
 *
 * HIPAA: Photo upload is a PHI event — every upload is audit-logged.
 * The s3_key is encrypted by the Photo model's cast before being stored.
 */
class PhotoController extends Controller
{
    public function __construct(
        private readonly SecureFileService $files,
        private readonly AuditLog $auditLog,
    ) {}

    public function store(UploadPhotoRequest $request, string $token): JsonResponse
    {
        $evaluation = Evaluation::where('secure_token', $token)->firstOrFail();
        $validated  = $request->validated();
        $photoType  = $validated['type'];

        // Synchronous AI quality check before wasting S3 space / uploading
        $qualityScore = config('features.ai_vision', false)
            ? $this->runRekognition($request->file('photo'))
            : $this->simulateQualityScore($photoType);

        if ($qualityScore < 50) {
            throw ValidationException::withMessages([
                'photo' => 'This photo is too blurry or no clear face was detected. Please ensure good lighting, look straight ahead, and try again.',
            ]);
        }

        // Upload to S3 (or local disk in dev) — returns plaintext key
        $s3Key = $this->files->upload(
            file: $request->file('photo'),
            patientId: (string) $evaluation->patient_id,
            evaluationId: (string) $evaluation->id,
            type: $validated['type'],
        );

        // Create photo record — s3_key is encrypted by model cast
        $photo = Photo::create([
            'tenant_id'        => $evaluation->tenant_id,
            'evaluation_id'    => $evaluation->id,
            'type'             => $validated['type'],
            's3_key'           => $s3Key,
            's3_key_hash'      => $this->files->hashKey($s3Key),
            'quality_score'    => $qualityScore,
            'analysis_status'  => Photo::ANALYSIS_COMPLETE,
            'capture_metadata' => $validated['capture_metadata'] ?? null,
            'taken_at'         => now(),
        ]);

        // Advance funnel step to PHOTOS on the first upload — never downgrade.
        if ($evaluation->funnel_step < Evaluation::FUNNEL_PHOTOS) {
            $evaluation->update(['funnel_step' => Evaluation::FUNNEL_PHOTOS]);
        }

        $this->auditLog->record('evaluation.photo.uploaded', $photo, [
            'type'          => $photo->type,
            'quality_score' => $photo->quality_score,
        ]);

        // Response shape must match UploadPhotoResponse in resources/js/types/intake.ts
        return response()->json([
            'id'              => $photo->id,
            'type'            => $photo->type,
            'quality_score'   => $photo->quality_score,
            'signed_url'      => $this->files->getSignedUrl($s3Key),
            'analysis_status' => $photo->analysis_status,
        ], 201);
    }

    private function runRekognition(UploadedFile $file): int
    {
        try {
            $client = new \Aws\Rekognition\RekognitionClient([
                'version' => 'latest',
                'region'  => config('services.rekognition.region', 'us-east-1'),
            ]);

            $result = $client->detectFaces([
                'Image' => [
                    'Bytes' => file_get_contents($file->getRealPath()),
                ],
                'Attributes' => ['ALL'],
            ]);

            $faces = $result->get('FaceDetails');

            if (empty($faces)) {
                return 0; // No face detected
            }

            $face      = $faces[0]; // Primary face
            $quality   = $face['Quality'] ?? [];
            $sharpness = (float) ($quality['Sharpness'] ?? 0);
            $brightness = (float) ($quality['Brightness'] ?? 0);

            // Weighted average: sharpness is more important for surgical analysis
            $score = (int) round(($sharpness * 0.7) + ($brightness * 0.3));

            return max(0, min(100, $score));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Rekognition DetectFaces sync failed', [
                'error' => $e->getMessage(),
            ]);
            // Revert back to allowing it if AWS is down
            return 100;
        }
    }

    private function simulateQualityScore(string $photoType): int
    {
        $base = match ($photoType) {
            Photo::TYPE_FRONT         => 78,
            Photo::TYPE_LEFT_PROFILE  => 72,
            Photo::TYPE_RIGHT_PROFILE => 74,
            default                   => 65,
        };

        // Add ±8 random variance to make it realistic
        return max(0, min(100, $base + random_int(-8, 8)));
    }
}
