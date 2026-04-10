<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Models\Evaluation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job 2/4 in the AI pipeline — body procedure variant.
 *
 * Extracts body landmarks from the FRONT and SIDE photos for body procedures:
 * BBL, Lipo 360, and Breast Augmentation.
 *
 * Simulation mode (FEATURE_AI_VISION=false):
 *   Generates realistic body landmark coordinates with subtle random variance.
 *
 * Landmark coordinate system:
 *   Normalised (0.0–1.0) relative to image width/height, origin top-left.
 *   Front photo: width measurements (waist, hip, shoulder, thigh).
 *   Side photo:  projection measurements (gluteal, abdominal, posture).
 *
 * Stores in evaluation.analysis_data['body_landmarks']:
 *   front_landmarks   — 11 points from front photo
 *   side_landmarks    — 5 points from side photo
 *   _body_attributes  — composition estimates (skin laxity, donor quality)
 */
class ExtractBodyLandmarksJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(private readonly string $evaluationId) {}

    public function handle(): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        $evaluation = Evaluation::withoutGlobalScopes()->findOrFail($this->evaluationId);

        $data = config('features.ai_vision', false)
            ? $this->runRealExtraction($evaluation)
            : $this->simulateLandmarks($evaluation);

        $evaluation->update([
            'analysis_data' => array_merge(
                $evaluation->analysis_data ?? [],
                [
                    'body_landmarks'              => $data,
                    'body_landmarks_extracted_at' => now()->toIso8601String(),
                ],
            ),
        ]);
    }

    // ─── Real extraction ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function runRealExtraction(Evaluation $evaluation): array
    {
        // Body pose via Rekognition not yet implemented — falls back to simulation.
        Log::info('ExtractBodyLandmarksJob: real body pose not yet implemented, using simulation', [
            'evaluation_id' => $this->evaluationId,
        ]);

        return $this->simulateLandmarks($evaluation);
    }

    // ─── Simulation ───────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function simulateLandmarks(Evaluation $evaluation): array
    {
        return [
            'front_landmarks'  => $this->simulateFrontLandmarks($evaluation->procedure_slug),
            'side_landmarks'   => $this->simulateSideLandmarks($evaluation->procedure_slug),
            '_body_attributes' => $this->simulateBodyAttributes($evaluation),
        ];
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function simulateFrontLandmarks(string $procedure): array
    {
        $j = fn (float $base, float $range = 0.01): float => round($base + (mt_rand(-1000, 1000) / 1000) * $range, 4);

        $isBBL  = $procedure === 'bbl';
        $isLipo = $procedure === 'lipo_360';

        $shoulderSpread = $isBBL ? 0.31 : 0.30;
        $waistIn        = $isBBL ? 0.41 : ($isLipo ? 0.39 : 0.40);
        $hipIn          = $isBBL ? 0.27 : 0.29;

        return [
            'leftShoulder'  => ['x' => $j($shoulderSpread),     'y' => $j(0.14, 0.015)],
            'rightShoulder' => ['x' => $j(1 - $shoulderSpread), 'y' => $j(0.14, 0.015)],
            'upperAbdomen'  => ['x' => $j(0.50, 0.005),         'y' => $j(0.36, 0.015)],
            'navel'         => ['x' => $j(0.50, 0.005),         'y' => $j(0.48, 0.015)],
            'lowerAbdomen'  => ['x' => $j(0.50, 0.005),         'y' => $j(0.56, 0.015)],
            'leftWaist'     => ['x' => $j($waistIn),            'y' => $j(0.43, 0.015)],
            'rightWaist'    => ['x' => $j(1 - $waistIn),        'y' => $j(0.43, 0.015)],
            'leftHip'       => ['x' => $j($hipIn),              'y' => $j(0.58, 0.015)],
            'rightHip'      => ['x' => $j(1 - $hipIn),          'y' => $j(0.58, 0.015)],
            'leftThigh'     => ['x' => $j(0.34),                'y' => $j(0.70, 0.015)],
            'rightThigh'    => ['x' => $j(0.66),                'y' => $j(0.70, 0.015)],
        ];
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function simulateSideLandmarks(string $procedure): array
    {
        $j = fn (float $base, float $range = 0.015): float => round($base + (mt_rand(-1000, 1000) / 1000) * $range, 4);

        $gluteX   = $procedure === 'bbl' ? $j(0.62, 0.02) : $j(0.58, 0.02);
        $abdomenX = $procedure === 'lipo_360' ? $j(0.60, 0.02) : $j(0.56, 0.02);

        return [
            'shoulder'        => ['x' => $j(0.52, 0.01), 'y' => $j(0.14, 0.01)],
            'upperAbdomenSide' => ['x' => $abdomenX,      'y' => $j(0.36, 0.01)],
            'glutealPeak'     => ['x' => $gluteX,         'y' => $j(0.55, 0.015)],
            'glutealBase'     => ['x' => $j(0.58, 0.015), 'y' => $j(0.63, 0.015)],
            'lowerBackCurve'  => ['x' => $j(0.45, 0.01),  'y' => $j(0.48, 0.01)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateBodyAttributes(Evaluation $evaluation): array
    {
        $answers = $evaluation->quiz_answers ?? [];

        $skinLaxity = match ($answers['q_skin_laxity'] ?? null) {
            'excellent' => ['label' => 'excellent', 'score' => mt_rand(85, 95)],
            'mild'      => ['label' => 'mild',      'score' => mt_rand(65, 80)],
            'moderate'  => ['label' => 'moderate',  'score' => mt_rand(40, 60)],
            default     => ['label' => 'unknown',   'score' => mt_rand(55, 75)],
        };

        $donorAreas = (array) ($answers['q_donor_areas'] ?? []);
        $donorCount = count(array_filter($donorAreas, fn ($a) => $a !== 'not_sure'));
        $donorScore = min(100, $donorCount * 25 + mt_rand(10, 30));

        return [
            'skin_laxity'      => $skinLaxity,
            'weight_stable'    => (bool) ($answers['q_weight_stable'] ?? true),
            'donor_quality'    => ['score' => $donorScore, 'areas' => $donorAreas],
            'photo_quality_avg' => mt_rand(68, 84),
        ];
    }
}
