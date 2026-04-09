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
            'quality_score'    => $validated['quality_score'],
            'analysis_status'  => Photo::ANALYSIS_PENDING,
            'capture_metadata' => $validated['capture_metadata'] ?? null,
            'taken_at'         => now(),
        ]);

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
}
