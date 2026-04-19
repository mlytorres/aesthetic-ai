<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAffiliatePartnerRequest;
use App\Http\Requests\Clinic\UpdateAffiliatePartnerRequest;
use App\Mail\AffiliatePartnerInviteMail;
use App\Models\AffiliatePartner;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                'id'                => $partner->id,
                'name'              => $partner->name,
                'email'             => $partner->email,
                'platform'          => $partner->platform,
                'handle'            => $partner->handle,
                'status'            => $partner->status,
                'payout_cents'      => $partner->payout_cents,
                'currency'          => $partner->currency,
                'monthly_cap_cents' => $partner->monthly_cap_cents,
                'hold_days'         => $partner->hold_days,
                'portal_url'        => route('affiliate.portal.show', [
                    'partner' => $partner->id,
                    'token'   => $partner->portal_access_token,
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

        $inviteSent = $this->sendPartnerInvite($partner);

        return back()->with(
            'flash.success',
            $inviteSent
                ? 'Affiliate partner created — invite email sent.'
                : 'Affiliate partner created. Invite email could not be queued; resend from the portal.'
        );
    }

    public function update(UpdateAffiliatePartnerRequest $request, string $affiliatePartner): RedirectResponse
    {
        $partner = AffiliatePartner::findOrFail($affiliatePartner);
        abort_unless($partner->tenant_id === TenantContext::id(), 404);

        $partner->update($request->validated());

        $this->auditLog->record('affiliate.partner.updated', $partner, [
            'affiliate_partner_id' => $partner->id,
        ]);

        return back()->with('flash.success', 'Partner updated.');
    }

    public function destroy(string $affiliatePartner): RedirectResponse
    {
        $partner = AffiliatePartner::findOrFail($affiliatePartner);
        abort_unless($partner->tenant_id === TenantContext::id(), 404);

        $partner->delete();

        $this->auditLog->record('affiliate.partner.deleted', null, [
            'affiliate_partner_id' => $partner->id,
            'name'                 => $partner->name,
        ]);

        return back()->with('flash.success', 'Partner removed.');
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

    /**
     * Queue the invite email. Wrapped in a try/catch so a mail driver hiccup never
     * kills the create flow — the partner record is already persisted and the audit
     * log captures the failure for follow-up.
     */
    private function sendPartnerInvite(AffiliatePartner $partner): bool
    {
        try {
            Mail::to($partner->email)->queue(new AffiliatePartnerInviteMail(
                partner: $partner,
                tenant: TenantContext::get(),
            ));

            $this->auditLog->record('affiliate.partner.invite_sent', $partner, [
                'affiliate_partner_id' => $partner->id,
                'email'                => $partner->email,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed to queue affiliate partner invite', [
                'affiliate_partner_id' => $partner->id,
                'tenant_id'            => $partner->tenant_id,
                'error'                => $e->getMessage(),
            ]);

            $this->auditLog->record('affiliate.partner.invite_failed', $partner, [
                'affiliate_partner_id' => $partner->id,
                'error'                => $e->getMessage(),
            ]);

            return false;
        }
    }
}
