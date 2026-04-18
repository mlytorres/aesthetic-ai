<?php

declare(strict_types=1);

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliateTermsAcceptance;
use App\Models\CampaignAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\AffiliateTerms;

function makeAffiliatePortalSeed(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);

    $partner = AffiliatePartner::create([
        'tenant_id' => $tenant->id,
        'name' => 'Influencer One',
        'email' => 'influencer1@example.com',
        'platform' => AffiliatePartner::PLATFORM_INSTAGRAM,
        'handle' => '@influencerone',
        'status' => AffiliatePartner::STATUS_ACTIVE,
        'payout_cents' => 5000,
        'currency' => 'USD',
        'hold_days' => 14,
    ]);

    $campaign = AffiliateCampaign::create([
        'tenant_id' => $tenant->id,
        'name' => 'Spring 2026',
        'slug' => 'spring-2026',
        'status' => AffiliateCampaign::STATUS_ACTIVE,
        'default_payout_cents' => 5000,
        'currency' => 'USD',
        'hold_days' => 14,
    ]);

    $asset = CampaignAsset::create([
        'tenant_id' => $tenant->id,
        'affiliate_campaign_id' => $campaign->id,
        'name' => 'Approved Creative',
        'asset_type' => CampaignAsset::TYPE_IMAGE,
        'storage_path' => 'campaign-assets/approved.png',
        'status' => CampaignAsset::STATUS_APPROVED,
    ]);

    $link = AffiliateLink::create([
        'tenant_id' => $tenant->id,
        'affiliate_partner_id' => $partner->id,
        'affiliate_campaign_id' => $campaign->id,
        'campaign_asset_id' => $asset->id,
        'status' => AffiliateLink::STATUS_ACTIVE,
    ]);

    return [$partner, $campaign, $asset, $link];
}

test('affiliate portal loads with aggregate metrics and no patient data', function (): void {
    $tenant = Tenant::factory()->create();
    [$partner] = makeAffiliatePortalSeed($tenant);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('affiliate.portal.show', [
            'partner' => $partner->id,
            'token' => $partner->portal_access_token,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('affiliate/portal')
            ->where('partner.id', $partner->id)
            ->where('terms.current_version', AffiliateTerms::CURRENT_VERSION)
            ->where('terms.accepted_current', false)
            ->has('metrics')
            ->has('links', 1)
            ->missing('patient')
            ->missing('patients')
        );
});

test('affiliate can accept latest terms version from portal', function (): void {
    $tenant = Tenant::factory()->create();
    [$partner] = makeAffiliatePortalSeed($tenant);

    $this->withoutMiddleware();

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->post(route('affiliate.portal.accept-terms', [
            'partner' => $partner->id,
            'token' => $partner->portal_access_token,
        ]))
        ->assertRedirect();

    $acceptance = AffiliateTermsAcceptance::query()
        ->where('affiliate_partner_id', $partner->id)
        ->where('terms_version', AffiliateTerms::CURRENT_VERSION)
        ->first();

    expect($acceptance)->not->toBeNull()
        ->and($acceptance->accepted_at)->not->toBeNull();
});

test('affiliate portal denies access with invalid token', function (): void {
    $tenant = Tenant::factory()->create();
    [$partner] = makeAffiliatePortalSeed($tenant);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('affiliate.portal.show', [
            'partner' => $partner->id,
            'token' => 'invalid-token',
        ]))
        ->assertNotFound();
});

test('clinic admin can create affiliate link for campaign', function (): void {
    $tenant = Tenant::factory()->create();
    [$partner, $campaign, $asset] = makeAffiliatePortalSeed($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $this->withoutMiddleware();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->post(route('clinic.affiliates.campaigns.links.store', $campaign), [
            'affiliate_partner_id' => $partner->id,
            'campaign_asset_id' => $asset->id,
        ]);

    $response->assertRedirect();

    $createdLinks = AffiliateLink::query()
        ->where('affiliate_campaign_id', $campaign->id)
        ->where('affiliate_partner_id', $partner->id)
        ->count();

    expect($createdLinks)->toBeGreaterThan(1);
});
