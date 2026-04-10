<?php

declare(strict_types=1);

use App\Jobs\AI\ExtractFacialLandmarksJob;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\TenantContext;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeVisionEvaluation(Tenant $tenant, string $procedure, array $quizAnswers = [], array $proportions = []): Evaluation
{
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $defaultProportions = array_merge([
        'overall_harmony'   => 70,
        'nasal_symmetry'    => ['score' => 80, 'left_offset' => 0.02, 'right_offset' => 0.02],
        'nasal_width_ratio' => ['ratio' => 1.0, 'ideal' => 1.0, 'deviation' => 0.0, 'score' => 100],
        'nasal_projection'  => ['goodes_ratio' => 0.575, 'ideal' => 0.575, 'deviation' => 0.0, 'score' => 100],
        'facial_thirds'     => ['upper' => 0.333, 'middle' => 0.333, 'lower' => 0.333, 'score' => 100],
        'eye_symmetry'      => ['y_difference' => 0.001, 'score' => 95],
    ], $proportions);

    return Evaluation::factory()->create([
        'tenant_id'      => $tenant->id,
        'patient_id'     => $patient->id,
        'status'         => Evaluation::STATUS_ANALYZING,
        'procedure_slug' => $procedure,
        'quiz_answers'   => $quizAnswers,
        'analysis_data'  => ['proportions' => $defaultProportions],
    ]);
}

function runRecommendations(Evaluation $evaluation): Evaluation
{
    $job = new GenerateBasicRecommendationsJob($evaluation->id);
    $job->handle(
        app(LeadScoringService::class),
        app(AuditLog::class),
        app(WebhookService::class),
    );

    return $evaluation->fresh();
}

// ─── ExtractFacialLandmarksJob simulation ─────────────────────────────────────

test('ExtractFacialLandmarksJob simulation mode generates face attributes', function (): void {
    $tenant    = Tenant::factory()->create();
    $patient   = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id'  => $tenant->id,
        'patient_id' => $patient->id,
        'status'     => Evaluation::STATUS_SUBMITTED,
    ]);

    Photo::factory()->create([
        'tenant_id'       => $tenant->id,
        'evaluation_id'   => $evaluation->id,
        'type'            => Photo::TYPE_FRONT,
        'analysis_status' => Photo::ANALYSIS_COMPLETE,
    ]);

    config(['features.ai_vision' => false]);

    $job = new ExtractFacialLandmarksJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $landmarks = $evaluation->analysis_data['landmarks'];

    expect($landmarks)->toHaveKey('_face_attributes')
        ->and($landmarks['_face_attributes'])->toHaveKeys(['age_range', 'photo_quality', 'pose', 'confidence'])
        ->and($landmarks['_face_attributes']['age_range'])->toHaveKeys(['low', 'high', 'midpoint'])
        ->and($landmarks['_face_attributes']['age_range']['midpoint'])->toBeInt()
        ->and($landmarks['_face_attributes']['age_range']['midpoint'])
            ->toBeGreaterThanOrEqual(28)
            ->toBeLessThanOrEqual(50);
});

test('ExtractFacialLandmarksJob simulation mode generates standard landmarks', function (): void {
    $tenant    = Tenant::factory()->create();
    $patient   = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id'  => $tenant->id,
        'patient_id' => $patient->id,
        'status'     => Evaluation::STATUS_SUBMITTED,
    ]);

    Photo::factory()->create([
        'tenant_id'       => $tenant->id,
        'evaluation_id'   => $evaluation->id,
        'type'            => Photo::TYPE_FRONT,
        'analysis_status' => Photo::ANALYSIS_COMPLETE,
    ]);

    config(['features.ai_vision' => false]);

    (new ExtractFacialLandmarksJob($evaluation->id))->handle();

    $landmarks = $evaluation->fresh()->analysis_data['landmarks'];

    expect($landmarks)->toHaveKey('nose')
        ->toHaveKey('eyeLeft')
        ->toHaveKey('eyeRight')
        ->toHaveKey('chinBottom')
        ->toHaveKey('leftPupil')
        ->toHaveKey('rightPupil');
});

// ─── Rhinoplasty recommendations ─────────────────────────────────────────────

test('rhinoplasty flags revision when prior surgery is true', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'rhinoplasty', ['q_prior_surgery' => true]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    $flags = $fresh->analysis_data['recommendations']['flags'];

    expect($flags)->toContain('revision_rhinoplasty');
});

