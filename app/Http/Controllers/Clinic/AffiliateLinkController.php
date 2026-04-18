<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAffiliateLinkRequest;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\CampaignAsset;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;

class AffiliateLinkController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function store(StoreAffiliateLinkRequest $request, string $affiliateCampaign): RedirectResponse
    {
        $affiliateCampaign = AffiliateCampaign::findOrFail($affiliateCampaign);
        abort_unless($affiliateCampaign->tenant_id === TenantContext::id(), 404);

        $validated = $request->validated();

        $asset = CampaignAsset::query()
            ->where('id', $validated['campaign_asset_id'])
            ->where('affiliate_campaign_id', $affiliateCampaign->id)
            ->firstOrFail();

        if (! $asset->isApproved()) {
            return back()->with('flash.error', 'Only approved assets can be linked to influencers.');
        }

        $partner = AffiliatePartner::query()
            ->where('id', $validated['affiliate_partner_id'])
            ->where('tenant_id', $affiliateCampaign->tenant_id)
            ->firstOrFail();

        if (! $partner->isActive()) {
            return back()->with('flash.error', 'Only active partners can receive tracking links.');
        }

        $link = AffiliateLink::create([
            'tenant_id' => $affiliateCampaign->tenant_id,
            'affiliate_partner_id' => $partner->id,
            'affiliate_campaign_id' => $affiliateCampaign->id,
            'campaign_asset_id' => $asset->id,
            'status' => $validated['status'] ?? AffiliateLink::STATUS_ACTIVE,
        ]);

        $this->auditLog->record('affiliate.link.created', $affiliateCampaign, [
            'affiliate_link_id' => $link->id,
            'affiliate_partner_id' => $link->affiliate_partner_id,
            'campaign_asset_id' => $link->campaign_asset_id,
        ]);

        return back()->with('flash.success', 'Affiliate link created.');
    }
}
