<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePlatformController extends Controller
{
    public function index(): Response
    {
        // Cross-tenant read — bypass both the application-layer TenantScope and the
        // database-layer RLS policy via the TenantContext::withAllTenants helper.
        [$stats, $topTenants, $recentPayouts] = TenantContext::withAllTenants(function (): array {
            $partnerBase = fn () => AffiliatePartner::withoutGlobalScope(TenantScope::class);
            $ledgerBase = fn () => AffiliatePayoutLedger::withoutGlobalScope(TenantScope::class);

            // Fraud-flagged pending count: load metadata and filter in PHP so this works
            // identically on Postgres (prod) and SQLite (test) without a JSON extension dance.
            $flaggedPending = $ledgerBase()
                ->where('status', AffiliatePayoutLedger::STATUS_PENDING_HOLD)
                ->get(['metadata'])
                ->filter(fn (AffiliatePayoutLedger $row) => ! empty($row->metadata['fraud_flags'] ?? []))
                ->count();

            $stats = [
                'total_partners' => $partnerBase()->count(),
                'active_partners' => $partnerBase()
                    ->where('status', AffiliatePartner::STATUS_ACTIVE)
                    ->count(),
                'total_payout_volume_cents' => $ledgerBase()->sum('amount_cents'),
                'pending_payouts_cents' => $ledgerBase()
                    ->whereIn('status', [
                        AffiliatePayoutLedger::STATUS_PENDING_HOLD,
                        AffiliatePayoutLedger::STATUS_APPROVED,
                    ])
                    ->sum('amount_cents'),
                'released_payouts_cents' => $ledgerBase()
                    ->where('status', AffiliatePayoutLedger::STATUS_RELEASED)
                    ->sum('amount_cents'),
                'flagged_pending_payouts' => $flaggedPending,
            ];

            $topTenants = Tenant::query()
                ->withCount('affiliatePartners')
                ->withSum('affiliatePayoutLedgers', 'amount_cents')
                ->orderByDesc('affiliate_payout_ledgers_sum_amount_cents')
                ->limit(10)
                ->get();

            $recentPayouts = $ledgerBase()
                ->with(['partner:id,name', 'tenant:id,name'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (AffiliatePayoutLedger $row) => [
                    'id' => $row->id,
                    'tenant' => $row->tenant,
                    'partner' => $row->partner,
                    'status' => $row->status,
                    'amount_cents' => $row->amount_cents,
                    'currency' => $row->currency,
                    'hold_until' => $row->hold_until,
                    'released_at' => $row->released_at,
                    'created_at' => $row->created_at,
                    'fraud_flags' => $row->metadata['fraud_flags'] ?? [],
                    'fraud_risk' => $row->metadata['fraud_risk'] ?? 'low',
                ]);

            return [$stats, $topTenants, $recentPayouts];
        });

        return Inertia::render('admin/affiliate-audit', [
            'stats' => $stats,
            'topTenants' => $topTenants,
            'recentPayouts' => $recentPayouts,
        ]);
    }
}
