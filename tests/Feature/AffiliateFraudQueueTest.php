<?php

declare(strict_types=1);

use App\Models\AffiliatePayoutLedger;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

beforeEach(function (): void {
    $this->withoutMiddleware();
});

function makeFlaggedPayout(Tenant $tenant, array $overrides = []): AffiliatePayoutLedger
{
    return makePayoutLedger($tenant, array_merge([
        'fraud_review_required' => true,
        'metadata' => [
            'rule' => 'qualified_evaluation',
            'fraud_flags' => ['duplicate_ip_24h', 'high_velocity_24h'],
            'fraud_risk' => 'high',
        ],
    ], $overrides));
}

// ── Index ────────────────────────────────────────────────────────────────────

test('fraud queue index returns only fraud_review_required payouts for the tenant', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $flagged = makeFlaggedPayout($tenant);
    makePayoutLedger($tenant, ['fraud_review_required' => false]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('clinic.affiliates.fraud-queue.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clinic/affiliate-fraud-queue')
            ->where('flaggedPayouts.0.id', $flagged->id)
            ->where('stats.total_flagged', 1)
            ->where('stats.pending_review', 1)
        );
});

test('fraud queue enforces tenant isolation', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app(TenantContext::class)->set($tenantB);
    $flaggedB = makeFlaggedPayout($tenantB);

    app(TenantContext::class)->set($tenantA);
    $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_ADMIN]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenantA->id])
        ->get(route('clinic.affiliates.fraud-queue.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_flagged', 0)
        );

    expect($flaggedB->fresh()->fraud_reviewed_at)->toBeNull();
});

// ── Clear ────────────────────────────────────────────────────────────────────

test('clearing a flagged payout sets fraud_reviewed_at and leaves payout in pending_hold', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $flagged = makeFlaggedPayout($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.fraud-queue.clear', $flagged))
        ->assertRedirect();

    $flagged->refresh();

    expect($flagged->fraud_reviewed_at)->not->toBeNull()
        ->and($flagged->fraud_reviewed_by_user_id)->toBe($user->id)
        ->and($flagged->status)->toBe(AffiliatePayoutLedger::STATUS_PENDING_HOLD);
});

test('clearing an already-reviewed payout returns a flash error', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $flagged = makeFlaggedPayout($tenant, ['fraud_reviewed_at' => now()]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.fraud-queue.clear', $flagged));

    $response->assertRedirect();
    expect(session('flash.error'))->not->toBeNull();
});

// ── Reject ───────────────────────────────────────────────────────────────────

test('rejecting a flagged payout marks it rejected and sets fraud_reviewed_at', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $flagged = makeFlaggedPayout($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.fraud-queue.reject', $flagged))
        ->assertRedirect();

    $flagged->refresh();

    expect($flagged->status)->toBe(AffiliatePayoutLedger::STATUS_REJECTED)
        ->and($flagged->fraud_reviewed_at)->not->toBeNull()
        ->and($flagged->rejection_reason)->toContain('fraud');
});

test('rejecting an already-reviewed payout returns a flash error', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $flagged = makeFlaggedPayout($tenant, ['fraud_reviewed_at' => now()]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.fraud-queue.reject', $flagged));

    $response->assertRedirect();
    expect(session('flash.error'))->not->toBeNull();
});

// ── Release enforcement ───────────────────────────────────────────────────────

test('approved payout with pending fraud review cannot be released', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $flagged = makeFlaggedPayout($tenant, [
        'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        'hold_until' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.payouts.release', $flagged));

    $response->assertRedirect();
    expect(session('flash.error'))->not->toBeNull()
        ->and($flagged->fresh()->status)->toBe(AffiliatePayoutLedger::STATUS_APPROVED);
});

test('approved payout with cleared fraud review can be released after hold expires', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $flagged = makeFlaggedPayout($tenant, [
        'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        'hold_until' => now()->subDay(),
        'fraud_reviewed_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.payouts.release', $flagged))
        ->assertRedirect();

    expect($flagged->fresh()->status)->toBe(AffiliatePayoutLedger::STATUS_RELEASED);
});

// ── 404 guard ─────────────────────────────────────────────────────────────────

test('clear endpoint returns 404 for a payout that is not fraud_review_required', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $clean = makePayoutLedger($tenant, ['fraud_review_required' => false]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.fraud-queue.clear', $clean))
        ->assertNotFound();
});
