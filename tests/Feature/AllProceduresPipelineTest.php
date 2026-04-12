<?php

declare(strict_types=1);

use App\Jobs\AI\ExtractBodyLandmarksJob;
use App\Jobs\AI\ExtractFacialLandmarksJob;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Jobs\AI\GenerateSimulationJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\ProcedureRegistry;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Image;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeAllProceduresEval(string $procedure): array
{
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'coordinator']);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => $procedure,
        'status' => Evaluation::STATUS_COMPLETE,
    ]);

    return [$tenant, $patient, $user, $evaluation];
}

// ─── Pipeline routing — registry drives correct job selection ─────────────────
//
// The ProcedureRegistry unit tests fully verify every slug's pipeline type.
// Here we assert the registry is correctly wired: body slugs select the body
// landmark jobs; face slugs select the facial landmark jobs.

test('controller selects ExtractBodyLandmarksJob for body procedures', function (string $procedure): void {
    expect(ProcedureRegistry::isBodyProcedure($procedure))->toBeTrue();

    // The job chosen depends on isBodyProcedure() — verify the correct class.
    $landmarkJob = ProcedureRegistry::isBodyProcedure($procedure)
        ? new ExtractBodyLandmarksJob('fake-id')
        : new ExtractFacialLandmarksJob('fake-id');

    expect($landmarkJob)->toBeInstanceOf(ExtractBodyLandmarksJob::class);
})->with([
    'tummy_tuck',
    'mommy_makeover',
    'breast_lift',
    'breast_reduction',
    'skinny_bbl',
    'reverse_bbl',
    'liposuction',
    'gynecomastia',
    'arm_lipo_lift',
    'arm_thigh_lift',
    'abdominal_etching',
    'j_plasma',
    'labiaplasty',
    'axillary_liposuction',
    'back_liposuction_lift',
    'arm_thigh_lift',
]);

test('controller selects ExtractFacialLandmarksJob for face procedures', function (string $procedure): void {
    expect(ProcedureRegistry::isFaceProcedure($procedure))->toBeTrue();

    $landmarkJob = ProcedureRegistry::isBodyProcedure($procedure)
        ? new ExtractBodyLandmarksJob('fake-id')
        : new ExtractFacialLandmarksJob('fake-id');

    expect($landmarkJob)->toBeInstanceOf(ExtractFacialLandmarksJob::class);
})->with([
    'face_and_neck_lift',
    'chin_lipo',
    'eyelid_surgery',
    'bichectomy',
    'otoplasty',
]);

// ─── GenerateSimulationJob — all new procedures produce a prompt ───────────────

test('simulation job completes in placeholder mode for all new procedures', function (string $procedure): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    [, , , $evaluation] = makeAllProceduresEval($procedure);
    $evaluation->update(['simulation_status' => 'pending']);

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();

    expect($evaluation->simulation_status)->toBe('complete')
        ->and($evaluation->simulation_data['mode'])->toBe('simulated')
        ->and($evaluation->simulation_data['prompt'])->toBeString()
        ->and(strlen($evaluation->simulation_data['prompt']))->toBeGreaterThan(30);

    Image::assertNothingGenerated();
})->with([
    // Body
    'tummy_tuck',
    'mommy_makeover',
    'breast_lift',
    'breast_reduction',
    'skinny_bbl',
    'reverse_bbl',
    'liposuction',
    'gynecomastia',
    'arm_lipo_lift',
    'arm_thigh_lift',
    'back_liposuction_lift',
    'axillary_liposuction',
    'abdominal_etching',
    'j_plasma',
    // Face
    'face_and_neck_lift',
    'chin_lipo',
    'eyelid_surgery',
    'bichectomy',
    'otoplasty',
]);

test('simulation prompt for tummy_tuck mentions abdominoplasty', function (): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    [, , , $evaluation] = makeAllProceduresEval('tummy_tuck');

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();

    expect(str_contains(strtolower($evaluation->simulation_data['prompt']), 'tummy tuck'))->toBeTrue();
});

test('simulation prompt for mommy_makeover mentions post-pregnancy restoration', function (): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    [, , , $evaluation] = makeAllProceduresEval('mommy_makeover');

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();

    expect(str_contains(strtolower($evaluation->simulation_data['prompt']), 'mommy makeover'))->toBeTrue();
});

test('simulation prompt for eyelid_surgery mentions blepharoplasty', function (): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    [, , , $evaluation] = makeAllProceduresEval('eyelid_surgery');

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();

    expect(str_contains(strtolower($evaluation->simulation_data['prompt']), 'blepharoplasty'))->toBeTrue();
});

// ─── LeadScoringService — high-revenue priority boosts ───────────────────────

test('mommy_makeover forces at least high priority regardless of low score', function (): void {
    $evaluation = new Evaluation;
    $evaluation->procedure_slug = 'mommy_makeover';

    $scorer = new LeadScoringService;
    // Researching timeline + under_10k budget → base score ~19 (Standard tier)
    [, $priority] = $scorer->score(
        $evaluation,
        ['overall_harmony' => 50, '_avg_photo_quality' => 70],
        ['q_timeline' => 'researching', 'q_budget' => 'under_10k', 'q_concerns' => [], 'q_referral' => null],
    );

    expect($priority)->toBe(Evaluation::PRIORITY_HIGH);
});

