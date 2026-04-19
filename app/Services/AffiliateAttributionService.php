<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\TenantContext;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AffiliateTermsAcceptance;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Support\AffiliateTerms;
use Illuminate\Http\Request;

class AffiliateAttributionService
{
    public function __construct(private readonly AuditLog $auditLog) {}

    /**
     * Resolve an active link by its public tracking token.
     *
     * Runs on unauthenticated routes that have no tenant middleware, so the
     * application-layer TenantScope + database-layer RLS would otherwise filter
     * the row out. Tokens are unique across tenants, so bypass the scope for the
     * initial lookup and then hydrate TenantContext from the link we find so all
     * downstream queries are properly tenant-scoped.
     */
    public function findActiveLinkByToken(string $token): ?AffiliateLink
    {
        $link = AffiliateLink::withoutGlobalScope(TenantScope::class)
            ->with(['partner', 'campaign', 'asset'])
            ->where('token', $token)
            ->where('status', AffiliateLink::STATUS_ACTIVE)
            ->first();

        if ($link === null) {
            return null;
        }

        $this->hydrateTenantContext($link->tenant_id);

        if (! $this->isLinkEligible($link)) {
            return null;
        }

        return $link;
    }

    /**
     * Resolve an active link by its short vanity code.
     * Same RLS-bypass rationale as {@see findActiveLinkByToken}.
     */
    public function findActiveLinkByShortCode(string $code): ?AffiliateLink
    {
        $link = AffiliateLink::withoutGlobalScope(TenantScope::class)
            ->where('short_code', $code)
            ->where('status', AffiliateLink::STATUS_ACTIVE)
            ->first();

        if ($link === null) {
            return null;
        }

        $this->hydrateTenantContext($link->tenant_id);

        return $link;
    }

    /**
     * Set TenantContext + DB GUC from a link's tenant_id. Idempotent — if the
     * context is already set to the same tenant, this is a no-op. If the context
     * is set to a *different* tenant (which would only happen if a clinic-context
     * request somehow reached these public endpoints), we keep the existing one
     * to avoid cross-tenant bleed.
     */
    private function hydrateTenantContext(string $tenantId): void
    {
        if (TenantContext::isSet() && TenantContext::id() === $tenantId) {
            return;
        }

        if (TenantContext::isSet()) {
            return;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant !== null) {
            TenantContext::set($tenant);
        }
    }

