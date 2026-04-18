<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Facades\TenantContext;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AffiliateTermsAcceptance;
use App\Models\AttributionEvent;
use App\Models\CampaignAsset;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\AffiliateTerms;
use Illuminate\Database\Seeder;

class AffiliateProgramDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->resolveTenant();
        TenantContext::set($tenant);

        $campaign = AffiliateCampaign::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => 'affiliate-spring-2026',
            ],
            [
                'name' => 'Affiliate Spring 2026',
                'status' => AffiliateCampaign::STATUS_ACTIVE,
                'description' => 'Demo affiliate campaign with approved assets and payout tracking.',
                'default_payout_cents' => 6500,
                'currency' => 'USD',
                'monthly_cap_cents' => 250000,
                'hold_days' => 14,
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addMonths(2),
                'settings' => [
                    'approved_assets_only' => true,
                ],
            ],
        );

        $approvedAsset = CampaignAsset::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'affiliate_campaign_id' => $campaign->id,
                'name' => 'Spring Story Creative A',
            ],
            [
                'asset_type' => CampaignAsset::TYPE_IMAGE,
                'storage_path' => 'campaign-assets/spring-story-a.png',
                'checksum' => hash('sha256', 'spring-story-a.png'),
                'status' => CampaignAsset::STATUS_APPROVED,
                'approved_at' => now()->subDays(5),
                'compliance_notes' => 'Approved for Instagram stories and reels captions.',
            ],
        );

        CampaignAsset::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'affiliate_campaign_id' => $campaign->id,
                'name' => 'Draft Creative - Pending Review',
            ],
            [
                'asset_type' => CampaignAsset::TYPE_IMAGE,
                'storage_path' => 'campaign-assets/draft-pending.png',
                'checksum' => hash('sha256', 'draft-pending.png'),
                'status' => CampaignAsset::STATUS_PENDING,
                'approved_at' => null,
                'compliance_notes' => 'Pending compliance approval.',
            ],
        );

        CampaignAsset::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'affiliate_campaign_id' => $campaign->id,
                'name' => 'IG Reel Caption Template',
            ],
            [
                'asset_type' => CampaignAsset::TYPE_CAPTION,
                'storage_path' => 'Ready to transform your look? Click my link to start your AI-guided evaluation at Miami Life! ✨ #BeautyRoadmap #MiamiLife',
                'checksum' => null,
                'status' => CampaignAsset::STATUS_APPROVED,
                'approved_at' => now()->subDays(2),
                'compliance_notes' => 'Mandatory #Ad disclosure included.',
            ],
        );

        $partnerAccepted = $this->upsertPartner(
            tenant: $tenant,
            name: 'Mia Influencer',
            email: 'mia.affiliate@aesthetic-ai.test',
            handle: '@mia_beauty',
            status: AffiliatePartner::STATUS_ACTIVE,
            payoutCents: 6500,
        );

        $partnerNoTerms = $this->upsertPartner(
            tenant: $tenant,
            name: 'Leo Creator',
            email: 'leo.affiliate@aesthetic-ai.test',
            handle: '@leo_looks',
            status: AffiliatePartner::STATUS_ACTIVE,
            payoutCents: 6500,
        );

        $partnerPaused = $this->upsertPartner(
            tenant: $tenant,
            name: 'Nora Paused',
            email: 'nora.affiliate@aesthetic-ai.test',
            handle: '@nora_style',
            status: AffiliatePartner::STATUS_PAUSED,
            payoutCents: 6500,
        );

        AffiliateTermsAcceptance::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'affiliate_partner_id' => $partnerAccepted->id,
                'terms_version' => AffiliateTerms::CURRENT_VERSION,
            ],
            [
                'accepted_at' => now()->subDays(3),
                'ip_hash' => hash('sha256', '127.0.0.1'),
                'user_agent_hash' => hash('sha256', 'SeederBot'),
                'metadata' => ['source' => 'affiliate_demo_seeder'],
            ],
        );

        $linkAccepted = AffiliateLink::firstOrCreate([
            'tenant_id' => $tenant->id,
            'affiliate_partner_id' => $partnerAccepted->id,
            'affiliate_campaign_id' => $campaign->id,
            'campaign_asset_id' => $approvedAsset->id,
        ], [
            'status' => AffiliateLink::STATUS_ACTIVE,
            'short_code' => 'spring26',
        ]);

        $linkNoTerms = AffiliateLink::firstOrCreate([
            'tenant_id' => $tenant->id,
            'affiliate_partner_id' => $partnerNoTerms->id,
            'affiliate_campaign_id' => $campaign->id,
            'campaign_asset_id' => $approvedAsset->id,
        ], [
            'status' => AffiliateLink::STATUS_ACTIVE,
            'short_code' => 'looks26',
        ]);

        AffiliateLink::firstOrCreate([
            'tenant_id' => $tenant->id,
            'affiliate_partner_id' => $partnerPaused->id,
            'affiliate_campaign_id' => $campaign->id,
            'campaign_asset_id' => $approvedAsset->id,
        ], [
            'status' => AffiliateLink::STATUS_PAUSED,
            'short_code' => 'style26',
        ]);

        $firstEvaluation = $this->createEvaluationForLink(
            tenant: $tenant,
            link: $linkAccepted,
            patientEmail: 'demo-patient-one@aesthetic-ai.test',
            patientName: 'Demo Patient One',
            leadScore: 84,
        );

        $secondEvaluation = $this->createEvaluationForLink(
            tenant: $tenant,
            link: $linkAccepted,
            patientEmail: 'demo-patient-two@aesthetic-ai.test',
            patientName: 'Demo Patient Two',
            leadScore: 71,
        );

        $firstEvent = AttributionEvent::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_type' => AttributionEvent::TYPE_EVALUATION_COMPLETED,
                'idempotency_key' => 'seed:complete:'.$firstEvaluation->id,
            ],
            [
                'affiliate_link_id' => $linkAccepted->id,
                'affiliate_partner_id' => $linkAccepted->affiliate_partner_id,
                'affiliate_campaign_id' => $linkAccepted->affiliate_campaign_id,
                'evaluation_id' => $firstEvaluation->id,
                'occurred_at' => now()->subDays(2),
                'metadata' => ['source' => 'affiliate_demo_seeder'],
            ],
        );

        $secondEvent = AttributionEvent::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_type' => AttributionEvent::TYPE_EVALUATION_COMPLETED,
                'idempotency_key' => 'seed:complete:'.$secondEvaluation->id,
            ],
            [
                'affiliate_link_id' => $linkAccepted->id,
                'affiliate_partner_id' => $linkAccepted->affiliate_partner_id,
                'affiliate_campaign_id' => $linkAccepted->affiliate_campaign_id,
                'evaluation_id' => $secondEvaluation->id,
                'occurred_at' => now()->subDay(),
                'metadata' => ['source' => 'affiliate_demo_seeder'],
            ],
        );

        AffiliatePayoutLedger::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'evaluation_id' => $firstEvaluation->id,
                'affiliate_link_id' => $linkAccepted->id,
            ],
            [
                'affiliate_partner_id' => $linkAccepted->affiliate_partner_id,
                'affiliate_campaign_id' => $linkAccepted->affiliate_campaign_id,
                'attribution_event_id' => $firstEvent->id,
                'status' => AffiliatePayoutLedger::STATUS_RELEASED,
                'amount_cents' => $campaign->default_payout_cents,
                'currency' => 'USD',
                'hold_until' => now()->subDay(),
                'released_at' => now()->subHours(12),
                'metadata' => ['source' => 'affiliate_demo_seeder'],
            ],
        );

        AffiliatePayoutLedger::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'evaluation_id' => $secondEvaluation->id,
                'affiliate_link_id' => $linkAccepted->id,
            ],
            [
                'affiliate_partner_id' => $linkAccepted->affiliate_partner_id,
                'affiliate_campaign_id' => $linkAccepted->affiliate_campaign_id,
                'attribution_event_id' => $secondEvent->id,
                'status' => AffiliatePayoutLedger::STATUS_PENDING_HOLD,
                'amount_cents' => $campaign->default_payout_cents,
                'currency' => 'USD',
                'hold_until' => now()->addDays(7),
                'released_at' => null,
                'metadata' => ['source' => 'affiliate_demo_seeder'],
            ],
        );

        $this->command?->info('Affiliate demo seeder complete.');
        $this->command?->line('Tenant slug: '.$tenant->slug);
        $this->command?->line('Accepted partner portal: '.route('affiliate.portal.show', [
            'partner' => $partnerAccepted->id,
            'token' => $partnerAccepted->portal_access_token,
        ], absolute: true));
        $this->command?->line('No-terms partner portal: '.route('affiliate.portal.show', [
            'partner' => $partnerNoTerms->id,
            'token' => $partnerNoTerms->portal_access_token,
        ], absolute: true));
    }

    private function resolveTenant(): Tenant
    {
        $tenant = Tenant::query()->where('slug', 'miamilife')->first();

        if ($tenant !== null) {
            return $tenant;
        }

        $starterPlan = Plan::query()->where('slug', 'starter')->first();

        if ($starterPlan === null) {
            $starterPlan = Plan::create([
                'slug' => 'starter',
                'name' => 'Starter',
                'max_procedures' => 1,
                'max_evaluations_mo' => 50,
                'features' => ['widget', 'dashboard', 'basic_ai'],
            ]);
        }

        return Tenant::create([
            'slug' => 'miamilife',
            'name' => 'Miami Life Cosmetic Center',
            'plan_id' => $starterPlan->id,
            'settings' => [
                'theme' => 'luxury-dark',
                'procedures_enabled' => ['rhinoplasty', 'bbl', 'lipo_360'],
            ],
        ]);
    }

    private function upsertPartner(
        Tenant $tenant,
        string $name,
        string $email,
        string $handle,
        string $status,
        int $payoutCents,
    ): AffiliatePartner {
        $partner = AffiliatePartner::firstOrNew([
            'tenant_id' => $tenant->id,
            'email' => $email,
        ]);

        $partner->fill([
            'name' => $name,
            'platform' => AffiliatePartner::PLATFORM_INSTAGRAM,
            'handle' => $handle,
            'status' => $status,
            'payout_cents' => $payoutCents,
            'currency' => 'USD',
            'monthly_cap_cents' => 250000,
            'hold_days' => 14,
        ]);

        $partner->save();

        return $partner;
    }

    private function createEvaluationForLink(
        Tenant $tenant,
        AffiliateLink $link,
        string $patientEmail,
        string $patientName,
        int $leadScore,
    ): Evaluation {
        $patient = Patient::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email_hash' => Patient::hashEmail($patientEmail),
            ],
            [
                'name_encrypted' => $patientName,
                'email_encrypted' => $patientEmail,
                'phone_encrypted' => null,
                'name_hash' => hash_hmac('sha256', strtolower($patientName), (string) config('app.key')),
                'created_via' => 'widget',
            ],
        );

        return Evaluation::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'affiliate_link_id' => $link->id,
                'status' => Evaluation::STATUS_COMPLETE,
            ],
            [
                'procedure_slug' => 'rhinoplasty',
                'lead_score' => $leadScore,
                'priority' => Evaluation::PRIORITY_HIGH,
                'funnel_step' => Evaluation::FUNNEL_SUBMITTED,
                'completed_at' => now()->subDay(),
            ],
        );
    }
}
