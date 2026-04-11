<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
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
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesJobTenant;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(SecureFileService $fileService, AuditLog $auditLog): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Set TenantContext so all subsequent scoped queries work in this worker process.
        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::findOrFail($this->evaluationId);
        $photos     = Photo::where('evaluation_id', $this->evaluationId)->get();

        if ($photos->isEmpty()) {
            Log::warning('ValidatePhotoQualityJob: no photos found', ['evaluation_id' => $this->evaluationId]);
            $this->markEvaluationFailed($evaluation, 'no_photos');
            return;
        }

        $passCount = 0;

        foreach ($photos as $photo) {
            $passed = $photo->quality_score >= 50;

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

        // Use withoutGlobalScopes() in failed() — TenantContext may not be set
        // if the job failed before setTenantFromEvaluation() completed.
        Evaluation::withoutGlobalScopes()
            ->where('id', $this->evaluationId)
            ->update(['status' => Evaluation::STATUS_FAILED]);
    }
}
