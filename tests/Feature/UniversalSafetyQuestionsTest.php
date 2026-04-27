<?php

declare(strict_types=1);

use App\Models\QuizDefinition;
use Database\Seeders\ProcedureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Guardrails for the universal medical-safety question block that is prepended
 * to procedure quizzes (age, body type, smoking, pregnancy, conditions,
 * medications, allergies) + the buttock-injection safety gate for BBL-family
 * procedures. These questions are required for HIPAA/surgical-candidacy
 * screening and must never silently disappear.
 */
// ─── Universal block ─────────────────────────────────────────────────────────

test('bbl quiz includes all universal safety questions in order', function (): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', 'bbl')
        ->where('is_active', true)
        ->firstOrFail();

    $ids = collect($quiz->questions)->pluck('id')->all();

    // Universal block present, in the defined order, at the front of the quiz.
    $expectedUniversal = [
        'q_age_range',
        'q_body_type',
        'q_smoking',
        'q_pregnancy_status',
        'q_medical_history',
        'q_medications',
        'q_allergies',
    ];

    expect(array_slice($ids, 0, count($expectedUniversal)))->toBe($expectedUniversal);
});

test('bbl quiz includes buttock-injection safety questions immediately after universal block', function (): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', 'bbl')
        ->where('is_active', true)
        ->firstOrFail();

    $ids = collect($quiz->questions)->pluck('id')->all();

    // The two buttock-injection questions must sit between q_allergies and q_concerns.
    $allergiesIdx = array_search('q_allergies', $ids, true);
    $concernsIdx = array_search('q_concerns', $ids, true);

    expect($allergiesIdx)->not->toBeFalse()
        ->and($concernsIdx)->not->toBeFalse()
        ->and($ids[$allergiesIdx + 1])->toBe('q_buttock_injections')
        ->and($ids[$allergiesIdx + 2])->toBe('q_buttock_injection_details')
        ->and($concernsIdx)->toBe($allergiesIdx + 3);
});

test('bbl quiz preserves existing procedure-specific questions after safety block', function (): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', 'bbl')
        ->where('is_active', true)
        ->firstOrFail();

    $ids = collect($quiz->questions)->pluck('id')->all();

    // The original BBL flow must still be present, intact.
    foreach (['q_concerns', 'q_donor_areas', 'q_weight_stable', 'q_timeline', 'q_budget', 'q_referral'] as $key) {
        expect($ids)->toContain($key);
    }
});

// ─── Shape / contract ────────────────────────────────────────────────────────

test('universal safety questions use frontend-supported types only', function (): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', 'bbl')
        ->where('is_active', true)
        ->firstOrFail();

    $universalIds = [
        'q_age_range', 'q_body_type', 'q_smoking', 'q_pregnancy_status',
        'q_medical_history', 'q_medications', 'q_allergies',
        'q_buttock_injections', 'q_buttock_injection_details',
    ];

    // The ProcedureResource maps DB types → frontend literals:
    //   select -> single, multiselect -> multi, boolean & text pass through.
    $allowedDbTypes = ['select', 'multiselect', 'boolean', 'text'];

    foreach ($quiz->questions as $q) {
        if (in_array($q['id'], $universalIds, true)) {
            expect($q)->toHaveKeys(['id', 'type', 'label', 'required', 'branches'])
                ->and($q['type'])->toBeIn($allowedDbTypes);
        }
    }
});

test('buttock-injection yes answer branches to details; no branches skips to q_concerns', function (): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', 'bbl')
        ->where('is_active', true)
        ->firstOrFail();

    $gate = collect($quiz->questions)->firstWhere('id', 'q_buttock_injections');

    expect($gate)->not->toBeNull()
        ->and($gate['branches']['true']['next'] ?? null)->toBe('q_buttock_injection_details')
        ->and($gate['branches']['false']['next'] ?? null)->toBe('q_concerns');
});

// ─── Rolled-out procedures (TT, Lipo 360) ────────────────────────────────────

test('universal safety block is prepended on tummy_tuck and lipo_360', function (string $slug): void {
    $this->seed(ProcedureSeeder::class);

    $quiz = QuizDefinition::where('procedure_slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

    $ids = collect($quiz->questions)->pluck('id')->all();

    foreach (['q_age_range', 'q_body_type', 'q_smoking', 'q_pregnancy_status',
        'q_medical_history', 'q_medications', 'q_allergies'] as $key) {
        expect($ids)->toContain($key);
    }

    // Order: universal block is always first.
    expect(array_slice($ids, 0, 7))->toBe([
        'q_age_range', 'q_body_type', 'q_smoking', 'q_pregnancy_status',
        'q_medical_history', 'q_medications', 'q_allergies',
    ]);
})->with(['tummy_tuck', 'lipo_360']);

test('lipo_360 also carries buttock-injection questions; tummy_tuck does not', function (): void {
    $this->seed(ProcedureSeeder::class);

    $lipoIds = collect(QuizDefinition::where('procedure_slug', 'lipo_360')
        ->where('is_active', true)
        ->firstOrFail()
        ->questions)
        ->pluck('id')
        ->all();

    $ttIds = collect(QuizDefinition::where('procedure_slug', 'tummy_tuck')
        ->where('is_active', true)
        ->firstOrFail()
        ->questions)
        ->pluck('id')
        ->all();

    expect($lipoIds)->toContain('q_buttock_injections')
        ->and($lipoIds)->toContain('q_buttock_injection_details')
        ->and($ttIds)->not->toContain('q_buttock_injections');
});
