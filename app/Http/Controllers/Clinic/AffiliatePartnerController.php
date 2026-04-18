<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAffiliatePartnerRequest;
use App\Models\AffiliatePartner;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePartnerController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function index(): Response
    {
        $tenant = TenantContext::get();

        $partners = AffiliatePartner::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'platform',
                'handle',
                'status',
                'payout_cents',
                'currency',
                'monthly_cap_cents',
                'hold_days',
                'portal_access_token',
                'created_at',
            ]);

        $this->auditLog->record('affiliate.partners.viewed', null, [
            'count' => $partners->count(),
        ]);

        return Inertia::render('clinic/affiliate-partners', [
            'partners' => $partners->map(fn (AffiliatePartner $partner): array => [
                'id' => $partner->id,
                'name' => $partner->name,
                'email' => $partner->email,
                'platform' => $partner->platform,
                'handle' => $partner->handle,
                'status' => $partner->status,
                'payout_cents' => $partner->payout_cents,
                'currency' => $partner->currency,
                'monthly_cap_cents' => $partner->monthly_cap_cents,
                'hold_days' => $partner->hold_days,
                'portal_url' => route('affiliate.portal.show', [
                    'partner' => $partner->id,
                    'token' => $partner->portal_access_token,
                ], absolute: true),
            ])->values(),
        ]);
    }

    public function store(StoreAffiliatePartnerRequest $request): RedirectResponse
    {
        $partner = AffiliatePartner::create($request->validated());

        $this->auditLog->record('affiliate.partner.created', $partner, [
            'platform' => $partner->platform,
        ]);

        return back()->with('flash.success', 'Affiliate partner created.');
    }

    public function rotatePortalToken(string $affiliatePartner): RedirectResponse
    {
        $partner = AffiliatePartner::findOrFail($affiliatePartner);
        abort_unless($partner->tenant_id === TenantContext::id(), 404);

        $partner->update([
            'portal_access_token' => Str::random(48),
        ]);

        $this->auditLog->record('affiliate.partner.portal_token_rotated', $partner, [
            'affiliate_partner_id' => $partner->id,
        ]);

        return back()->with('flash.success', 'Affiliate portal token rotated.');
    }
}
