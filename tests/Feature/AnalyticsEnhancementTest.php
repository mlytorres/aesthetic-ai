<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeAnalyticsTenant(): array
{
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    return [$tenant, $user];
}

function createEval(Tenant $tenant, array $attrs = []): Evaluation
{
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create(array_merge([
        'tenant_id'      => $tenant->id,
        'patient_id'     => $patient->id,
        'status'         => Evaluation::STATUS_COMPLETE,
        'procedure_slug' => 'rhinoplasty',
        'lead_score'     => 70,
    ], $attrs));
}

/**
 * Call a private method on AnalyticsController directly via Reflection.
 * TenantContext must be set before calling.
 */
function callAnalyticsMethod(string $method): mixed
{
    $controller = app(AnalyticsController::class);
    $ref        = new ReflectionMethod(AnalyticsController::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($controller);
}

// ─── monthOverMonth ───────────────────────────────────────────────────────────

test('analytics index page renders with new deferred props', function (): void {
    [$tenant, $user] = makeAnalyticsTenant();

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});

test('monthOverMonth returns required structure keys', function (): void {
    [$tenant] = makeAnalyticsTenant();
    app(TenantContext::class)->set($tenant);

    $result = callAnalyticsMethod('monthOverMonth');

    expect($result)->toHaveKeys(['current_month', 'previous_month', 'evaluations', 'avg_score', 'booked'])
        ->and($result['evaluations'])->toHaveKeys(['current', 'previous', 'delta'])
        ->and($result['avg_score'])->toHaveKeys(['current', 'previous', 'delta'])
        ->and($result['booked'])->toHaveKeys(['current', 'previous', 'delta']);
});

test('monthOverMonth counts current month evaluations correctly', function (): void {
    [$tenant] = makeAnalyticsTenant();

    // 2 this month, 1 last month
    createEval($tenant, ['created_at' => now()]);
    createEval($tenant, ['created_at' => now()]);
    createEval($tenant, ['created_at' => now()->subMonth()]);

    app(TenantContext::class)->set($tenant);
    $result = callAnalyticsMethod('monthOverMonth');

    expect($result['evaluations']['current'])->toBe(2)
        ->and($result['evaluations']['previous'])->toBe(1)
        ->and($result['evaluations']['delta'])->toBe(1);
});

test('monthOverMonth excludes draft evaluations', function (): void {
    [$tenant] = makeAnalyticsTenant();

    createEval($tenant, ['status' => Evaluation::STATUS_DRAFT, 'created_at' => now()]);
    createEval($tenant, ['status' => Evaluation::STATUS_COMPLETE, 'created_at' => now()]);

    app(TenantContext::class)->set($tenant);
    $result = callAnalyticsMethod('monthOverMonth');

    expect($result['evaluations']['current'])->toBe(1);
});

test('monthOverMonth tracks bookings per month', function (): void {
    [$tenant] = makeAnalyticsTenant();

    createEval($tenant, ['status' => Evaluation::STATUS_BOOKED, 'created_at' => now()]);
    createEval($tenant, ['status' => Evaluation::STATUS_COMPLETE, 'created_at' => now()]);

    app(TenantContext::class)->set($tenant);
    $result = callAnalyticsMethod('monthOverMonth');

    expect($result['booked']['current'])->toBe(1);
});

test('monthOverMonth delta is negative when current is lower than previous', function (): void {
    [$tenant] = makeAnalyticsTenant();

    // 0 this month, 3 last month
    createEval($tenant, ['created_at' => now()->subMonth()]);
    createEval($tenant, ['created_at' => now()->subMonth()]);
    createEval($tenant, ['created_at' => now()->subMonth()]);

    app(TenantContext::class)->set($tenant);
    $result = callAnalyticsMethod('monthOverMonth');

    expect($result['evaluations']['delta'])->toBe(-3);
});

// ─── procedureMix ─────────────────────────────────────────────────────────────

test('procedureMix returns a row per procedure slug', function (): void {
    [$tenant] = makeAnalyticsTenant();

    createEval($tenant, ['procedure_slug' => 'rhinoplasty']);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty']);
    createEval($tenant, ['procedure_slug' => 'bbl']);

    app(TenantContext::class)->set($tenant);
    $mix = callAnalyticsMethod('procedureMix');

    $procedures = array_column($mix, 'procedure');

    expect($procedures)->toContain('rhinoplasty')
        ->and($procedures)->toContain('bbl');
});

test('procedureMix calculates booking rate correctly', function (): void {
    [$tenant] = makeAnalyticsTenant();

    // 4 rhinoplasty: 2 booked → 50%
    createEval($tenant, ['procedure_slug' => 'rhinoplasty', 'status' => Evaluation::STATUS_BOOKED]);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty', 'status' => Evaluation::STATUS_BOOKED]);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty', 'status' => Evaluation::STATUS_COMPLETE]);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty', 'status' => Evaluation::STATUS_COMPLETE]);

    app(TenantContext::class)->set($tenant);
    $mix   = callAnalyticsMethod('procedureMix');
    $rhino = collect($mix)->firstWhere('procedure', 'rhinoplasty');

    expect($rhino['count'])->toBe(4)
        ->and($rhino['booked'])->toBe(2)
        ->and($rhino['booking_rate'])->toBe(50.0);
});