    public function trackClick(AffiliateLink $link, Request $request): AttributionEvent
    {
        $isBot = $this->isBot($request->userAgent());

        // Real users bump the counter + timestamp. Crawlers (googlebot, slackbot, link previewers,
        // etc.) still create an AttributionEvent for forensic/debugging purposes but never inflate
        // the partner's click_count — which feeds attribution + conversion-rate reporting.
        if (! $isBot) {
            $link->update([
                'click_count' => $link->click_count + 1,
                'last_clicked_at' => now(),
            ]);
        }

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
                'bot' => $isBot,
            ],
            'occurred_at' => now(),
        ]);

        $this->auditLog->record('affiliate.link.click_tracked', $link, [
            'affiliate_link_id' => $link->id,
            'event_id' => $event->id,
            'bot' => $isBot,
        ]);

        return $event;
    }

    /**
     * Cheap user-agent heuristic for known crawlers + link-preview bots.
     * Not a security boundary — just stops the obvious traffic from skewing click counts.
     */
    private function isBot(?string $userAgent): bool
    {
        if (blank($userAgent)) {
            // Treat missing UA as suspicious (real browsers always send one).
            return true;
        }

        $ua = strtolower($userAgent);

        $patterns = [
            'bot', 'crawler', 'spider', 'slurp', 'mediapartners',
            'facebookexternalhit', 'facebookcatalog', 'twitterbot', 'linkedinbot',
            'slackbot', 'discordbot', 'telegrambot', 'whatsapp', 'skypeuripreview',
            'redditbot', 'pinterestbot', 'embedly', 'quora link preview',
            'headlesschrome', 'phantomjs', 'puppeteer', 'playwright',
            'curl/', 'wget/', 'python-requests', 'go-http-client', 'http_request',
            'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot',
        ];

        foreach ($patterns as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
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

        $fraudFlags = $this->calculateFraudFlags($event, $link);
        $fraudRisk = $this->fraudRiskLevel($fraudFlags);
        $fraudReviewRequired = $this->isFraudReviewRequired($fraudRisk);

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
            'fraud_review_required' => $fraudReviewRequired,
            'metadata' => [
                'rule' => 'qualified_evaluation',
                'fraud_flags' => $fraudFlags,
                'fraud_risk' => $fraudRisk,
            ],
        ]);

        $this->auditLog->record('affiliate.payout.pending_hold_created', $evaluation, [
            'affiliate_link_id' => $link->id,
            'attribution_event_id' => $event->id,
            'fraud_flags' => $fraudFlags,
            'fraud_review_required' => $fraudReviewRequired,
        ]);
    }

    /**
     * Velocity / duplicate heuristics that run synchronously before ledger creation.
     * Flags are advisory — the reviewer decides whether to approve or reject the payout.
     * Per-tenant thresholds override the global config when set in tenant settings.
     *
     * @return array<int, string>
     */
    private function calculateFraudFlags(AttributionEvent $event, AffiliateLink $link): array
    {
        $flags = [];
        $tenantSettings = TenantContext::isSet() ? (TenantContext::get()->settings ?? []) : [];

        // Missing client fingerprint data (likely bot, automation, or privacy-restricted client).
        if ($event->ip_hash === null || $event->user_agent_hash === null) {
            $flags[] = 'missing_fingerprint';
        }

        // Duplicate IP within 24h across completed-evaluation events for this partner.
        if ($event->ip_hash !== null) {
            $duplicateIp = AttributionEvent::query()
                ->where('affiliate_partner_id', $link->affiliate_partner_id)
                ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
                ->where('ip_hash', $event->ip_hash)
                ->where('id', '!=', $event->id)
                ->where('occurred_at', '>=', now()->subDay())
                ->exists();

            if ($duplicateIp) {
                $flags[] = 'duplicate_ip_24h';
            }

            // Click burst from same IP within 1h — distinct signal from completion duplication.
            $clickThreshold = (int) ($tenantSettings['affiliate_fraud_click_velocity_1h_threshold']
                ?? config('affiliate.fraud.click_velocity_1h_threshold', 5));

            $clicksLast1h = AttributionEvent::query()
                ->where('affiliate_partner_id', $link->affiliate_partner_id)
                ->where('event_type', AttributionEvent::TYPE_CLICK)
                ->where('ip_hash', $event->ip_hash)
                ->where('occurred_at', '>=', now()->subHour())
                ->count();

            if ($clicksLast1h > $clickThreshold) {
                $flags[] = 'high_click_velocity_1h';
            }
        }

        // Duplicate user agent within 1h — weaker signal, but combined with IP suggests scripted submission.
        if ($event->user_agent_hash !== null) {
            $duplicateUa = AttributionEvent::query()
                ->where('affiliate_partner_id', $link->affiliate_partner_id)
                ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
                ->where('user_agent_hash', $event->user_agent_hash)
                ->where('id', '!=', $event->id)
                ->where('occurred_at', '>=', now()->subHour())
                ->exists();

            if ($duplicateUa) {
                $flags[] = 'duplicate_ua_1h';
            }
        }

        // Burst detection: more than N completions for this partner in the last 24h.
        $dailyThreshold = (int) ($tenantSettings['affiliate_fraud_daily_completion_threshold']
            ?? config('affiliate.fraud.daily_completion_threshold', 10));

        $completionsLast24h = AttributionEvent::query()
            ->where('affiliate_partner_id', $link->affiliate_partner_id)
            ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
            ->where('occurred_at', '>=', now()->subDay())
            ->count();

        if ($completionsLast24h > $dailyThreshold) {
            $flags[] = 'high_velocity_24h';
        }

        return $flags;
    }

    /**
     * Map flag count to a coarse risk label surfaced in the admin audit UI.
     *
     * @param  array<int, string>  $flags
     */
    private function fraudRiskLevel(array $flags): string
    {
        return match (true) {
            count($flags) >= 2 => 'high',
            count($flags) === 1 => 'medium',
            default => 'low',
        };
    }

    /**
     * Determine whether the payout ledger should enter the fraud review queue.
     * Ledgers at or above the configured threshold risk level are held until a
     * human reviewer explicitly clears or rejects them.
     */
    private function isFraudReviewRequired(string $riskLevel): bool
    {
        $threshold = config('affiliate.fraud.review_required_from_risk', 'medium');

        return match ($threshold) {
            'high' => $riskLevel === 'high',
            default => in_array($riskLevel, ['medium', 'high'], true),
        };
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