test('tummy_tuck forces at least high priority regardless of low score', function (): void {
    $evaluation = new Evaluation;
    $evaluation->procedure_slug = 'tummy_tuck';

    $scorer = new LeadScoringService;
    [, $priority] = $scorer->score(
        $evaluation,
        ['overall_harmony' => 50, '_avg_photo_quality' => 70],
        ['q_timeline' => 'researching', 'q_budget' => 'under_10k', 'q_concerns' => [], 'q_referral' => null],
    );

    expect($priority)->toBe(Evaluation::PRIORITY_HIGH);
});

test('non-high-revenue procedure can score standard priority', function (): void {
    $evaluation = new Evaluation;
    $evaluation->procedure_slug = 'chin_lipo';

    $scorer = new LeadScoringService;
    [, $priority] = $scorer->score(
        $evaluation,
        ['overall_harmony' => 50, '_avg_photo_quality' => 70],
        ['q_timeline' => 'researching', 'q_budget' => 'under_10k', 'q_concerns' => [], 'q_referral' => null],
    );

    expect($priority)->toBe(Evaluation::PRIORITY_STANDARD);
});

// ─── GenerateBasicRecommendationsJob — new procedure safety flags ─────────────

test('tummy_tuck recommendations flag future pregnancy concern', function (): void {
    config(['features.notifications' => false]);

    [, , , $evaluation] = makeAllProceduresEval('tummy_tuck');

    $evaluation->update([
        'analysis_data' => ['proportions' => ['overall_harmony' => 60]],
        'quiz_answers' => [
            'q_future_pregnancy' => true,
            'q_diastasis' => false,
            'q_prior_surgery' => false,
            'q_weight_stable' => true,
            'q_timeline' => 'asap',
            'q_budget' => 'over_25k',
        ],
    ]);

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(new LeadScoringService, app(AuditLog::class), app(WebhookService::class));

    $evaluation->refresh();
    $flags = $evaluation->analysis_data['recommendations']['flags'] ?? [];

    expect($flags)->toContain('future_pregnancy_planned');
});

test('skinny_bbl recommendations flag low donor fat when BMI is low', function (): void {
    config(['features.notifications' => false]);

    [, , , $evaluation] = makeAllProceduresEval('skinny_bbl');

    $evaluation->update([
        'analysis_data' => ['proportions' => ['overall_harmony' => 60]],
        'quiz_answers' => [
            'q_bmi_range' => 'low',
            'q_donor_areas' => ['abdomen'],
            'q_weight_stable' => true,
            'q_timeline' => '3_months',
            'q_budget' => '10k_15k',
        ],
    ]);

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(new LeadScoringService, app(AuditLog::class), app(WebhookService::class));

    $evaluation->refresh();
    $flags = $evaluation->analysis_data['recommendations']['flags'] ?? [];

    expect($flags)->toContain('low_donor_fat_concern')
        ->and($flags)->toContain('bbl_safety_protocol_required');
});

test('mommy_makeover recommendations flag breastfeeding timing', function (): void {
    config(['features.notifications' => false]);

    [, , , $evaluation] = makeAllProceduresEval('mommy_makeover');

    $evaluation->update([
        'analysis_data' => ['proportions' => ['overall_harmony' => 60]],
        'quiz_answers' => [
            'q_concerns' => ['breast', 'abdomen'],
            'q_future_pregnancy' => false,
            'q_breastfeeding' => true,
            'q_weight_stable' => true,
            'q_timeline' => 'asap',
            'q_budget' => 'over_25k',
        ],
    ]);

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(new LeadScoringService, app(AuditLog::class), app(WebhookService::class));

    $evaluation->refresh();
    $flags = $evaluation->analysis_data['recommendations']['flags'] ?? [];

    expect($flags)->toContain('currently_breastfeeding')
        ->and($flags)->toContain('combined_procedure_complexity');
});

test('breast_reduction flags functional back pain when reported', function (): void {
    config(['features.notifications' => false]);

    [, , , $evaluation] = makeAllProceduresEval('breast_reduction');

    $evaluation->update([
        'analysis_data' => ['proportions' => ['overall_harmony' => 60]],
        'quiz_answers' => [
            'q_concerns' => ['back_pain', 'shoulder_grooving'],
            'q_breastfeeding' => false,
            'q_prior_surgery' => false,
            'q_timeline' => '3_months',
            'q_budget' => '10k_15k',
        ],
    ]);

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(new LeadScoringService, app(AuditLog::class), app(WebhookService::class));

    $evaluation->refresh();
    $flags = $evaluation->analysis_data['recommendations']['flags'] ?? [];

    expect($flags)->toContain('functional_back_pain');
});

test('eyelid_surgery flags dry eye risk when reported', function (): void {
    config(['features.notifications' => false]);

    [, , , $evaluation] = makeAllProceduresEval('eyelid_surgery');

    $evaluation->update([
        'analysis_data' => ['proportions' => ['overall_harmony' => 60]],
        'quiz_answers' => [
            'q_concerns' => ['upper'],
            'q_dry_eyes' => true,
            'q_prior_surgery' => false,
            'q_timeline' => 'asap',
            'q_budget' => '10k_15k',
        ],
    ]);

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(new LeadScoringService, app(AuditLog::class), app(WebhookService::class));

    $evaluation->refresh();
    $flags = $evaluation->analysis_data['recommendations']['flags'] ?? [];

    expect($flags)->toContain('dry_eye_risk');
});
