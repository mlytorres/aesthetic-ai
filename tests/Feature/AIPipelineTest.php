<?php

declare(strict_types=1);

use App\Jobs\AI\CalculateProportionsJob;
use App\Jobs\AI\ExtractFacialLandmarksJob;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Jobs\AI\NotifyClinicNewEvaluationJob;
use App\Jobs\AI\ValidatePhotoQualityJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\SecureFileService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a tenant + evaluation + photos ready for the AI pipeline.
 * TenantContext is NOT set — the jobs set it themselves via ResolvesJobTenant.
 */
function makePipelineEvaluation(int $photoCount = 3): array
{
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_SUBMITTED,
        'quiz_answers' => [
            'q_timeline' => 'asap',
            'q_budget' => '15k_25k',
            'q_concerns' => ['tip', 'bridge'],
            'q_referral' => 'google',
            'q_prior_surgery' => false,
            'q_breathing' => false,
        ],
    ]);

    $photos = collect();
    $types = [Photo::TYPE_FRONT, Photo::TYPE_LEFT_PROFILE, Photo::TYPE_RIGHT_PROFILE];

    for ($i = 0; $i < min($photoCount, 3); $i++) {
        $photos->push(Photo::factory()->create([
            'tenant_id' => $tenant->id,
            'evaluation_id' => $evaluation->id,
            'type' => $types[$i],
        ]));
    }

    return [$tenant, $evaluation, $photos];
}

// ─── ValidatePhotoQualityJob ──────────────────────────────────────────────────

test('ValidatePhotoQualityJob assigns quality scores to all photos in simulation mode', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation, $photos] = makePipelineEvaluation(3);

    $job = new ValidatePhotoQualityJob($evaluation->id);
    $job->handle(app(SecureFileService::class), app(AuditLog::class));

    foreach ($photos as $photo) {
        $photo->refresh();
        expect($photo->quality_score)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        expect($photo->analysis_status)->toBe(Photo::ANALYSIS_COMPLETE);
    }
});

test('ValidatePhotoQualityJob marks evaluation failed when no photos exist', function (): void {
    config(['features.ai_vision' => false]);

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_SUBMITTED,
    ]);
    // No photos created

    $job = new ValidatePhotoQualityJob($evaluation->id);
    $job->handle(app(SecureFileService::class), app(AuditLog::class));

    expect($evaluation->fresh()->status)->toBe(Evaluation::STATUS_FAILED);
});

test('ValidatePhotoQualityJob sets TenantContext via ResolvesJobTenant', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation] = makePipelineEvaluation(3);

    // TenantContext is NOT set before calling handle — the job must set it itself
    $tenantContext = app(TenantContext::class);
    $tenantContext->clear();
    expect($tenantContext->isSet())->toBeFalse();

    $job = new ValidatePhotoQualityJob($evaluation->id);
    $job->handle(app(SecureFileService::class), app(AuditLog::class));

    expect($tenantContext->isSet())->toBeTrue();
});

// ─── CalculateProportionsJob ──────────────────────────────────────────────────

