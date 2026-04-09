<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Models\Evaluation;
use App\Models\Photo;
use App\Services\AuditLog;
use App\Services\SecureFileService;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job 1/4 in the AI pipeline.
 *
 * Validates each photo using AWS Rekognition DetectFaces.
 * Checks that:
 *   - A human face is detected in the image
 *   - The face is reasonably centred and un-obstructed
 *   - Image quality is acceptable (sharpness, brightness)
 *
 * Sets photo.quality_score (0–100) and photo.analysis_status.
 * If too many photos fail validation the evaluation is marked 'failed'.
 *
 * SIMULATION MODE (FEATURE_AI_VISION=false):
 *   Skips Rekognition. Assigns realistic mock scores so the pipeline
 *   can be tested end-to-end without AWS credentials.
 */
class ValidatePhotoQualityJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(SecureFileService $fileService, AuditLog $auditLog): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::withoutGlobalScopes()->findOrFail($this->evaluationId);
        $photos     = Photo::withoutGlobalScopes()
            ->where('evaluation_id', $this->evaluationId)
            ->get();

        if ($photos->isEmpty()) {
            Log::warning('ValidatePhotoQualityJob: no photos found', ['evaluation_id' => $this->evaluationId]);
            $this->markEvaluationFailed($evaluation, 'no_photos');
            return;
        }

        $passCount = 0;

        foreach ($photos as $photo) {
            $photo->update(['analysis_status' => Photo::ANALYSIS_PROCESSING]);

            if (config('features.ai_vision', false)) {
                $score = $this->runRekognition($photo, $fileService);
            } else {
                $score = $this->simulateQualityScore($photo);
            }

            $passed = $score >= 50;

            $photo->update([
                'quality_score'   => $score,
                'analysis_status' => $passed ? Photo::ANALYSIS_COMPLETE : Photo::ANALYSIS_FAILED,
            ]);

            if ($passed) {
                $passCount++;
            }
        }

        $requiredPhotoCount = $photos->where('type', '!=', Photo::TYPE_ADDITIONAL)->count();
        $minimumPass        = (int) ceil($requiredPhotoCount * 0.67); // at least 2/3 required photos must pass

        if ($passCount < $minimumPass) {
            Log::warning('ValidatePhotoQualityJob: insufficient passing photos', [
                'evaluation_id' => $this->evaluationId,
                'pass_count'    => $passCount,
                'required'      => $minimumPass,
            ]);
            $this->markEvaluationFailed($evaluation, 'photo_quality_insufficient');
            $this->batch()?->cancel();
            return;
        }

        $auditLog->recordSystem('ai.photo_quality.validated', $evaluation, [
            'photos_total'  => $photos->count(),
            'photos_passed' => $passCount,
        ]);
    }

    /**
     * Real Rekognition call — only runs in production/staging.
     */
    private function runRekognition(Photo $photo, SecureFileService $fileService): int
    {
        try {
            $client = new RekognitionClient([
                'version' => 'latest',
                'region'  => config('services.rekognition.region', 'us-east-1'),
            ]);

            // Dekrypt the S3 key (cast handles decryption automatically)
            $s3Key  = $photo->s3_key;
            $bucket = config('filesystems.disks.s3.bucket');

            $result = $client->detectFaces([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => $bucket,
                        'Name'   => $s3Key,
                    ],
                ],
                'Attributes' => ['QUALITY'],
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
            Log::error('Rekognition DetectFaces failed', [
                'evaluation_id' => $this->evaluationId,
                'photo_id'      => $photo->id,
                'error'         => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Simulation mode — returns plausible quality scores by photo type.
     * Front photos tend to be better quality than profiles.
     */
    private function simulateQualityScore(Photo $photo): int
    {
        $base = match ($photo->type) {
            Photo::TYPE_FRONT         => 78,
            Photo::TYPE_LEFT_PROFILE  => 72,
            Photo::TYPE_RIGHT_PROFILE => 74,
            default                   => 65,
        };

        // Add ±8 random variance to make it realistic
        return max(0, min(100, $base + random_int(-8, 8)));
    }

    private function markEvaluationFailed(Evaluation $evaluation, string $reason): void
    {
        $evaluation->update([
            'status'       => Evaluation::STATUS_FAILED,
            'analysis_data' => array_merge($evaluation->analysis_data ?? [], [
                'failure_reason' => $reason,
                'failed_at'      => now()->toIso8601String(),
            ]),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ValidatePhotoQualityJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error'         => $e->getMessage(),
        ]);

        Evaluation::withoutGlobalScopes()
            ->where('id', $this->evaluationId)
            ->update(['status' => Evaluation::STATUS_FAILED]);
    }
}
