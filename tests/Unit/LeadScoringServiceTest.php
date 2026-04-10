<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Services\LeadScoringService;
use Illuminate\Support\Str;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a minimal quiz answers array for scoring.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quizAnswers(array $overrides = []): array
{
    return array_merge([
        'q_timeline' => 'researching',
        'q_budget' => 'under_10k',
        'q_concerns' => [],
        'q_referral' => null,
        'q_prior_surgery' => false,
        'q_breathing' => false,
    ], $overrides);
}

/**
 * Build a minimal proportions array for scoring.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function proportions(array $overrides = []): array
{
    return array_merge([
        'overall_harmony' => 50,
        '_avg_photo_quality' => 70,
    ], $overrides);
}

function makeUnsavedEvaluation(): Evaluation
{
    $e = new Evaluation;
    $e->id = (string) Str::uuid();

    return $e;
}

// ─── Timeline scoring (30 pts) ────────────────────────────────────────────────

test('asap timeline scores 30 pts', function (): void {
    $svc = new LeadScoringService;
    [$score] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_timeline' => 'asap']));
    // 30 (timeline) + 5 (budget) + 10 (harmony@50) + 7 (quality@70) + 0 (concerns) + 1 (referral)
    expect($score)->toBe(53);
});

test('3_months timeline scores 22 pts', function (): void {
    $svc = new LeadScoringService;
    [$score] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_timeline' => '3_months']));
    expect($score)->toBe(45);
});

test('researching timeline scores 3 pts', function (): void {
    $svc = new LeadScoringService;
    [$score] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_timeline' => 'researching']));
    expect($score)->toBe(26);
});

// ─── Budget scoring (25 pts) ──────────────────────────────────────────────────

test('over_25k budget scores 25 pts', function (): void {
    $svc = new LeadScoringService;
    [$score] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_budget' => 'over_25k']));
    expect($score)->toBe(46);
});

test('15k_25k budget scores 20 pts', function (): void {
    $svc = new LeadScoringService;
    [$score] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_budget' => '15k_25k']));
    expect($score)->toBe(41);
});

// ─── Priority tiers ───────────────────────────────────────────────────────────

test('score >= 80 resolves to urgent priority', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 100]), quizAnswers([
        'q_timeline' => 'asap',
        'q_budget' => 'over_25k',
        'q_concerns' => ['tip', 'bridge', 'nostrils', 'profile'],
        'q_referral' => 'referral',
    ]));
    expect($priority)->toBe(Evaluation::PRIORITY_URGENT);
});

test('score 60-79 resolves to high priority', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers([
        'q_timeline' => 'asap',
        'q_budget' => '15k_25k',
        'q_referral' => 'google',
    ]));
    expect($priority)->toBe(Evaluation::PRIORITY_HIGH);
});

test('score 40-59 resolves to medium priority', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers([
        'q_timeline' => 'asap',
    ]));
    expect($priority)->toBe(Evaluation::PRIORITY_MEDIUM);
});

test('score below 40 resolves to standard priority', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]), quizAnswers());
    expect($priority)->toBe(Evaluation::PRIORITY_STANDARD);
});

// ─── Priority boosts ─────────────────────────────────────────────────────────

test('revision rhinoplasty boosts priority by one tier', function (): void {
    $svc = new LeadScoringService;

    // Base score puts us at standard, revision should push to medium
    [$score, $priority] = $svc->score(
        makeUnsavedEvaluation(),
        proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]),
        quizAnswers(['q_prior_surgery' => true]),
    );

    expect($priority)->toBe(Evaluation::PRIORITY_MEDIUM);
});

test('functional breathing component boosts priority by one tier', function (): void {
    $svc = new LeadScoringService;

    [$score, $priority] = $svc->score(
        makeUnsavedEvaluation(),
        proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]),
        quizAnswers(['q_breathing' => true]),
    );

    expect($priority)->toBe(Evaluation::PRIORITY_MEDIUM);
});

test('urgent priority is not boosted above urgent', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 100]), quizAnswers([
        'q_timeline' => 'asap',
        'q_budget' => 'over_25k',
        'q_concerns' => ['a', 'b', 'c', 'd'],
        'q_referral' => 'referral',
        'q_prior_surgery' => true,
    ]));
    expect($priority)->toBe(Evaluation::PRIORITY_URGENT);
});

// ─── Force-upgrade rule ───────────────────────────────────────────────────────

test('serious budget + urgent timeline forces at least high priority', function (): void {
    $svc = new LeadScoringService;

    // Low harmony + no concerns → would normally be medium, force-upgrade to high
    [$score, $priority] = $svc->score(
        makeUnsavedEvaluation(),
        proportions(['overall_harmony' => 10, '_avg_photo_quality' => 10]),
        quizAnswers([
            'q_timeline' => 'asap',
            'q_budget' => '15k_25k',
        ]),
    );

    expect($priority)->toBe(Evaluation::PRIORITY_HIGH);
});

test('force-upgrade does not downgrade urgent to high', function (): void {
    $svc = new LeadScoringService;
    [, $priority] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 100]), quizAnswers([
        'q_timeline' => 'asap',
        'q_budget' => 'over_25k',
        'q_concerns' => ['a', 'b', 'c', 'd'],
        'q_referral' => 'referral',
    ]));
    expect($priority)->toBe(Evaluation::PRIORITY_URGENT);
});

// ─── Score clamping ───────────────────────────────────────────────────────────

test('score is always between 0 and 100', function (): void {
    $svc = new LeadScoringService;

    [$min] = $svc->score(
        makeUnsavedEvaluation(),
        proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]),
        quizAnswers(['q_referral' => null]),
    );

    [$max] = $svc->score(
        makeUnsavedEvaluation(),
        proportions(['overall_harmony' => 100, '_avg_photo_quality' => 100]),
        quizAnswers([
            'q_timeline' => 'asap',
            'q_budget' => 'over_25k',
            'q_concerns' => ['a', 'b', 'c', 'd'],
            'q_referral' => 'referral',
        ]),
    );

    expect($min)->toBeGreaterThanOrEqual(0);
    expect($max)->toBeLessThanOrEqual(100);
});

// ─── Referral source ─────────────────────────────────────────────────────────

test('word-of-mouth referral scores 5 pts', function (): void {
    $svc = new LeadScoringService;
    [$withReferral] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_referral' => 'referral']));
    [$noReferral] = $svc->score(makeUnsavedEvaluation(), proportions(), quizAnswers(['q_referral' => null]));
    expect($withReferral - $noReferral)->toBe(4); // 5 pts vs 1 pt default
});

// ─── Concerns count ───────────────────────────────────────────────────────────

dataset('concern_counts', [
    'no concerns → 0 pts' => [[], 0],
    '1 concern → 4 pts' => [['tip'], 4],
    '2 concerns → 6 pts' => [['tip', 'bridge'], 6],
    '3 concerns → 8 pts' => [['tip', 'bridge', 'nostrils'], 8],
    '4+ concerns → 10 pts' => [['tip', 'bridge', 'nostrils', 'profile'], 10],
]);

test('concerns count scoring', function (array $concerns, int $expectedPts): void {
    $svc = new LeadScoringService;

    // Use zeroed-out other factors to isolate concern pts
    [$withConcerns] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]), quizAnswers(['q_concerns' => $concerns, 'q_referral' => null]));
    [$noConcerns] = $svc->score(makeUnsavedEvaluation(), proportions(['overall_harmony' => 0, '_avg_photo_quality' => 0]), quizAnswers(['q_concerns' => [], 'q_referral' => null]));

    expect($withConcerns - $noConcerns)->toBe($expectedPts);
})->with('concern_counts');
