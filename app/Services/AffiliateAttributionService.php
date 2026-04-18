<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AffiliateTermsAcceptance;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Support\AffiliateTerms;
use Illuminate\Http\Request;

class AffiliateAttributionService
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function findActiveLinkByToken(string $token): ?AffiliateLink
    {
        $link = AffiliateLink::query()
            ->with(['partner', 'campaign', 'asset'])
            ->where('token', $token)
            ->where('status', AffiliateLink::STATUS_ACTIVE)
            ->first();

        if ($link === null || ! $this->isLinkEligible($link)) {
            return null;
        }

        return $link;
    }

    public function trackClick(AffiliateLink $link, Request $request): AttributionEvent
    {
        $link->update([
            'click_count' => $link->click_count + 1,
            'last_clicked_at' => now(),
        ]);

        $event = AttributionEvent::create([
            'tenant_id' => $link->tenant_id,
            'affiliate_link_id' => $link->id,
            'affiliate_partner_id' => $link->affiliate_partner_id,
            'affiliate_campaign_id' => $link->affiliate_campaign_id,
            'event_type' => AttributionEvent::TYPE_CLICK,
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => [
                'source' => 'redirect',
                'path' => $request->path(),
            ],
            'occurred_at' => now(),
        ]);

        $this->auditLog->record('affiliate.link.click_tracked', $link, [
            'affiliate_link_id' => $link->id,
            'event_id' => $event->id,
        ]);

        return $event;
    }

    public function trackIntakeStarted(Evaluation $evaluation, Request $request): ?AttributionEvent
    {
        if ($evaluation->affiliate_link_id === null) {
            return null;
        }

        $link = AffiliateLink::find($evaluation->affiliate_link_id);

        if ($link === null || ! $this->isLinkEligible($link)) {
            return null;
        }

        $idempotencyKey = sprintf('intake_started:%s', $evaluation->id);

        return AttributionEvent::firstOrCreate([
            'tenant_id' => $evaluation->tenant_id,
            'event_type' => AttributionEvent::TYPE_INTAKE_STARTED,
            'idempotency_key' => $idempotencyKey,
        ], [
            'affiliate_link_id' => $link->id,
            'affiliate_partner_id' => $link->affiliate_partner_id,
            'affiliate_campaign_id' => $link->affiliate_campaign_id,
            'evaluation_id' => $evaluation->id,
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => [
                'source' => 'evaluation_store',
            ],
            'occurred_at' => now(),
        ]);
    }

    public function trackEvaluationCompleted(Evaluation $evaluation, Request $request): ?AttributionEvent
    {
        if ($evaluation->affiliate_link_id === null) {
            return null;
        }

        $link = AffiliateLink::with(['campaign', 'asset'])->find($evaluation->affiliate_link_id);

        if ($link === null || ! $this->isLinkEligible($link)) {
            return null;
        }

        $idempotencyKey = sprintf('evaluation_completed:%s', $evaluation->id);

        $event = AttributionEvent::firstOrCreate([
            'tenant_id' => $evaluation->tenant_id,
            'event_type' => AttributionEvent::TYPE_EVALUATION_COMPLETED,
            'idempotency_key' => $idempotencyKey,
        ], [
            'affiliate_link_id' => $link->id,
            'affiliate_partner_id' => $link->affiliate_partner_id,
            'affiliate_campaign_id' => $link->affiliate_campaign_id,
            'evaluation_id' => $evaluation->id,
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => [
                'source' => 'evaluation_submit',
            ],
            'occurred_at' => now(),
        ]);

        if ($event->wasRecentlyCreated) {
            $this->createPayoutLedger($evaluation, $link, $event);
        }

        return $event;
    }

    private function createPayoutLedger(Evaluation $evaluation, AffiliateLink $link, AttributionEvent $event): void
    {
        $campaign = $link->campaign;

        if ($campaign === null) {
            return;
        }

        AffiliatePayoutLedger::firstOrCreate([
            'tenant_id' => $evaluation->tenant_id,
            'evaluation_id' => $evaluation->id,
            'affiliate_link_id' => $link->id,
        ], [
            'affiliate_partner_id' => $link->affiliate_partner_id,
            'affiliate_campaign_id' => $link->affiliate_campaign_id,
            'attribution_event_id' => $event->id,
            'status' => AffiliatePayoutLedger::STATUS_PENDING_HOLD,
            'amount_cents' => $campaign->default_payout_cents,
            'currency' => $campaign->currency,
            'hold_until' => now()->addDays($campaign->hold_days),
            'metadata' => [
                'rule' => 'qualified_evaluation',
            ],
        ]);

        $this->auditLog->record('affiliate.payout.pending_hold_created', $evaluation, [
            'affiliate_link_id' => $link->id,
            'attribution_event_id' => $event->id,
        ]);
    }

    private function hashValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', strtolower($value), (string) config('app.key'));
    }

    private function isLinkEligible(AffiliateLink $link): bool
    {
        $link->loadMissing(['partner', 'campaign', 'asset']);

        if (! $link->isActive()) {
            return false;
        }

        if ($link->partner === null || $link->partner->status !== AffiliatePartner::STATUS_ACTIVE) {
            return false;
        }

        if ($link->campaign === null || $link->campaign->status !== AffiliateCampaign::STATUS_ACTIVE) {
            return false;
        }

        if ($link->asset !== null && ! $link->asset->isApproved()) {
            return false;
        }

        return AffiliateTermsAcceptance::query()
            ->where('affiliate_partner_id', $link->affiliate_partner_id)
            ->where('terms_version', AffiliateTerms::CURRENT_VERSION)
            ->exists();
    }
}
