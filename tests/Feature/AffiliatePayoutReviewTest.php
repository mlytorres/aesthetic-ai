<?php

declare(strict_types=1);

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

beforeEach(function (): void {
    $this->withoutMiddleware();
});

function makePayoutLedger(Tenant $tenant, array $overrides = []): AffiliatePayoutLedger
{
    app(TenantContext::class)->set($tenant);

    $partner = AffiliatePartner::create([
        'tenant_id' => $tenant->id,
        'name' => 'Creator One',
        'email' => fake()->unique()->safeEmail(),
        'platform' => AffiliatePartner::PLATFORM_INSTAGRAM,
        'handle' => '@creatorone',
        'status' => AffiliatePartner::STATUS_ACTIVE,
        'payout_cents' => 5000,
        'currency' => 'USD',
        'hold_days' => 14,
    ]);

    $campaign = AffiliateCampaign::create([
        'tenant_id' => $tenant->id,
        'name' => 'Spring Campaign',
        'slug' => 'spring-campaign-'.fake()->numberBetween(100, 999),
        'status' => AffiliateCampaign::STATUS_ACTIVE,
        'default_payout_cents' => 5000,
        'currency' => 'USD',
        'hold_days' => 14,
    ]);

    $link = AffiliateLink::create([
        'tenant_id' => $tenant->id,
        'affiliate_partner_id' => $partner->id,
        'affiliate_campaign_id' => $campaign->id,
        'token' => 'aff_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
        'status' => AffiliateLink::STATUS_ACTIVE,
    ]);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'affiliate_link_id' => $link->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'completed_at' => now()->subDay(),
    ]);

    $event = AttributionEvent::create([
        'tenant_id' => $tenant->id,
        'affiliate_link_id' => $link->id,
        'affiliate_partner_id' => $partner->id,
        'affiliate_campaign_id' => $campaign->id,
        'evaluation_id' => $evaluation->id,
        'event_type' => AttributionEvent::TYPE_EVALUATION_COMPLETED,
        'idempotency_key' => 'evaluation_completed:'.$evaluation->id,
        'occurred_at' => now()->subDay(),
    ]);

    return AffiliatePayoutLedger::create(array_merge([
        'tenant_id' => $tenant->id,
        'affiliate_partner_id' => $partner->id,
        'affiliate_campaign_id' => $campaign->id,
        'affiliate_link_id' => $link->id,
        'attribution_event_id' => $event->id,
        'evaluation_id' => $evaluation->id,
        'status' => AffiliatePayoutLedger::STATUS_PENDING_HOLD,
        'amount_cents' => 5000,
        'currency' => 'USD',
        'hold_until' => now()->addDays(7),
    ], $overrides));
}

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