test('rhinoplasty flags functional_component when breathing issues', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'rhinoplasty', ['q_breathing' => true]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('functional_component');
});

test('rhinoplasty flags nasal_asymmetry_detected when score below 70', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'rhinoplasty', [], [
        'nasal_symmetry' => ['score' => 60, 'left_offset' => 0.08, 'right_offset' => 0.04],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('nasal_asymmetry_detected');
});

test('rhinoplasty produces required recommendation keys', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'rhinoplasty', [
        'q_concerns' => ['bridge', 'tip'],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    $rec = $fresh->analysis_data['recommendations'];

    expect($rec)->toHaveKeys(['procedure', 'confidence', 'primary_finding', 'flags', 'key_points', 'technique_notes', 'harmony_score'])
        ->and($rec['procedure'])->toBe('rhinoplasty');
});

// ─── BBL recommendations ──────────────────────────────────────────────────────

test('bbl always sets bbl_safety_protocol_required flag', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'bbl', [
        'q_weight_stable' => true,
        'q_concerns'      => ['hourglass'],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('bbl_safety_protocol_required');
});

test('bbl flags weight_unstable when weight is not stable', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'bbl', ['q_weight_stable' => false]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('weight_unstable');
});

test('bbl flags donor_areas_unspecified when none selected', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'bbl', [
        'q_weight_stable' => true,
        'q_donor_areas'   => [],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('donor_areas_unspecified');
});

// ─── Lipo 360 recommendations ─────────────────────────────────────────────────

test('lipo_360 flags skin_laxity_concern for moderate laxity', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'lipo_360', ['q_skin_laxity' => 'moderate']);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('skin_laxity_concern');
});

test('lipo_360 does not flag skin_laxity_concern for excellent skin', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'lipo_360', ['q_skin_laxity' => 'excellent']);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->not->toContain('skin_laxity_concern');
});

// ─── Breast augmentation recommendations ─────────────────────────────────────

test('breast_augmentation flags revision_breast_surgery for prior surgery', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'breast_augmentation', ['q_prior_surgery' => true]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('revision_breast_surgery');
});

test('breast_augmentation flags large_volume_request for 3+ cup goal', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'breast_augmentation', ['q_size_goal' => '3_plus']);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('large_volume_request');
});

test('breast_augmentation flags lift_consideration when lift is a concern', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'breast_augmentation', ['q_concerns' => ['lift']]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('lift_consideration');
});

// ─── Facelift recommendations (age estimation) ────────────────────────────────

test('facelift flags young_facelift_candidate when estimated age under 40', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', [], [
        '_face_attributes' => [
            'age_range' => ['low' => 32, 'high' => 38, 'midpoint' => 35],
        ],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('young_facelift_candidate');
});

test('facelift flags mature_facelift_candidate when estimated age 60+', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', [], [
        '_face_attributes' => [
            'age_range' => ['low' => 60, 'high' => 68, 'midpoint' => 64],
        ],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    $flags = $fresh->analysis_data['recommendations']['flags'];

    expect($flags)->toContain('mature_facelift_candidate');
});

test('facelift includes deep-plane technique note for mature candidates', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', [], [
        '_face_attributes' => [
            'age_range' => ['low' => 62, 'high' => 70, 'midpoint' => 66],
        ],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    $techniques = $fresh->analysis_data['recommendations']['technique_notes'];

    expect(implode(' ', $techniques))->toContain('Deep-plane');
});

test('facelift flags smoker_high_risk for smokers', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', ['q_smoker' => true]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('smoker_high_risk');
});

test('facelift stores estimated_age in recommendations', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', [], [
        '_face_attributes' => [
            'age_range' => ['low' => 48, 'high' => 56, 'midpoint' => 52],
        ],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['estimated_age'])->toBe(52);
});

test('facelift has low confidence when no age data available', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', []);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['confidence'])->toBe('low');
});

test('facelift has medium confidence when age data is present', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', [], [
        '_face_attributes' => [
            'age_range' => ['low' => 44, 'high' => 52, 'midpoint' => 48],
        ],
    ]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['confidence'])->toBe('medium');
});

test('facelift flags revision_facelift for prior surgery', function (): void {
    $tenant     = Tenant::factory()->create();
    $evaluation = makeVisionEvaluation($tenant, 'facelift', ['q_prior_surgery' => true]);

    config(['features.notifications' => false]);
    $fresh = runRecommendations($evaluation);

    expect($fresh->analysis_data['recommendations']['flags'])->toContain('revision_facelift');
});
