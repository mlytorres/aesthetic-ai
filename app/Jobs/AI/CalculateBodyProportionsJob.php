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

/**
 * Job 3/4 in the AI pipeline — body procedure variant.
 *
 * Computes body proportion metrics from the landmark coordinates stored
 * by ExtractBodyLandmarksJob. Pure geometry — no API calls.
 *
 * Metrics:
 *   waist_hip_ratio        — waist width / hip width (ideal female ~0.70)
 *   shoulder_waist_ratio   — shoulder width / waist width (ideal ~1.40)
 *   gluteal_projection     — side-view projection from lower back baseline
 *   abdominal_projection   — side-view anterior projection (lipo 360)
 *   body_symmetry          — left/right alignment score (0–100)
 *   overall_contour_score  — weighted composite score (0–100)
 */
class CalculateBodyProportionsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    private const IDEAL_WHR = 0.70;

    private const IDEAL_SHOULDER_WAIST = 1.40;

    private const MAX_GLUTEAL_PROJECTION = 0.18;

    public function __construct(private readonly string $evaluationId) {}

    public function handle(): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        $evaluation = Evaluation::withoutGlobalScopes()->findOrFail($this->evaluationId);

        $analysisData = $evaluation->analysis_data ?? [];
        $landmarks    = $analysisData['body_landmarks'] ?? [];

        $proportions = $this->calculate($landmarks, $evaluation);

        $evaluation->update([
            'analysis_data' => array_merge(
                $analysisData,
                [
                    'body_proportions'            => $proportions,
                    'body_proportions_calculated_at' => now()->toIso8601String(),
                ],
            ),
        ]);
    }

    // ─── Main calculation ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $landmarks
     * @return array<string, mixed>
     */
    private function calculate(array $landmarks, Evaluation $evaluation): array
    {
        $front = $landmarks['front_landmarks'] ?? [];
        $side  = $landmarks['side_landmarks'] ?? [];
        $attrs = $landmarks['_body_attributes'] ?? [];

        if (empty($front)) {
            return $this->defaultProportions();
        }

        $shoulderWidth = $this->width($front, 'leftShoulder', 'rightShoulder');
        $waistWidth    = $this->width($front, 'leftWaist', 'rightWaist');
        $hipWidth      = $this->width($front, 'leftHip', 'rightHip');
        $thighWidth    = $this->width($front, 'leftThigh', 'rightThigh');

        $whr              = $hipWidth > 0 ? round($waistWidth / $hipWidth, 3) : 0.75;
        $shoulderWaistRat = $waistWidth > 0 ? round($shoulderWidth / $waistWidth, 3) : 1.40;

        $whrScore           = $this->scoreRatio($whr, self::IDEAL_WHR, tolerance: 0.15);
        $shoulderWaistScore = $this->scoreRatio($shoulderWaistRat, self::IDEAL_SHOULDER_WAIST, tolerance: 0.25);
        $symmetryScore      = $this->bodySymmetryScore($front);

        $glutealProjection  = $this->glutealProjection($side);
        $glutealScore       = $this->scoreProjection($glutealProjection);

        $abdominalProjection = $this->abdominalProjection($side);
        $abdominalScore      = max(0, 100 - (int) ($abdominalProjection * 400));

        $skinLaxityScore = (int) ($attrs['skin_laxity']['score'] ?? 65);

        $overallScore = (int) round(
            $whrScore * 0.30
            + $shoulderWaistScore * 0.20
            + $symmetryScore * 0.20
            + $glutealScore * 0.15
            + $skinLaxityScore * 0.15
        );

        return [
            'waist_hip_ratio' => [
                'ratio' => $whr,
                'ideal' => self::IDEAL_WHR,
                'score' => $whrScore,
                'label' => $this->whrLabel($whr),
            ],
            'shoulder_waist_ratio' => [
                'ratio' => $shoulderWaistRat,
                'ideal' => self::IDEAL_SHOULDER_WAIST,
                'score' => $shoulderWaistScore,
            ],
            'measurements' => [
                'shoulder_width' => round($shoulderWidth, 3),
                'waist_width'    => round($waistWidth, 3),
                'hip_width'      => round($hipWidth, 3),
                'thigh_width'    => round($thighWidth, 3),
            ],
            'gluteal_projection' => [
                'value' => round($glutealProjection, 3),
                'score' => $glutealScore,
            ],
            'abdominal_projection' => [
                'value' => round($abdominalProjection, 3),
                'score' => $abdominalScore,
            ],
            'body_symmetry'         => $symmetryScore,
            'skin_laxity'           => $attrs['skin_laxity'] ?? ['label' => 'unknown', 'score' => 65],
            'overall_contour_score' => $overallScore,
            '_avg_photo_quality'    => (int) ($attrs['photo_quality_avg'] ?? 70),
            '_body_attributes'      => $attrs,
        ];
    }

    // ─── Geometry helpers ─────────────────────────────────────────────────────

    /**
     * @param  array<string, array<string, float>>  $landmarks
     */
    private function width(array $landmarks, string $left, string $right): float
    {
        if (! isset($landmarks[$left], $landmarks[$right])) {
            return 0.0;
        }

        return abs(($landmarks[$right]['x'] ?? 0) - ($landmarks[$left]['x'] ?? 0));
    }

    /**
     * @param  array<string, array<string, float>>  $side
     */
    private function glutealProjection(array $side): float
    {
        if (! isset($side['glutealPeak'], $side['lowerBackCurve'])) {
            return 0.10;
        }

        return abs(($side['glutealPeak']['x'] ?? 0.58) - ($side['lowerBackCurve']['x'] ?? 0.45));
    }

    /**
     * @param  array<string, array<string, float>>  $side
     */
    private function abdominalProjection(array $side): float
    {
        if (! isset($side['upperAbdomenSide'], $side['lowerBackCurve'])) {
            return 0.12;
        }

        return abs(($side['upperAbdomenSide']['x'] ?? 0.56) - ($side['lowerBackCurve']['x'] ?? 0.45));
    }

    /**
     * @param  array<string, array<string, float>>  $landmarks
     */
    private function bodySymmetryScore(array $landmarks): int
    {
        $pairs = [
            ['leftShoulder', 'rightShoulder'],
            ['leftWaist',    'rightWaist'],
            ['leftHip',      'rightHip'],
            ['leftThigh',    'rightThigh'],
        ];

        $totalDeviation = 0.0;
        $counted        = 0;

        foreach ($pairs as [$l, $r]) {
            if (isset($landmarks[$l], $landmarks[$r])) {
                $totalDeviation += abs(($landmarks[$l]['y'] ?? 0) - ($landmarks[$r]['y'] ?? 0));
                $counted++;
            }
        }

        if ($counted === 0) {
            return 75;
        }

        $avgDeviation = $totalDeviation / $counted;

        return max(0, min(100, (int) round(100 - ($avgDeviation / 0.05) * 100)));
    }

    // ─── Scoring helpers ──────────────────────────────────────────────────────

    private function scoreRatio(float $actual, float $ideal, float $tolerance): int
    {
        $deviation = abs($actual - $ideal);

        return max(0, min(100, (int) round(100 - ($deviation / $tolerance) * 100)));
    }

    private function scoreProjection(float $projection): int
    {
        return min(100, (int) round(($projection / self::MAX_GLUTEAL_PROJECTION) * 100));
    }

    private function whrLabel(float $whr): string
    {
        return match (true) {
            $whr <= 0.70 => 'Hourglass',
            $whr <= 0.75 => 'Pear',
            $whr <= 0.80 => 'Rectangular',
            default      => 'Apple',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultProportions(): array
    {
        return [
            'waist_hip_ratio'      => ['ratio' => 0.75, 'ideal' => self::IDEAL_WHR, 'score' => 50, 'label' => 'Rectangular'],
            'shoulder_waist_ratio' => ['ratio' => 1.40, 'ideal' => self::IDEAL_SHOULDER_WAIST, 'score' => 50],
            'measurements'         => ['shoulder_width' => 0.0, 'waist_width' => 0.0, 'hip_width' => 0.0, 'thigh_width' => 0.0],
            'gluteal_projection'   => ['value' => 0.10, 'score' => 50],
            'abdominal_projection' => ['value' => 0.12, 'score' => 50],
            'body_symmetry'        => 75,
            'skin_laxity'          => ['label' => 'unknown', 'score' => 65],
            'overall_contour_score' => 50,
            '_avg_photo_quality'   => 70,
            '_body_attributes'     => [],
        ];
    }
}
