<?php

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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
