<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\ReleaseAffiliatePayoutRequest;
use App\Http\Requests\Clinic\ReviewAffiliatePayoutRequest;
use App\Models\AffiliatePayoutLedger;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePayoutController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function index(): Response
    {
        $tenant = TenantContext::get();

        $payouts = AffiliatePayoutLedger::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'partner:id,name,handle',
                'campaign:id,name',
                'evaluation:id,secure_token,status,completed_at',
            ])
            ->orderByRaw("CASE status WHEN 'pending_hold' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 WHEN 'released' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->get([
                'id',
                'tenant_id',
                'affiliate_partner_id',
                'affiliate_campaign_id',
                'evaluation_id',
                'status',
                'amount_cents',
                'currency',
                'hold_until',
                'released_at',
                'rejection_reason',
                'created_at',
            ]);

        return Inertia::render('clinic/affiliate-payouts', [
            'payouts' => $payouts,
            'serverNow' => now()->toIso8601String(),
        ]);
    }

    public function review(ReviewAffiliatePayoutRequest $request, string $affiliatePayoutLedger): RedirectResponse
    {
        $payoutLedger = $this->resolvePayoutLedger($affiliatePayoutLedger);

        $validated = $request->validated();
        $status = $validated['status'];

        if (! in_array($payoutLedger->status, [
            AffiliatePayoutLedger::STATUS_PENDING_HOLD,
            AffiliatePayoutLedger::STATUS_APPROVED,
        ], true)) {
            return back()->with('flash.error', 'Payout cannot be reviewed from its current status.');
        }

        DB::transaction(function () use ($payoutLedger, $status, $validated, $request): void {
            $payoutLedger->update([
                'status' => $status,
                'reviewed_by_user_id' => $request->user()?->id,
                'rejection_reason' => $status === AffiliatePayoutLedger::STATUS_REJECTED
                    ? $validated['rejection_reason']
                    : null,
            ]);

            $this->auditLog->record('affiliate.payout.reviewed', $payoutLedger, [
                'payout_id' => $payoutLedger->id,
                'new_status' => $status,
            ]);
        });

        return back()->with('flash.success', 'Payout review saved.');
    }

    public function release(ReleaseAffiliatePayoutRequest $request, string $affiliatePayoutLedger): RedirectResponse
    {
        $payoutLedger = $this->resolvePayoutLedger($affiliatePayoutLedger);

        if ($payoutLedger->status !== AffiliatePayoutLedger::STATUS_APPROVED) {
            return back()->with('flash.error', 'Only approved payouts can be released.');
        }

        if ($payoutLedger->hold_until !== null && $payoutLedger->hold_until->isFuture()) {
            return back()->with('flash.error', 'Hold window has not expired yet.');
        }

        DB::transaction(function () use ($payoutLedger, $request): void {
            $payoutLedger->update([
                'status' => AffiliatePayoutLedger::STATUS_RELEASED,
                'released_at' => now(),
                'reviewed_by_user_id' => $request->user()?->id,
            ]);

            $this->auditLog->record('affiliate.payout.released', $payoutLedger, [
                'payout_id' => $payoutLedger->id,
            ]);
        });

        return back()->with('flash.success', 'Payout released.');
    }

    private function resolvePayoutLedger(string $id): AffiliatePayoutLedger
    {
        $payoutLedger = AffiliatePayoutLedger::findOrFail($id);

        abort_unless($payoutLedger->tenant_id === TenantContext::id(), 404);

        return $payoutLedger;
    }
}