test('CalculateProportionsJob writes proportions into analysis_data', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation, $photos] = makePipelineEvaluation(3);

    // Seed the quality scores ValidatePhotoQualityJob would have set
    foreach ($photos as $photo) {
        $photo->update(['quality_score' => 75, 'analysis_status' => Photo::ANALYSIS_COMPLETE]);
    }

    $job = new CalculateProportionsJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $proportions = $evaluation->analysis_data['proportions'] ?? null;

    expect($proportions)->toBeArray();
    expect($proportions)->toHaveKey('overall_harmony');
    expect($proportions)->toHaveKey('facial_thirds');
    expect($proportions)->toHaveKey('nasal_symmetry');
    expect($proportions['overall_harmony'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

test('CalculateProportionsJob includes average photo quality in proportions', function (): void {
    config(['features.ai_vision' => false]);

    [, $evaluation, $photos] = makePipelineEvaluation(3);

    foreach ($photos as $photo) {
        $photo->update(['quality_score' => 80, 'analysis_status' => Photo::ANALYSIS_COMPLETE]);
    }

    $job = new CalculateProportionsJob($evaluation->id);
    $job->handle();

    $evaluation->refresh();
    $avgQuality = $evaluation->analysis_data['proportions']['_avg_photo_quality'] ?? null;

    expect($avgQuality)->toBe(80);
});

// ─── GenerateBasicRecommendationsJob ─────────────────────────────────────────

test('GenerateBasicRecommendationsJob marks evaluation complete with lead score', function (): void {
    config(['features.ai_vision' => false]);
    Bus::fake([NotifyClinicNewEvaluationJob::class]);

    [, $evaluation] = makePipelineEvaluation(3);

    // Seed proportions that GenerateBasicRecommendationsJob reads
    $evaluation->update([
        'analysis_data' => [
            'proportions' => [
                'overall_harmony' => 72,
                'facial_thirds' => ['upper' => 33.3, 'middle' => 33.3, 'lower' => 33.4],
                'nasal_symmetry' => ['deviation_mm' => 1.0, 'symmetry_score' => 90],
                '_avg_photo_quality' => 75,
            ],
        ],
    ]);

    $job = new GenerateBasicRecommendationsJob($evaluation->id);
    $job->handle(app(LeadScoringService::class), app(AuditLog::class));

    $evaluation->refresh();

    expect($evaluation->status)->toBe(Evaluation::STATUS_COMPLETE);
    expect($evaluation->lead_score)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($evaluation->priority)->toBeIn([
        Evaluation::PRIORITY_URGENT,
        Evaluation::PRIORITY_HIGH,
        Evaluation::PRIORITY_MEDIUM,
        Evaluation::PRIORITY_STANDARD,
    ]);
});

test('GenerateBasicRecommendationsJob adds recommendations to analysis_data', function (): void {
    config(['features.ai_vision' => false]);
    Bus::fake([NotifyClinicNewEvaluationJob::class]);

    [, $evaluation] = makePipelineEvaluation();

    $evaluation->update([
        'analysis_data' => [
            'proportions' => ['overall_harmony' => 65, '_avg_photo_quality' => 70],
        ],
    ]);

    $job = new GenerateBasicRecommendationsJob($evaluation->id);
    $job->handle(app(LeadScoringService::class), app(AuditLog::class));

    $evaluation->refresh();
    $recommendations = $evaluation->analysis_data['recommendations'] ?? null;

    expect($recommendations)->toBeArray()->not->toBeEmpty();
    expect($recommendations)->toHaveKey('procedure');
    expect($recommendations)->toHaveKey('confidence');
});

test('GenerateBasicRecommendationsJob dispatches notification job on completion', function (): void {
    config(['features.ai_vision' => false]);
    Bus::fake([NotifyClinicNewEvaluationJob::class]);

    [, $evaluation] = makePipelineEvaluation();

    $evaluation->update([
        'analysis_data' => [
            'proportions' => ['overall_harmony' => 65, '_avg_photo_quality' => 70],
        ],
    ]);

    $job = new GenerateBasicRecommendationsJob($evaluation->id);
    $job->handle(app(LeadScoringService::class), app(AuditLog::class));

    Bus::assertDispatched(NotifyClinicNewEvaluationJob::class, function ($job) use ($evaluation): bool {
        return $job->evaluationId === $evaluation->id;
    });
});

// ─── Full pipeline integration ────────────────────────────────────────────────

test('full AI pipeline transforms submitted evaluation to complete with score', function (): void {
    config(['features.ai_vision' => false]);
    Bus::fake([NotifyClinicNewEvaluationJob::class]);

    [, $evaluation, $photos] = makePipelineEvaluation(3);

    expect($evaluation->status)->toBe(Evaluation::STATUS_SUBMITTED);

    // Run all four jobs in sequence (mirrors Bus::chain order)
    (new ValidatePhotoQualityJob($evaluation->id))
        ->handle(app(SecureFileService::class), app(AuditLog::class));

    (new ExtractFacialLandmarksJob($evaluation->id))->handle();

    (new CalculateProportionsJob($evaluation->id))->handle();

    (new GenerateBasicRecommendationsJob($evaluation->id))
        ->handle(app(LeadScoringService::class), app(AuditLog::class));

    $evaluation->refresh();

    expect($evaluation->status)->toBe(Evaluation::STATUS_COMPLETE);
    expect($evaluation->lead_score)->toBeInt()->toBeGreaterThan(0);
    expect($evaluation->priority)->not->toBeNull();
    expect($evaluation->analysis_data)->toHaveKey('proportions');
    expect($evaluation->analysis_data)->toHaveKey('recommendations');
    expect($evaluation->analysis_data)->toHaveKey('landmarks');
});
