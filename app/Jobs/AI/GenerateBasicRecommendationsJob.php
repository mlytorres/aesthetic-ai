<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Models\Evaluation;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job 4/4 in the AI pipeline.
 *
 * Generates rule-based procedure recommendations by combining:
 *   - Facial proportion scores from CalculateProportionsJob
 *   - Quiz answers (concerns, prior surgery, skin type, budget, timeline)
 *
 * This is intentionally rule-based (not ML) for the MVP. It produces:
 *   - A primary recommendation with confidence and reasoning bullets
 *   - Flagged concerns that the surgeon should review
 *   - Technique suggestions based on skin type and prior surgery
 *
 * After generating recommendations, it:
 *   1. Calls LeadScoringService to compute the lead score + priority
 *   2. Updates evaluation status to 'complete'
 *   3. Dispatches NotifyClinicNewEvaluationJob
 */
class GenerateBasicRecommendationsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(LeadScoringService $scorer, AuditLog $auditLog): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        /** @var Evaluation $evaluation */
        $evaluation   = Evaluation::withoutGlobalScopes()->findOrFail($this->evaluationId);
        $analysisData = $evaluation->analysis_data ?? [];
        $proportions  = $analysisData['proportions'] ?? [];
        $quizAnswers  = $evaluation->quiz_answers ?? [];
        $procedure    = $evaluation->procedure_slug;

        // ── Generate recommendations ──────────────────────────────────────────
        $recommendations = match ($procedure) {
            'rhinoplasty' => $this->rhinoplastyRecommendations($proportions, $quizAnswers),
            default       => $this->genericRecommendations($proportions, $quizAnswers),
        };

        // ── Score the lead ────────────────────────────────────────────────────
        [$leadScore, $priority] = $scorer->score($evaluation, $proportions, $quizAnswers);

        // ── Persist everything and mark complete ──────────────────────────────
        $evaluation->update([
            'status'        => Evaluation::STATUS_COMPLETE,
            'lead_score'    => $leadScore,
            'priority'      => $priority,
            'analysis_data' => array_merge($analysisData, [
                'recommendations'           => $recommendations,
                'recommendations_generated_at' => now()->toIso8601String(),
            ]),
        ]);

        $auditLog->recordSystem('evaluation.analysis.complete', $evaluation, [
            'lead_score' => $leadScore,
            'priority'   => $priority,
        ]);

        // ── Notify clinic ─────────────────────────────────────────────────────
        if (config('features.notifications', true)) {
            NotifyClinicNewEvaluationJob::dispatch($this->evaluationId)->onQueue('notifications');
        }
    }

    /**
     * Rhinoplasty-specific rule-based recommendations.
     *
     * @param array<string, mixed> $proportions
     * @param array<string, mixed> $quizAnswers
     * @return array<string, mixed>
     */
    private function rhinoplastyRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags   = [];
        $bullets = [];
        $techniques = [];

        // ── Skin thickness ────────────────────────────────────────────────────
        $skinType = $quizAnswers['q_skin_thickness'] ?? null;
        match ($skinType) {
            'thin'  => $bullets[] = 'Thin skin provides excellent definition and shows fine detail work well, but requires meticulous refinement to avoid visible scarring.',
            'thick' => $bullets[] = 'Thick/sebaceous skin may obscure tip refinement. Skin-thinning techniques (defatting) may be discussed during consultation.',
            default => null,
        };

        // ── Prior rhinoplasty ─────────────────────────────────────────────────
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        if ($priorSurgery === true || $priorSurgery === 'true' || $priorSurgery === 1) {
            $flags[]    = 'revision_rhinoplasty';
            $bullets[]  = 'Revision rhinoplasty is technically more complex due to scar tissue and altered anatomy. Surgeon should review prior operative notes if available.';
            $techniques[] = 'Revision approach likely required';
        }

        // ── Breathing concerns ────────────────────────────────────────────────
        $breathing = $quizAnswers['q_breathing'] ?? null;
        if ($breathing === true || $breathing === 'true' || $breathing === 1) {
            $flags[]    = 'functional_component';
            $bullets[]  = 'Patient reports nasal breathing difficulties. Functional evaluation (septal deviation, turbinate hypertrophy) recommended alongside cosmetic assessment.';
            $techniques[] = 'Functional rhinoplasty component likely';
        }

        // ── Concern areas ─────────────────────────────────────────────────────
        $concerns = $quizAnswers['q_concerns'] ?? [];
        if (is_array($concerns)) {
            if (in_array('bridge', $concerns, true)) {
                $bullets[] = 'Dorsal hump reduction requested. Osteotomies may be required to close the open roof after hump removal.';
                $techniques[] = 'Hump reduction + osteotomies';
            }
            if (in_array('tip', $concerns, true)) {
                $bullets[] = 'Tip refinement requested. Structural grafting (tip graft, columellar strut) may enhance definition and maintain support.';
                $techniques[] = 'Tip refinement ± structural grafting';
            }
            if (in_array('nostrils', $concerns, true)) {
                $bullets[] = 'Alar base modification requested. Weir excisions may be considered for nostril width/flare reduction.';
                $techniques[] = 'Alar base reduction (Weir excisions)';
            }
            if (in_array('asymmetry', $concerns, true)) {
                $flags[]   = 'asymmetry_noted';
                $bullets[] = 'Asymmetry is a primary concern. AI analysis supports this — surgical plan should prioritise bilateral symmetry.';
            }
        }

        // ── Proportion-based observations ─────────────────────────────────────
        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        if ($harmonyScore >= 75) {
            $bullets[] = 'Facial proportions are within normal range. Targeted refinements should achieve an excellent aesthetic result.';
        } elseif ($harmonyScore >= 50) {
            $bullets[] = 'Facial proportions show moderate variation from ideal ratios. Surgical planning should account for these findings.';
        } else {
            $flags[]   = 'significant_proportion_deviation';
            $bullets[] = 'Proportion analysis indicates notable deviation from ideal facial thirds/fifths. Surgeon should review AI measurements during consultation.';
        }

        $nasalSym = $proportions['nasal_symmetry']['score'] ?? 50;
        if ($nasalSym < 70) {
            $flags[]   = 'nasal_asymmetry_detected';
            $bullets[] = sprintf('Nasal symmetry score: %d/100. Asymmetry visible in AI analysis — surgeon should confirm clinically.', $nasalSym);
        }

        $nasalWidth = $proportions['nasal_width_ratio'] ?? [];
        if (isset($nasalWidth['ratio'])) {
            if ($nasalWidth['ratio'] > 1.2) {
                $bullets[] = sprintf('Alar width ratio %.2f exceeds ideal (1.0), suggesting wider-than-ideal nostrils. Alar base reduction may be beneficial.', $nasalWidth['ratio']);
            } elseif ($nasalWidth['ratio'] < 0.8) {
                $bullets[] = sprintf('Alar width ratio %.2f is below ideal (1.0). This narrow base may influence tip projection planning.', $nasalWidth['ratio']);
            }
        }

        return [
            'procedure'       => 'rhinoplasty',
            'confidence'      => $this->confidenceFromHarmony($harmonyScore),
            'primary_finding' => $this->primaryFinding($concerns, $priorSurgery),
            'flags'           => array_unique($flags),
            'key_points'      => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score'   => $harmonyScore,
        ];
    }

    /**
     * Generic fallback for non-rhinoplasty procedures.
     *
     * @param array<string, mixed> $proportions
     * @param array<string, mixed> $quizAnswers
     * @return array<string, mixed>
     */
    private function genericRecommendations(array $proportions, array $quizAnswers): array
    {
        return [
            'procedure'       => 'general',
            'confidence'      => 'medium',
            'primary_finding' => 'Evaluation completed. Full AI analysis available for rhinoplasty — other procedures use standard intake review.',
            'flags'           => [],
            'key_points'      => ['Manual review recommended for this procedure type.'],
            'technique_notes' => [],
            'harmony_score'   => $proportions['overall_harmony'] ?? 50,
        ];
    }

    private function confidenceFromHarmony(int $harmonyScore): string
    {
        return match (true) {
            $harmonyScore >= 75 => 'high',
            $harmonyScore >= 50 => 'medium',
            default             => 'low',
        };
    }

    /**
     * @param array<int, string>|mixed $concerns
     * @param mixed $priorSurgery
     */
    private function primaryFinding(mixed $concerns, mixed $priorSurgery): string
    {
        if ($priorSurgery === true || $priorSurgery === 'true') {
            return 'Revision rhinoplasty patient — prior surgery documented.';
        }

        if (is_array($concerns) && count($concerns) > 0) {
            $labels = [
                'bridge'     => 'dorsal hump',
                'tip'        => 'nasal tip',
                'nostrils'   => 'alar base',
                'asymmetry'  => 'nasal asymmetry',
                'projection' => 'nasal projection',
            ];

            $mapped = array_filter(
                array_map(fn ($c) => $labels[$c] ?? null, $concerns)
            );

            return 'Primary concerns: ' . implode(', ', $mapped) . '.';
        }

        return 'General rhinoplasty consultation requested.';
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateBasicRecommendationsJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error'         => $e->getMessage(),
        ]);

        Evaluation::withoutGlobalScopes()
            ->where('id', $this->evaluationId)
            ->update(['status' => Evaluation::STATUS_FAILED]);
    }
}
