<?php

declare(strict_types=1);

use App\Models\AffiliatePayoutLedger;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

beforeEach(function (): void {
    $this->withoutMiddleware();
});

test('clinic admin can approve pending payout', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $ledger = makePayoutLedger($tenant);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.payouts.review', $ledger), [
            'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        ])
        ->assertRedirect();

    expect($ledger->fresh()->status)->toBe(AffiliatePayoutLedger::STATUS_APPROVED);
});

test('approved payout cannot be released before hold expires', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $ledger = makePayoutLedger($tenant, [
        'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        'hold_until' => now()->addDays(2),
    ]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.payouts.release', $ledger))
        ->assertRedirect();

    $ledger->refresh();

    expect($ledger->status)->toBe(AffiliatePayoutLedger::STATUS_APPROVED)
        ->and($ledger->released_at)->toBeNull();
});

test('approved payout can be released after hold expires', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $ledger = makePayoutLedger($tenant, [
        'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        'hold_until' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.affiliates.payouts.release', $ledger))
        ->assertRedirect();

    $ledger->refresh();

    expect($ledger->status)->toBe(AffiliatePayoutLedger::STATUS_RELEASED)
        ->and($ledger->released_at)->not->toBeNull();
});

test('tenant cannot mutate another tenant payout', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    app(TenantContext::class)->set($tenantA);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $ledgerB = makePayoutLedger($tenantB);
    app(TenantContext::class)->set($tenantA);

    $this->actingAs($userA)
        ->withSession(['tenant_id' => $tenantA->id])
        ->withHeaders(['X-Clinic-ID' => $tenantA->id])
        ->patch(route('clinic.affiliates.payouts.review', $ledgerB), [
            'status' => AffiliatePayoutLedger::STATUS_APPROVED,
        ])
        ->assertNotFound();

    expect($ledgerB->fresh()->status)->toBe(AffiliatePayoutLedger::STATUS_PENDING_HOLD);
});
