<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAffiliateCampaignRequest;
use App\Http\Requests\Clinic\StoreCampaignAssetRequest;
use App\Models\AffiliateCampaign;
use App\Models\AffiliatePartner;
use App\Models\CampaignAsset;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateCampaignController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function index(): Response
    {
        $tenant = TenantContext::get();

        $campaigns = AffiliateCampaign::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'assets:id,tenant_id,affiliate_campaign_id,name,asset_type,status',
                'links:id,tenant_id,affiliate_partner_id,affiliate_campaign_id,campaign_asset_id,status,token,click_count,last_clicked_at',
                'links.partner:id,name,handle',
                'links.asset:id,name,status',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'tenant_id',
                'name',
                'slug',
                'status',
                'default_payout_cents',
                'currency',
                'monthly_cap_cents',
                'hold_days',
                'starts_at',
                'ends_at',
                'created_at',
            ]);

        $partners = AffiliatePartner::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', AffiliatePartner::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'handle']);

        return Inertia::render('clinic/affiliate-campaigns', [
            'campaigns' => $campaigns,
            'partners' => $partners,
        ]);
    }

    public function store(StoreAffiliateCampaignRequest $request): RedirectResponse
    {
        $campaign = AffiliateCampaign::create($request->validated());

        $this->auditLog->record('affiliate.campaign.created', $campaign, [
            'status' => $campaign->status,
            'payout_cents' => $campaign->default_payout_cents,
        ]);

        return back()->with('flash.success', 'Affiliate campaign created.');
    }

    public function storeAsset(StoreCampaignAssetRequest $request, AffiliateCampaign $affiliateCampaign): RedirectResponse
    {
        abort_unless($affiliateCampaign->tenant_id === TenantContext::id(), 404);

        $attributes = array_merge($request->validated(), [
            'tenant_id' => $affiliateCampaign->tenant_id,
            'affiliate_campaign_id' => $affiliateCampaign->id,
            'approved_by_user_id' => $request->user()?->id,
            'approved_at' => $request->validated('status') === CampaignAsset::STATUS_APPROVED ? now() : null,
            'revoked_at' => $request->validated('status') === CampaignAsset::STATUS_REVOKED ? now() : null,
        ]);

        $asset = CampaignAsset::create($attributes);

        $this->auditLog->record('affiliate.asset.created', $affiliateCampaign, [
            'asset_id' => $asset->id,
            'status' => $asset->status,
        ]);

        return back()->with('flash.success', 'Campaign asset saved.');
    }
}
