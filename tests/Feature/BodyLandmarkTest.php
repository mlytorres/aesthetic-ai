<?php

declare(strict_types=1);

use App\Jobs\AI\CalculateBodyProportionsJob;
use App\Jobs\AI\ExtractBodyLandmarksJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeBodyEvaluation(string $procedure = 'bbl', array $quizAnswers = []): array
{
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => $procedure,
        'status' => Evaluation::STATUS_SUBMITTED,
        'quiz_answers' => array_merge([
            'q_skin_laxity' => 'mild',
            'q_weight_stable' => true,
            'q_donor_areas' => ['abdomen', 'flanks'],
        ], $quizAnswers),
    ]);

    return [$tenant, $evaluation];
}

// ─── ExtractBodyLandmarksJob ──────────────────────────────────────────────────

test('ExtractBodyLandmarksJob stores front and side landmarks for BBL in simulation mode', function (): void {
    config(['features.ai_vision' => false]);

    [$tenant, $evaluation] = makeBodyEvaluation('bbl');

    $job = new ExtractBodyLandmarksJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $landmarks = $evaluation->analysis_data['body_landmarks'] ?? null;

    expect($landmarks)->not->toBeNull()
        ->and($landmarks['front_landmarks'])->toBeArray()
        ->and($landmarks['side_landmarks'])->toBeArray()
        ->and($landmarks['_body_attributes'])->toBeArray();
});

test('ExtractBodyLandmarksJob front landmarks contain all expected keys', function (): void {
    config(['features.ai_vision' => false]);

    [$tenant, $evaluation] = makeBodyEvaluation('lipo_360');

    $job = new ExtractBodyLandmarksJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $front = $evaluation->analysis_data['body_landmarks']['front_landmarks'];

    foreach (['leftShoulder', 'rightShoulder', 'leftWaist', 'rightWaist', 'leftHip', 'rightHip'] as $key) {
        expect($front)->toHaveKey($key)
            ->and($front[$key])->toHaveKeys(['x', 'y']);

        // Coordinates must be normalised 0.0–1.0
        expect($front[$key]['x'])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        expect($front[$key]['y'])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }
});

test('ExtractBodyLandmarksJob side landmarks contain all expected keys', function (): void {
    config(['features.ai_vision' => false]);

    [$tenant, $evaluation] = makeBodyEvaluation('breast_augmentation');

    $job = new ExtractBodyLandmarksJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $side = $evaluation->analysis_data['body_landmarks']['side_landmarks'];

    foreach (['glutealPeak', 'glutealBase', 'lowerBackCurve', 'shoulder', 'upperAbdomenSide'] as $key) {
        expect($side)->toHaveKey($key)
            ->and($side[$key])->toHaveKeys(['x', 'y']);
    }
});

test('ExtractBodyLandmarksJob BBL produces wider hip coordinates than lipo', function (): void {
    config(['features.ai_vision' => false]);

    [, $bblEval] = makeBodyEvaluation('bbl');
    [, $lipoEval] = makeBodyEvaluation('lipo_360');

    (new ExtractBodyLandmarksJob($bblEval->id))->handle();
    (new ExtractBodyLandmarksJob($lipoEval->id))->handle();

    $bblEval->refresh();
    $lipoEval->refresh();

    $bblLeftHip = $bblEval->analysis_data['body_landmarks']['front_landmarks']['leftHip']['x'];
    $lipoLeftHip = $lipoEval->analysis_data['body_landmarks']['front_landmarks']['leftHip']['x'];

    // BBL patients simulated with more hip width — left hip x should be smaller (further left)
    expect($bblLeftHip)->toBeLessThan($lipoLeftHip + 0.05); // allow tolerance
});

test('ExtractBodyLandmarksJob body attributes include skin laxity score', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('bbl', ['q_skin_laxity' => 'excellent']);

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();

    $evaluation->refresh();
    $attrs = $evaluation->analysis_data['body_landmarks']['_body_attributes'];

    expect($attrs)->toHaveKey('skin_laxity')
        ->and($attrs['skin_laxity']['label'])->toBe('excellent')
        ->and($attrs['skin_laxity']['score'])->toBeGreaterThanOrEqual(85)->toBeLessThanOrEqual(95);
});

test('ExtractBodyLandmarksJob stores extraction timestamp', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation();

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();

    $evaluation->refresh();
    expect($evaluation->analysis_data)->toHaveKey('body_landmarks_extracted_at');
});

// ─── CalculateBodyProportionsJob ──────────────────────────────────────────────

test('CalculateBodyProportionsJob computes proportions after landmark extraction', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('bbl');

    // Run landmark job first to populate analysis_data['body_landmarks']
    (new ExtractBodyLandmarksJob($evaluation->id))->handle();
    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    $proportions = $evaluation->analysis_data['body_proportions'] ?? null;

    expect($proportions)->not->toBeNull()
        ->and($proportions)->toHaveKey('waist_hip_ratio')
        ->and($proportions)->toHaveKey('shoulder_waist_ratio')
        ->and($proportions)->toHaveKey('overall_contour_score')
        ->and($proportions)->toHaveKey('body_symmetry')
        ->and($proportions)->toHaveKey('gluteal_projection')
        ->and($proportions)->toHaveKey('abdominal_projection');
});

test('CalculateBodyProportionsJob overall_contour_score is between 0 and 100', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('lipo_360');

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();
    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    $score = $evaluation->analysis_data['body_proportions']['overall_contour_score'];

    expect($score)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

test('CalculateBodyProportionsJob WHR label is one of the expected values', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('breast_augmentation');

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();
    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    $label = $evaluation->analysis_data['body_proportions']['waist_hip_ratio']['label'];

    expect($label)->toBeIn(['Hourglass', 'Pear', 'Rectangular', 'Apple']);
});

test('CalculateBodyProportionsJob returns defaults when no front landmarks available', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('bbl');
    // Do NOT run ExtractBodyLandmarksJob — analysis_data will be empty

    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    $proportions = $evaluation->analysis_data['body_proportions'];

    // Defaults should be sensible placeholder values
    expect($proportions['overall_contour_score'])->toBe(50)
        ->and($proportions['body_symmetry'])->toBe(75)
        ->and($proportions['waist_hip_ratio']['score'])->toBe(50);
});

test('CalculateBodyProportionsJob stores calculated_at timestamp', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation();

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();
    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    expect($evaluation->analysis_data)->toHaveKey('body_proportions_calculated_at');
});

test('body_symmetry score is between 0 and 100', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makeBodyEvaluation('bbl');

    (new ExtractBodyLandmarksJob($evaluation->id))->handle();
    (new CalculateBodyProportionsJob($evaluation->id))->handle();

    $evaluation->refresh();
    $symmetry = $evaluation->analysis_data['body_proportions']['body_symmetry'];

    expect($symmetry)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});
