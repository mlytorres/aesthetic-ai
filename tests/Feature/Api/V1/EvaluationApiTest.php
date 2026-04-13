<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SecureFileService;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => User::ROLE_COORDINATOR,
    ]);
    $this->patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->evaluation = Evaluation::factory()
        ->withAnalysis()
        ->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'quiz_answers' => [
                'timeline' => 'within_3_months',
                'budget_range' => '15000_25000',
                'concerns' => ['tip', 'bridge'],
                'prior_rhinoplasty' => false,
                'breathing_issues' => true,
            ],
        ]);

    // Mock SecureFileService so tests don't hit S3
    $this->mock(SecureFileService::class, function ($mock): void {
        $mock->shouldReceive('getSignedUrl')->andReturn('https://s3.example.com/signed-url');
    });
});

// ─── Authentication ───────────────────────────────────────────────────────────

test('unauthenticated request returns 401', function (): void {
    $this->getJson("/api/v1/evaluations/{$this->evaluation->secure_token}")
        ->assertUnauthorized();
});

test('valid Bearer token authenticates and returns evaluation', function (): void {
    $raw = ApiToken::generateRaw();
    ApiToken::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Zapier Token',
        'token_hash' => $raw['hash'],
        'scopes' => ['evaluations:read', 'phi:read'],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$raw['raw'])
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$this->evaluation->secure_token}")
        ->assertOk()
        ->assertJsonPath('data.evaluation_token', $this->evaluation->secure_token);
});

test('invalid Bearer token returns 401', function (): void {
    $this->withHeader('Authorization', 'Bearer aai_live_totallyfaketoken')
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$this->evaluation->secure_token}")
        ->assertUnauthorized();
});

test('authenticated user without X-Clinic-ID resolves tenant from session', function (): void {
    // TenantMiddleware falls back to the user's own tenant_id when X-Clinic-ID is absent.
    // This covers clinic dashboard users calling the API directly.
    $this->actingAs($this->user)
        ->getJson("/api/v1/evaluations/{$this->evaluation->secure_token}")
        ->assertOk()
        ->assertJsonPath('data.evaluation_token', $this->evaluation->secure_token);
});

// ─── Happy path ───────────────────────────────────────────────────────────────

test('returns evaluation with patient PHI by secure token', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$this->evaluation->secure_token}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'evaluation_token',
                'procedure_interest',
                'status',
                'lead_score',
                'priority',
                'ready_for_call',
                'ai_analysis_complete',
                'created_at',
                'patient' => ['id', 'name', 'email', 'phone'],
                'quiz_summary' => ['timeline', 'budget_range', 'concerns'],
                'ai_analysis',
                'photos',
            ],
        ]);

    expect($response->json('data.evaluation_token'))->toBe($this->evaluation->secure_token)
        ->and($response->json('data.procedure_interest'))->toBe('rhinoplasty')
        ->and($response->json('data.ai_analysis_complete'))->toBeTrue()
        ->and($response->json('data.quiz_summary.timeline'))->toBe('within_3_months')
        ->and($response->json('data.quiz_summary.budget_range'))->toBe('15000_25000')
        ->and($response->json('data.patient.name'))->toBe($this->patient->name_encrypted)
        ->and($response->json('data.patient.email'))->toBe($this->patient->email_encrypted);
});

test('ready_for_call is true for high-priority completed evaluations', function (): void {
    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'priority' => Evaluation::PRIORITY_HIGH,
        'lead_score' => 87,
    ]);

    $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$evaluation->secure_token}")
        ->assertOk()
        ->assertJsonPath('data.ready_for_call', true);
});

test('ready_for_call is false for standard-priority evaluations', function (): void {
    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'priority' => Evaluation::PRIORITY_STANDARD,
        'lead_score' => 42,
    ]);

    $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$evaluation->secure_token}")
        ->assertOk()
        ->assertJsonPath('data.ready_for_call', false);
});

test('ai_analysis is null when AI pipeline has not completed', function (): void {
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'status' => Evaluation::STATUS_ANALYZING,
        'lead_score' => null,
    ]);

    $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$evaluation->secure_token}")
        ->assertOk()
        ->assertJsonPath('data.ai_analysis_complete', false)
        ->assertJsonPath('data.ai_analysis', null);
});

// ─── Tenant isolation ─────────────────────────────────────────────────────────

test('cannot access evaluation belonging to a different tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherEvaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    // Authenticated as our tenant's user, but evaluation belongs to another tenant
    $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson("/api/v1/evaluations/{$otherEvaluation->secure_token}")
        ->assertNotFound();
});

// ─── Not found ───────────────────────────────────────────────────────────────

test('returns 404 for unknown evaluation token', function (): void {
    $this->actingAs($this->user)
        ->withHeader('X-Clinic-ID', $this->tenant->id)
        ->getJson('/api/v1/evaluations/totally-fake-token-abc123')
        ->assertNotFound();
});
