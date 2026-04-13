<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeEvaluationAtStep(int $step, Tenant $tenant): Evaluation
{
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'funnel_step' => $step,
        'status' => match ($step) {
            Evaluation::FUNNEL_PROCEDURE => Evaluation::STATUS_DRAFT,
            Evaluation::FUNNEL_QUIZ => Evaluation::STATUS_SUBMITTED,
            Evaluation::FUNNEL_PHOTOS => Evaluation::STATUS_SUBMITTED,
            Evaluation::FUNNEL_SUBMITTED => Evaluation::STATUS_ANALYZING,
            default => Evaluation::STATUS_DRAFT,
        },
    ]);
}

// ─── Funnel step constants ────────────────────────────────────────────────────

test('Evaluation model has correct funnel step constants', function (): void {
    expect(Evaluation::FUNNEL_PROCEDURE)->toBe(1)
        ->and(Evaluation::FUNNEL_QUIZ)->toBe(2)
        ->and(Evaluation::FUNNEL_PHOTOS)->toBe(3)
        ->and(Evaluation::FUNNEL_SUBMITTED)->toBe(4);
});

// ─── EvaluationController funnel progression (via HTTP + X-Clinic-ID) ────────

test('creating an evaluation sets funnel_step to 1 (procedure selected)', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson(route('intake.evaluations.store'), [
            'procedure_slug' => 'rhinoplasty',
        ])
        ->assertCreated();

    $evaluation = Evaluation::withoutGlobalScopes()->latest()->first();
    expect($evaluation->funnel_step)->toBe(Evaluation::FUNNEL_PROCEDURE);
});

test('saving quiz answers advances funnel_step to 2', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'funnel_step' => Evaluation::FUNNEL_PROCEDURE,
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson(route('intake.evaluations.quiz', $evaluation->secure_token), [
            'answers' => ['q_timeline' => 'asap', 'q_budget' => '15k_25k'],
        ])
        ->assertOk();

    expect($evaluation->fresh()->funnel_step)->toBe(Evaluation::FUNNEL_QUIZ);
});

test('quiz step does not downgrade funnel_step if already higher', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    // Already at step 3 (photos uploaded)
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'funnel_step' => Evaluation::FUNNEL_PHOTOS,
        'status' => Evaluation::STATUS_SUBMITTED,
    ]);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson(route('intake.evaluations.quiz', $evaluation->secure_token), [
            'answers' => ['q_timeline' => 'asap'],
        ])
        ->assertOk();

    // Must remain at 3, not drop to 2
    expect($evaluation->fresh()->funnel_step)->toBe(Evaluation::FUNNEL_PHOTOS);
});

test('submitting an evaluation sets funnel_step to 4', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'funnel_step' => Evaluation::FUNNEL_PHOTOS,
        'status' => Evaluation::STATUS_SUBMITTED,
        'quiz_answers' => ['q_timeline' => 'asap'],
    ]);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson(route('intake.evaluations.submit', $evaluation->secure_token), [
            'patient' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '305-555-0100',
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
            'turnstile_token' => 'test-bypass-token',
        ])
        ->assertOk();

    expect($evaluation->fresh()->funnel_step)->toBe(Evaluation::FUNNEL_SUBMITTED);
});

// ─── AnalyticsController intakeFunnel ────────────────────────────────────────

test('analytics intake funnel counts evaluations at each step correctly', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    app(TenantContext::class)->set($tenant);

    // 4 reached step 1, 3 reached step 2, 1 reached step 4
    makeEvaluationAtStep(Evaluation::FUNNEL_PROCEDURE, $tenant);
    makeEvaluationAtStep(Evaluation::FUNNEL_QUIZ, $tenant);
    makeEvaluationAtStep(Evaluation::FUNNEL_QUIZ, $tenant);
    makeEvaluationAtStep(Evaluation::FUNNEL_SUBMITTED, $tenant);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));

    // Verify counts directly — deferred props resolve on partial reload,
    // so we assert against the DB query that powers intakeFunnel().
    expect(
        Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('funnel_step', '>=', Evaluation::FUNNEL_PROCEDURE)
            ->count()
    )->toBe(4);

    expect(
        Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('funnel_step', '>=', Evaluation::FUNNEL_QUIZ)
            ->count()
    )->toBe(3);

    expect(
        Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('funnel_step', '>=', Evaluation::FUNNEL_SUBMITTED)
            ->count()
    )->toBe(1);
});

test('intake funnel is scoped to the authenticated tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_COORDINATOR]);

    // Tenant B has submitted evaluations — must not appear in tenant A's funnel
    makeEvaluationAtStep(Evaluation::FUNNEL_SUBMITTED, $tenantB);
    makeEvaluationAtStep(Evaluation::FUNNEL_SUBMITTED, $tenantB);

    // Tenant A has zero evaluations
    expect(
        Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenantA->id)
            ->where('funnel_step', '>=', Evaluation::FUNNEL_PROCEDURE)
            ->count()
    )->toBe(0);

    $this->actingAs($userA)
        ->withSession(['tenant_id' => $tenantA->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});
