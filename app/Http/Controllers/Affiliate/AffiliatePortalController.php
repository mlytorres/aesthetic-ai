<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AffiliateTermsAcceptance;
use App\Models\AttributionEvent;
use App\Models\CampaignAsset;
use App\Services\SecureFileService;
use App\Support\AffiliateTerms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePortalController extends Controller
{
    public function __construct(
        private readonly SecureFileService $fileService
    ) {}

    public function show(string $partner, string $token): Response
    {
        $affiliatePartner = $this->resolvePartnerFromToken($partner, $token);

        $linkQuery = AffiliateLink::query()
            ->where('affiliate_partner_id', $affiliatePartner->id);

        $links = $linkQuery
            ->with([
                'campaign:id,name,status',
                'asset:id,name,status',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'affiliate_campaign_id',
                'campaign_asset_id',
                'status',
                'token',
                'short_code',
                'click_count',
                'last_clicked_at',
            ]);

        $linkIds = $links->pluck('id')->all();

        $clicks = AttributionEvent::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->where('event_type', AttributionEvent::TYPE_CLICK)
            ->count();

        $intakeStarts = AttributionEvent::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->where('event_type', AttributionEvent::TYPE_INTAKE_STARTED)
            ->count();

        $completedEvaluations = AttributionEvent::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
            ->count();

        $pendingPayoutCents = AffiliatePayoutLedger::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->whereIn('status', [
                AffiliatePayoutLedger::STATUS_PENDING_HOLD,
                AffiliatePayoutLedger::STATUS_APPROVED,
            ])
            ->sum('amount_cents');

        $releasedPayoutCents = AffiliatePayoutLedger::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->where('status', AffiliatePayoutLedger::STATUS_RELEASED)
            ->sum('amount_cents');

        $acceptedTerms = AffiliateTermsAcceptance::query()
            ->where('affiliate_partner_id', $affiliatePartner->id)
            ->where('terms_version', AffiliateTerms::CURRENT_VERSION)
            ->exists();

        $campaignIds = $links->pluck('affiliate_campaign_id')->unique()->filter()->all();

        $mediaKit = CampaignAsset::query()
            ->whereIn('affiliate_campaign_id', $campaignIds)
            ->where('status', CampaignAsset::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CampaignAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->asset_type,
                'url' => str_contains($asset->storage_path, '://')
                    ? $asset->storage_path
                    : $this->fileService->getSignedUrl($asset->storage_path),
            ]);

        return Inertia::render('affiliate/portal', [
            'partner' => [
                'id' => $affiliatePartner->id,
                'name' => $affiliatePartner->name,
                'handle' => $affiliatePartner->handle,
                'status' => $affiliatePartner->status,
                'platform' => $affiliatePartner->platform,
            ],
            'portal_token' => $token,
            'terms' => [
                'current_version' => AffiliateTerms::CURRENT_VERSION,
                'accepted_current' => $acceptedTerms,
                'summary' => [
                    'Use only platform-approved creatives.',
                    'Disclose sponsored relationship in posts.',
                    'Do not make unapproved medical claims.',
                ],
            ],
            'metrics' => [
                'clicks' => $clicks,
                'intake_starts' => $intakeStarts,
                'completed_evaluations' => $completedEvaluations,
                'pending_payout_cents' => (int) $pendingPayoutCents,
                'released_payout_cents' => (int) $releasedPayoutCents,
            ],
            'links' => $links->map(fn (AffiliateLink $link) => [
                'id' => $link->id,
                'status' => $link->status,
                'click_count' => $link->click_count,
                'last_clicked_at' => $link->last_clicked_at,
                'campaign_name' => $link->campaign?->name,
                'asset_name' => $link->asset?->name,
                'tracking_url' => route('intake.affiliate.track', ['token' => $link->token], absolute: true),
                'short_tracking_url' => $link->short_code
                    ? route('intake.affiliate.short_link', ['code' => $link->short_code], absolute: true)
                    : null,
            ])->values(),
            'media_kit' => $mediaKit,
            'link_count' => count($linkIds),
        ]);
    }

    public function acceptTerms(Request $request, string $partner, string $token): RedirectResponse
    {
        $affiliatePartner = $this->resolvePartnerFromToken($partner, $token);

        AffiliateTermsAcceptance::firstOrCreate([
            'tenant_id' => $affiliatePartner->tenant_id,
            'affiliate_partner_id' => $affiliatePartner->id,
            'terms_version' => AffiliateTerms::CURRENT_VERSION,
        ], [
            'accepted_at' => now(),
            'ip_hash' => $this->hashValue($request->ip()) ?? hash('sha256', 'unknown'),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => [
                'source' => 'affiliate_portal',
            ],
        ]);

        return back()->with('flash.success', 'Terms accepted.');
    }

    private function resolvePartnerFromToken(string $partnerId, string $token): AffiliatePartner
    {
        $affiliatePartner = AffiliatePartner::query()
            ->where('id', $partnerId)
            ->where('tenant_id', TenantContext::id())
            ->firstOrFail();

        abort_unless(hash_equals($affiliatePartner->portal_access_token, $token), 404);

        return $affiliatePartner;
    }

    private function hashValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash_hmac('sha256', strtolower($value), (string) config('app.key'));
    }
}
