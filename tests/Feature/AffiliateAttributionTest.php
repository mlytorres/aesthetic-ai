<?php

declare(strict_types=1);

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AffiliateTermsAcceptance;
use App\Models\AttributionEvent;
use App\Models\CampaignAsset;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\AffiliateTerms;
use Illuminate\Support\Facades\Http;

function makeAffiliateLink(Tenant $tenant, array $overrides = [], bool $acceptCurrentTerms = true): AffiliateLink
{
    $partner = AffiliatePartner::create([
        'tenant_id' => $tenant->id,
        'name' => 'Creator One',
        'email' => 'creator@example.com',
        'platform' => AffiliatePartner::PLATFORM_INSTAGRAM,
        'handle' => '@creatorone',
        'status' => AffiliatePartner::STATUS_ACTIVE,
        'payout_cents' => 5000,
        'currency' => 'USD',
        'monthly_cap_cents' => 200000,
        'hold_days' => 14,
    ]);

    $campaign = AffiliateCampaign::create([
        'tenant_id' => $tenant->id,
        'name' => 'Spring Influencer Push',
        'slug' => 'spring-influencer-push',
        'status' => AffiliateCampaign::STATUS_ACTIVE,
        'default_payout_cents' => 5000,
        'currency' => 'USD',
        'hold_days' => 14,
    ]);

    $asset = CampaignAsset::create([
        'tenant_id' => $tenant->id,
        'affiliate_campaign_id' => $campaign->id,
        'name' => 'Approved Story Creative',
        'asset_type' => CampaignAsset::TYPE_IMAGE,
        'storage_path' => 'campaign-assets/approved-story.png',
        'status' => CampaignAsset::STATUS_APPROVED,
        'approved_at' => now(),
    ]);

    $link = AffiliateLink::create(array_merge([
        'tenant_id' => $tenant->id,
        'affiliate_partner_id' => $partner->id,
        'affiliate_campaign_id' => $campaign->id,
        'campaign_asset_id' => $asset->id,
        'token' => 'aff-token-123',
        'status' => AffiliateLink::STATUS_ACTIVE,
    ], $overrides));

    if ($acceptCurrentTerms) {
        AffiliateTermsAcceptance::create([
            'tenant_id' => $tenant->id,
            'affiliate_partner_id' => $partner->id,
            'terms_version' => AffiliateTerms::CURRENT_VERSION,
            'accepted_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'phpunit'),
        ]);
    }

    return $link;
}

test('affiliate track endpoint records click and redirects into intake', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $link = makeAffiliateLink($tenant);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('intake.affiliate.track', ['token' => $link->token]));

    $response->assertRedirect(route('intake.show', ['aff' => $link->token], absolute: false));

    $link->refresh();

    expect($link->click_count)->toBe(1)
        ->and($link->last_clicked_at)->not->toBeNull();

    $event = AttributionEvent::where('event_type', AttributionEvent::TYPE_CLICK)->first();

    expect($event)->not->toBeNull()
        ->and($event->affiliate_link_id)->toBe($link->id);
});

test('creating evaluation with affiliate token sets affiliate_link_id and tracks intake_started event', function (): void {
    $this->withoutMiddleware();

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $link = makeAffiliateLink($tenant);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson('/intake/evaluations', [
            'procedure_slug' => 'rhinoplasty',
            'aff' => $link->token,
        ]);

    $response->assertCreated();

    $evaluation = Evaluation::withoutGlobalScopes()->latest('id')->firstOrFail();

    expect($evaluation->affiliate_link_id)->toBe($link->id);

    $event = AttributionEvent::query()
        ->where('event_type', AttributionEvent::TYPE_INTAKE_STARTED)
        ->where('evaluation_id', $evaluation->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->affiliate_link_id)->toBe($link->id);
});

test('submitting an attributed evaluation creates completion event and payout ledger entry', function (): void {
    $this->withoutMiddleware();

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $link = makeAffiliateLink($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_SUBMITTED,
        'funnel_step' => Evaluation::FUNNEL_PHOTOS,
        'affiliate_link_id' => $link->id,
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson("/intake/evaluations/{$evaluation->secure_token}/submit", [
            'patient' => [
                'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com',
                'phone' => '305-555-1001',
            ],
            'consent' => [
                'hipaa_acknowledged' => true,
                'terms_accepted' => true,
                'photo_use_consent' => true,
                'consented_at' => now()->toISOString(),
            ],
            'turnstile_token' => 'turnstile-token',
        ]);

    $response->assertOk();

    $completionEvent = AttributionEvent::query()
        ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
        ->where('evaluation_id', $evaluation->id)
        ->first();

    expect($completionEvent)->not->toBeNull();

    $ledger = AffiliatePayoutLedger::query()
        ->where('evaluation_id', $evaluation->id)
        ->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->status)->toBe(AffiliatePayoutLedger::STATUS_PENDING_HOLD)
        ->and($ledger->amount_cents)->toBe(5000)
        ->and($ledger->currency)->toBe('USD')
        ->and($ledger->hold_until)->not->toBeNull();
});

test('tracking token is ignored when partner has not accepted current terms', function (): void {
    $this->withoutMiddleware();

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $link = makeAffiliateLink($tenant, [], false);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('intake.affiliate.track', ['token' => $link->token]))
        ->assertRedirect(route('intake.show', ['aff' => $link->token], absolute: false));

    expect(AttributionEvent::where('event_type', AttributionEvent::TYPE_CLICK)->count())->toBe(0);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->postJson('/intake/evaluations', [
            'procedure_slug' => 'rhinoplasty',
            'aff' => $link->token,
        ]);

    $response->assertCreated();

    $evaluation = Evaluation::withoutGlobalScopes()->latest('id')->firstOrFail();
    expect($evaluation->affiliate_link_id)->toBeNull();
});
