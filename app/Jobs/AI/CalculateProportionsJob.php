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
 * Job 3/4 in the AI pipeline.
 *
 * Calculates facial proportion metrics from the landmark coordinates
 * stored by ExtractFacialLandmarksJob.
 *
 * Metrics computed:
 *   facial_thirds       — upper/mid/lower face height ratio (ideal ≈ 1:1:1)
 *   facial_fifths       — horizontal width of nose vs eye width (ideal ≈ 1:1:1:1:1)
 *   nasal_symmetry      — left vs right side of nose (0–100, ideal = 100)
 *   nasal_projection    — nose projection as ratio to face height (Goode's ratio, ideal ≈ 0.55–0.60)
 *   nasal_width_ratio   — alar width / inter-canthal distance (ideal ≈ 1.0)
 *   eye_symmetry        — horizontal eye alignment score (0–100, ideal = 100)
 *   overall_harmony     — weighted composite of all metrics (0–100)
 *
 * All metrics are procedure-agnostic; the GenerateRecommendationsJob
 * interprets them in the context of the procedure and quiz answers.
 *
 * No external API calls — this is pure geometry on normalised coordinates.
 */
class CalculateProportionsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesJobTenant;

    public int $tries   = 3;
    public int $timeout = 120;

    // Golden ratio and ideal proportion constants
    private const IDEAL_NASAL_PROJECTION = 0.575; // Goode's ratio midpoint
    private const IDEAL_NASAL_WIDTH_RATIO = 1.0;   // alar width ÷ inter-canthal distance
    private const GOLDEN_RATIO = 1.618;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::findOrFail($this->evaluationId);
        $analysisData = $evaluation->analysis_data ?? [];
        $landmarks    = $analysisData['landmarks'] ?? [];

        if (empty($landmarks)) {
            Log::info('CalculateProportionsJob: no landmarks, skipping proportions', [
                'evaluation_id' => $this->evaluationId,
            ]);
            // Write placeholder proportions so the next job still runs
            $this->saveProportions($evaluation, $this->defaultProportions());
            return;
        }

        $proportions = $this->calculate($landmarks);

        $this->saveProportions($evaluation, $proportions);
    }

    /**
     * @param array<string, array{x: float, y: float}> $lm Normalised landmarks
     * @return array<string, mixed>
     */
    private function calculate(array $lm): array
    {
        $proportions = [];

        // ── Facial Thirds (vertical) ──────────────────────────────────────────
        // Upper third:  forehead top → brow line (estimated as top of image → brow)
        // Middle third: brow → base of nose
        // Lower third:  base of nose → chin
        //
        // We approximate forehead top as y=0.10 (Rekognition gives face bounding box
        // but landmarks start at brows, so we use a sensible default).

        $browY   = isset($lm['leftEyeBrowLeft'], $lm['rightEyeBrowRight'])
            ? ($lm['leftEyeBrowLeft']['y'] + $lm['rightEyeBrowRight']['y']) / 2
            : 0.30;

        $noseBaseY = $lm['nose']['y'] ?? 0.55;
        $chinY     = $lm['chinBottom']['y'] ?? 0.88;
        $foreheadY = max(0, $browY - ($chinY - $browY) * 0.4); // estimate forehead top

        $totalFaceH = $chinY - $foreheadY;
        if ($totalFaceH > 0) {
            $upper  = ($browY - $foreheadY) / $totalFaceH;
            $middle = ($noseBaseY - $browY) / $totalFaceH;
            $lower  = ($chinY - $noseBaseY) / $totalFaceH;

            // Score = how close each third is to 0.333 (ideal 1:1:1)
            $thirdsScore = 100 - (
                abs($upper - 0.333) +
                abs($middle - 0.333) +
                abs($lower - 0.333)
            ) * 150;

            $proportions['facial_thirds'] = [
                'upper'  => round($upper, 3),
                'middle' => round($middle, 3),
                'lower'  => round($lower, 3),
                'score'  => (int) max(0, min(100, round($thirdsScore))),
            ];
        }

        // ── Nasal Symmetry ────────────────────────────────────────────────────
        // Compare left vs right distance from centre axis (nose midpoint)

        if (isset($lm['noseLeft'], $lm['noseRight'], $lm['nose'])) {
            $centreX   = $lm['nose']['x'];
            $leftDist  = abs($lm['noseLeft']['x'] - $centreX);
            $rightDist = abs($centreX - $lm['noseRight']['x']);

            // Symmetry: ratio of smaller to larger distance (1.0 = perfect)
            $maxDist  = max($leftDist, $rightDist);
            $symmetry = $maxDist > 0 ? min($leftDist, $rightDist) / $maxDist : 1.0;
            $nasalSymmetryScore = (int) round($symmetry * 100);

            $proportions['nasal_symmetry'] = [
                'score'       => $nasalSymmetryScore,
                'left_offset' => round($leftDist, 4),
                'right_offset' => round($rightDist, 4),
            ];
        }

        // ── Nasal Projection (Goode's Ratio) ─────────────────────────────────
        // Goode's ratio = nasal projection / nasal length (ideal 0.55–0.60)
        // We approximate projection from nose tip to alar base horizontal distance.

        if (isset($lm['nose'], $lm['noseLeft'], $lm['noseRight'])) {
            $alarMidY      = ($lm['noseLeft']['y'] + $lm['noseRight']['y']) / 2;
            $noseProjection = abs($lm['nose']['y'] - ($lm['leftEyeBrowLeft']['y'] ?? $browY));
            $alarWidth      = abs($lm['noseRight']['x'] - $lm['noseLeft']['x']);
            $alarMidX       = ($lm['noseLeft']['x'] + $lm['noseRight']['x']) / 2;
            $tipOffset      = abs($lm['nose']['x'] - $alarMidX); // deviation from centre

            // Goode's ratio approximation
            $noseLength = abs($lm['nose']['y'] - ($lm['leftEyeBrowLeft']['y'] ?? $browY));
            $goodesRatio = $noseLength > 0 ? round($tipOffset / $noseLength, 3) : self::IDEAL_NASAL_PROJECTION;

            $projectionDeviation = abs($goodesRatio - self::IDEAL_NASAL_PROJECTION);
            $projectionScore     = (int) max(0, min(100, round(100 - ($projectionDeviation * 200))));

            $proportions['nasal_projection'] = [
                'goodes_ratio'  => $goodesRatio,
                'ideal'         => self::IDEAL_NASAL_PROJECTION,
                'deviation'     => round($projectionDeviation, 3),
                'score'         => $projectionScore,
            ];
        }

        // ── Nasal Width Ratio (inter-alar / inter-canthal) ────────────────────
        // Ideal: alar width ≈ inter-canthal distance (ratio = 1.0)

        if (isset($lm['noseLeft'], $lm['noseRight'], $lm['leftEyeLeft'], $lm['rightEyeRight'])) {
            $alarWidth        = abs($lm['noseRight']['x'] - $lm['noseLeft']['x']);
            $interCanthal     = abs($lm['rightEyeLeft']['x'] - $lm['leftEyeRight']['x']);

            if ($interCanthal > 0) {
                $widthRatio      = round($alarWidth / $interCanthal, 3);
                $widthDeviation  = abs($widthRatio - self::IDEAL_NASAL_WIDTH_RATIO);
                $widthScore      = (int) max(0, min(100, round(100 - ($widthDeviation * 100))));

                $proportions['nasal_width_ratio'] = [
                    'ratio'       => $widthRatio,
                    'ideal'       => self::IDEAL_NASAL_WIDTH_RATIO,
                    'deviation'   => round($widthDeviation, 3),
                    'score'       => $widthScore,
                ];
            }
        }

        // ── Eye Symmetry (horizontal alignment) ──────────────────────────────
        // Compare Y coordinate of left vs right pupil — ideal = same height

        if (isset($lm['leftPupil'], $lm['rightPupil'])) {
            $eyeYDiff       = abs($lm['leftPupil']['y'] - $lm['rightPupil']['y']);
            $faceH          = $chinY - $foreheadY;
            $normalizedDiff = $faceH > 0 ? $eyeYDiff / $faceH : 0;
            $eyeSymScore    = (int) max(0, min(100, round(100 - ($normalizedDiff * 500))));

            $proportions['eye_symmetry'] = [
                'y_difference' => round($eyeYDiff, 4),
                'score'        => $eyeSymScore,
            ];
        }

        // ── Overall Harmony Score ─────────────────────────────────────────────
        // Weighted composite:
        //   nasal_symmetry    30% — most visible from consultation photos
        //   nasal_width_ratio 25%
        //   nasal_projection  20%
        //   facial_thirds     15%
        //   eye_symmetry      10%

        $weights = [
            'nasal_symmetry'   => 0.30,
            'nasal_width_ratio' => 0.25,
            'nasal_projection' => 0.20,
            'facial_thirds'    => 0.15,
            'eye_symmetry'     => 0.10,
        ];

        $weightedSum   = 0.0;
        $weightApplied = 0.0;

        foreach ($weights as $key => $weight) {
            if (isset($proportions[$key]['score'])) {
                $weightedSum   += $proportions[$key]['score'] * $weight;
                $weightApplied += $weight;
            }
        }

        $proportions['overall_harmony'] = $weightApplied > 0
            ? (int) round($weightedSum / $weightApplied)
            : 50;

        return $proportions;
    }

    /**
     * @param array<string, mixed> $proportions
     */
    private function saveProportions(Evaluation $evaluation, array $proportions): void
    {
        $existing = $evaluation->analysis_data ?? [];
        $evaluation->update([
            'analysis_data' => array_merge($existing, [
                'proportions'           => $proportions,
                'proportions_calculated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Default/neutral proportions — used when landmarks are unavailable.
     * Scores of 50 indicate "unknown" rather than good or bad.
     *
     * @return array<string, mixed>
     */
    private function defaultProportions(): array
    {
        return [
            'facial_thirds'     => ['upper' => 0.333, 'middle' => 0.333, 'lower' => 0.333, 'score' => 50],
            'nasal_symmetry'    => ['score' => 50, 'left_offset' => 0, 'right_offset' => 0],
            'nasal_projection'  => ['goodes_ratio' => self::IDEAL_NASAL_PROJECTION, 'ideal' => self::IDEAL_NASAL_PROJECTION, 'deviation' => 0, 'score' => 50],
            'nasal_width_ratio' => ['ratio' => self::IDEAL_NASAL_WIDTH_RATIO, 'ideal' => self::IDEAL_NASAL_WIDTH_RATIO, 'deviation' => 0, 'score' => 50],
            'eye_symmetry'      => ['y_difference' => 0, 'score' => 50],
            'overall_harmony'   => 50,
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CalculateProportionsJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error'         => $e->getMessage(),
        ]);
    }
}
