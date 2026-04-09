<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;

/**
 * Calculates a 0–100 lead score and priority tier for a completed evaluation.
 *
 * Score Breakdown (total 100 points):
 * ┌─────────────────────────────────────────┬────────┐
 * │ Factor                                  │ Weight │
 * ├─────────────────────────────────────────┼────────┤
 * │ Timeline urgency                        │  30 pts│
 * │ Budget alignment                        │  25 pts│
 * │ AI proportion harmony score             │  20 pts│
 * │ Photo quality (average of photos)       │  10 pts│
 * │ Concerns count (seriousness)            │  10 pts│
 * │ Referral source (intent signal)         │   5 pts│
 * └─────────────────────────────────────────┴────────┘
 *
 * Priority Tiers:
 *   Urgent   → score 80–100  (call within 1 hour)
 *   High     → score 60–79   (call within 4 hours)
 *   Medium   → score 40–59   (call within 24 hours)
 *   Standard → score 0–39    (review queue)
 *
 * Automatic priority boosts:
 *   - Revision rhinoplasty patient → +1 tier (higher clinical complexity)
 *   - Functional component (breathing) → +1 tier
 *   - Budget ≥ $15k + timeline ≤ 3 months → force at least 'high'
 */
class LeadScoringService
{
    /**
     * Score an evaluation and return [score (int), priority (string)].
     *
     * @param array<string, mixed> $proportions  From analysis_data.proportions
     * @param array<string, mixed> $quizAnswers   From evaluation.quiz_answers
     * @return array{int, string}                 [score, priority]
     */
    public function score(
        Evaluation $evaluation,
        array $proportions,
        array $quizAnswers,
    ): array {
        $score = 0;

        // ── Timeline (30 pts) ─────────────────────────────────────────────────
        $timeline = $quizAnswers['q_timeline'] ?? 'researching';
        $score += match ($timeline) {
            'asap'        => 30,
            '3_months'    => 22,
            '6_months'    => 12,
            'researching' => 3,
            default       => 5,
        };

        // ── Budget (25 pts) ───────────────────────────────────────────────────
        $budget = $quizAnswers['q_budget'] ?? 'under_10k';
        $score += match ($budget) {
            'over_25k'   => 25,
            '15k_25k'    => 20,
            '10k_15k'    => 13,
            'under_10k'  => 5,
            default      => 5,
        };

        // ── AI Harmony Score (20 pts) ─────────────────────────────────────────
        $harmony = (int) ($proportions['overall_harmony'] ?? 50);
        // Map 0–100 harmony → 0–20 points (scaled)
        $score += (int) round($harmony * 0.20);

        // ── Photo quality (10 pts) ────────────────────────────────────────────
        // Average quality score across all photos, normalised to 0–10
        $avgPhotoQuality = (int) ($proportions['_avg_photo_quality'] ?? 70);
        $score += (int) round($avgPhotoQuality * 0.10);

        // ── Concerns count (10 pts) ───────────────────────────────────────────
        // More concerns = higher engagement / more complex case
        $concerns = $quizAnswers['q_concerns'] ?? [];
        $concernCount = is_array($concerns) ? count($concerns) : 0;
        $score += match (true) {
            $concernCount >= 4 => 10,
            $concernCount >= 3 => 8,
            $concernCount >= 2 => 6,
            $concernCount >= 1 => 4,
            default            => 0,
        };

        // ── Referral source (5 pts) ───────────────────────────────────────────
        $referral = $quizAnswers['q_referral'] ?? null;
        $score += match ($referral) {
            'referral'  => 5,  // Word-of-mouth = high intent
            'google'    => 4,  // Active searcher
            'instagram' => 3,  // Social discovery
            'tiktok'    => 2,
            default     => 1,
        };

        // ── Clamp to 0–100 ────────────────────────────────────────────────────
        $score = max(0, min(100, $score));

        // ── Determine base priority ───────────────────────────────────────────
        $priority = $this->toPriority($score);

        // ── Priority boosts ───────────────────────────────────────────────────
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? false;
        $isRevision   = ($priorSurgery === true || $priorSurgery === 'true' || $priorSurgery === 1);

        $breathing    = $quizAnswers['q_breathing'] ?? false;
        $hasFunctional = ($breathing === true || $breathing === 'true' || $breathing === 1);

        if ($isRevision || $hasFunctional) {
            $priority = $this->boostPriority($priority);
        }

        // Force at least 'high' for serious budget + timeline combo
        $seriousBudget   = in_array($budget, ['15k_25k', 'over_25k'], true);
        $urgentTimeline  = in_array($timeline, ['asap', '3_months'], true);

        if ($seriousBudget && $urgentTimeline) {
            $priority = $this->atLeast($priority, Evaluation::PRIORITY_HIGH);
        }

        return [$score, $priority];
    }

    private function toPriority(int $score): string
    {
        return match (true) {
            $score >= 80 => Evaluation::PRIORITY_URGENT,
            $score >= 60 => Evaluation::PRIORITY_HIGH,
            $score >= 40 => Evaluation::PRIORITY_MEDIUM,
            default      => Evaluation::PRIORITY_STANDARD,
        };
    }

    /**
     * Boost a priority by one tier.
     * urgent → urgent (already max)
     */
    private function boostPriority(string $priority): string
    {
        return match ($priority) {
            Evaluation::PRIORITY_STANDARD => Evaluation::PRIORITY_MEDIUM,
            Evaluation::PRIORITY_MEDIUM   => Evaluation::PRIORITY_HIGH,
            Evaluation::PRIORITY_HIGH     => Evaluation::PRIORITY_URGENT,
            default                       => $priority,
        };
    }

    /**
     * Ensure a priority is at least the given minimum tier.
     */
    private function atLeast(string $current, string $minimum): string
    {
        $order = [
            Evaluation::PRIORITY_STANDARD => 0,
            Evaluation::PRIORITY_MEDIUM   => 1,
            Evaluation::PRIORITY_HIGH     => 2,
            Evaluation::PRIORITY_URGENT   => 3,
        ];

        $currentRank = $order[$current]  ?? 0;
        $minimumRank = $order[$minimum]  ?? 0;

        return $currentRank >= $minimumRank ? $current : $minimum;
    }
}
