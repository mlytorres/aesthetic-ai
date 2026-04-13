<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function intakeTenant(): Tenant
{
    return Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(14),
    ]);
}

function setIntakeTenant(Tenant $tenant): void
{
    app(TenantContext::class)->set($tenant);
}

// ─── intake.evaluation.create rate limiter ─────────────────────────────────

test('intake evaluation create allows 3 requests per 10 minutes per IP', function (): void {
    RateLimiter::clear('intake.evaluation.create');

    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    $payload = ['procedure_slug' => 'rhinoplasty'];

    // Three requests should succeed
    foreach (range(1, 3) as $i) {
        $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
            ->postJson('/intake/evaluations', $payload);

        expect($response->status())->not->toBe(429, "Request {$i} was unexpectedly rate limited");
    }

    // Fourth request should be rate limited
    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson('/intake/evaluations', $payload);

    expect($response->status())->toBe(429);
})->skip(fn () => ! class_exists(App\Models\Tenant::class));

// ─── intake.evaluation.submit rate limiter ────────────────────────────────

test('intake evaluation submit allows 3 requests per hour per IP', function (): void {
    RateLimiter::clear('intake.evaluation.submit');

    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    // Stub Turnstile & Reverb to prevent real network calls
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    // We test the rate limit itself — not whether the evaluation processes correctly
    // so we just confirm the 4th identical request returns 429.
    foreach (range(1, 3) as $i) {
        $this->withHeaders(['X-Clinic-ID' => $tenant->id])
            ->postJson('/intake/evaluations/fake-token/submit', [
                'turnstile_token' => 'test',
                'patient' => ['name' => 'Test', 'email' => "test{$i}@example.com"],
                'consent' => [],
            ]);
        // Status may be 404/422 (fake token) — what matters is it's NOT 429
    }

    // On the 4th attempt it should be rate limited before any processing
    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson('/intake/evaluations/fake-token/submit', [
            'turnstile_token' => 'test',
            'patient' => ['name' => 'Test', 'email' => 'test4@example.com'],
            'consent' => [],
        ]);

    expect($response->status())->toBe(429);
})->skip(fn () => ! class_exists(App\Models\Tenant::class));

// ─── intake.photos rate limiter ───────────────────────────────────────────

test('intake photo upload is rate limited after 15 uploads per token', function (): void {
    RateLimiter::clear('intake.photos');

    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    // First 15 posts should not be rate-limited (they may 422/404 due to missing file, not 429)
    for ($i = 0; $i < 15; $i++) {
        $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
            ->postJson("/intake/evaluations/{$evaluation->secure_token}/photos", []);

        expect($response->status())->not->toBe(429, "Photo request {$i} was unexpectedly rate limited");
    }

    // 16th should hit the rate limit
    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$evaluation->secure_token}/photos", []);

    expect($response->status())->toBe(429);
});

// ─── intake.quiz rate limiter ─────────────────────────────────────────────

test('intake quiz endpoint is rate limited after 30 requests per minute per token', function (): void {
    RateLimiter::clear('intake.quiz');

    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    // 30 requests should succeed (may 422 due to validation — not 429)
    for ($i = 0; $i < 30; $i++) {
        $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
            ->postJson("/intake/evaluations/{$evaluation->secure_token}/quiz", []);

        expect($response->status())->not->toBe(429, "Quiz request {$i} was unexpectedly rate limited");
    }

    // 31st should hit the rate limit
    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$evaluation->secure_token}/quiz", []);

    expect($response->status())->toBe(429);
});

// ─── access-requests rate limiter ────────────────────────────────────────

test('access requests endpoint is rate limited after 5 requests per hour per IP', function (): void {
    RateLimiter::clear('access-requests');

    // 5 requests should pass (may 422 due to validation)
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/access-requests', []);
        expect($response->status())->not->toBe(429, "Access request {$i} was unexpectedly rate limited");
    }

    // 6th should be throttled
    $response = $this->postJson('/access-requests', []);
    expect($response->status())->toBe(429);
});

// ─── Per-email+procedure+tenant 24h cooldown ──────────────────────────────

test('same email cannot submit same procedure twice within 24 hours', function (): void {
    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $email = 'patient@example.com';
    $emailHash = Patient::hashEmail($email);

    // Create an existing evaluation for this email+procedure that was submitted < 24h ago
    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_hash' => $emailHash,
    ]);
    Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_ANALYZING,
        'created_at' => now()->subHours(2),
    ]);

    // Now try to submit a NEW evaluation for the same email+procedure+tenant
    $newPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $newEvaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $newPatient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$newEvaluation->secure_token}/submit", [
            'turnstile_token' => 'test',
            'patient' => [
                'name' => 'Test Patient',
                'email' => $email,
                'phone' => null,
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
        ]);

    expect($response->status())->toBe(429)
        ->and($response->json('message'))->toContain('24 hours');
});

test('same email CAN submit different procedure within 24 hours', function (): void {
    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $email = 'patient@example.com';
    $emailHash = Patient::hashEmail($email);

    // Existing evaluation for rhinoplasty
    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_hash' => $emailHash,
    ]);
    Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_ANALYZING,
        'created_at' => now()->subHours(2),
    ]);

    // Try to submit for a DIFFERENT procedure (brow_lift)
    $newPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $newEvaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $newPatient->id,
        'procedure_slug' => 'brow_lift',
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$newEvaluation->secure_token}/submit", [
            'turnstile_token' => 'test',
            'patient' => [
                'name' => 'Test Patient',
                'email' => $email,
                'phone' => null,
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
        ]);

    // Should NOT be blocked (will 422/500 due to other logic — that's fine)
    expect($response->status())->not->toBe(429);
});

test('same email can resubmit same procedure after 24 hours have passed', function (): void {
    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $email = 'patient@example.com';
    $emailHash = Patient::hashEmail($email);

    // Existing evaluation that is MORE than 24h old
    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_hash' => $emailHash,
    ]);
    Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_ANALYZING,
        'created_at' => now()->subHours(25),
    ]);

    $newPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $newEvaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $newPatient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$newEvaluation->secure_token}/submit", [
            'turnstile_token' => 'test',
            'patient' => [
                'name' => 'Test Patient',
                'email' => $email,
                'phone' => null,
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
        ]);

    // Should NOT be blocked by the 24h cooldown (other errors are acceptable)
    expect($response->status())->not->toBe(429);
});

test('draft evaluations are excluded from 24h cooldown check', function (): void {
    $tenant = intakeTenant();
    setIntakeTenant($tenant);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $email = 'patient@example.com';
    $emailHash = Patient::hashEmail($email);

    // Existing evaluation that is still in DRAFT status (abandoned wizard)
    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_hash' => $emailHash,
    ]);
    Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_DRAFT,
        'created_at' => now()->subHours(1),
    ]);

    $newPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $newEvaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $newPatient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_DRAFT,
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$newEvaluation->secure_token}/submit", [
            'turnstile_token' => 'test',
            'patient' => [
                'name' => 'Test Patient',
                'email' => $email,
                'phone' => null,
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
        ]);

    // Draft statuses should NOT trigger the cooldown
    expect($response->status())->not->toBe(429);
});