test('procedureMix is ordered by volume descending', function (): void {
    [$tenant] = makeAnalyticsTenant();

    createEval($tenant, ['procedure_slug' => 'bbl']);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty']);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty']);
    createEval($tenant, ['procedure_slug' => 'rhinoplasty']);

    app(TenantContext::class)->set($tenant);
    $mix = callAnalyticsMethod('procedureMix');

    expect($mix[0]['procedure'])->toBe('rhinoplasty')
        ->and($mix[1]['procedure'])->toBe('bbl');
});

test('procedureMix scopes to tenant', function (): void {
    [$tenantA] = makeAnalyticsTenant();
    [$tenantB] = makeAnalyticsTenant();

    createEval($tenantA, ['procedure_slug' => 'rhinoplasty']);
    createEval($tenantB, ['procedure_slug' => 'facelift']);

    app(TenantContext::class)->set($tenantA);
    $mix        = callAnalyticsMethod('procedureMix');
    $procedures = array_column($mix, 'procedure');

    expect($procedures)->toContain('rhinoplasty')
        ->and($procedures)->not->toContain('facelift');
});

test('procedureMix includes human-readable label', function (): void {
    [$tenant] = makeAnalyticsTenant();

    createEval($tenant, ['procedure_slug' => 'lipo_360']);

    app(TenantContext::class)->set($tenant);
    $mix  = callAnalyticsMethod('procedureMix');
    $lipo = collect($mix)->firstWhere('procedure', 'lipo_360');

    expect($lipo['label'])->toBe('Lipo 360°');
});

// ─── scoreVsBooking ───────────────────────────────────────────────────────────

test('scoreVsBooking returns 5 buckets', function (): void {
    [$tenant] = makeAnalyticsTenant();
    app(TenantContext::class)->set($tenant);

    $svb = callAnalyticsMethod('scoreVsBooking');

    expect($svb)->toHaveCount(5);
});

test('scoreVsBooking bucket labels are correct', function (): void {
    [$tenant] = makeAnalyticsTenant();
    app(TenantContext::class)->set($tenant);

    $svb     = callAnalyticsMethod('scoreVsBooking');
    $buckets = array_column($svb, 'bucket');

    expect($buckets)->toBe(['0–19', '20–39', '40–59', '60–79', '80–100']);
});

test('scoreVsBooking calculates booking rate per bucket', function (): void {
    [$tenant] = makeAnalyticsTenant();

    // 80–100 bucket: 2 total, 1 booked → 50%
    createEval($tenant, ['lead_score' => 85, 'status' => Evaluation::STATUS_BOOKED]);
    createEval($tenant, ['lead_score' => 90, 'status' => Evaluation::STATUS_COMPLETE]);

    // 0–19 bucket: 1 total, 0 booked → 0%
    createEval($tenant, ['lead_score' => 10, 'status' => Evaluation::STATUS_COMPLETE]);

    app(TenantContext::class)->set($tenant);
    $svb       = callAnalyticsMethod('scoreVsBooking');
    $highBkt   = collect($svb)->firstWhere('bucket', '80–100');
    $lowBkt    = collect($svb)->firstWhere('bucket', '0–19');

    expect($highBkt['total'])->toBe(2)
        ->and($highBkt['booked'])->toBe(1)
        ->and($highBkt['booking_rate'])->toBe(50.0)
        ->and($lowBkt['total'])->toBe(1)
        ->and($lowBkt['booking_rate'])->toBe(0.0);
});

test('scoreVsBooking returns zero booking rate for empty buckets', function (): void {
    [$tenant] = makeAnalyticsTenant();
    app(TenantContext::class)->set($tenant);

    $svb = callAnalyticsMethod('scoreVsBooking');

    foreach ($svb as $row) {
        expect($row['booking_rate'])->toBe(0.0);
    }
});
