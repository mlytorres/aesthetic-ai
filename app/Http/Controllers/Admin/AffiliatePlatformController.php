<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePlatformController extends Controller
{
    public function index(): Response
    {
        // Aggregate stats across the entire platform
        $stats = [
            'total_partners' => AffiliatePartner::count(),
            'active_partners' => AffiliatePartner::where('status', AffiliatePartner::STATUS_ACTIVE)->count(),
            'total_payout_volume_cents' => AffiliatePayoutLedger::sum('amount_cents'),
            'pending_payouts_cents' => AffiliatePayoutLedger::where('status', AffiliatePayoutLedger::STATUS_PENDING)->sum('amount_cents'),
            'released_payouts_cents' => AffiliatePayoutLedger::where('status', AffiliatePayoutLedger::STATUS_RELEASED)->sum('amount_cents'),
        ];

        // Top tenants by affiliate activity
        $topTenants = Tenant::query()
            ->withCount('affiliatePartners')
            ->withSum('affiliatePayoutLedgers', 'amount_cents')
            ->orderByDesc('affiliate_payout_ledgers_sum_amount_cents')
            ->limit(10)
            ->get();

        // Recent payouts across all tenants
        $recentPayouts = AffiliatePayoutLedger::query()
            ->with(['partner:id,name', 'tenant:id,name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('admin/affiliate-audit', [
            'stats' => $stats,
            'topTenants' => $topTenants,
            'recentPayouts' => $recentPayouts,
        ]);
    }
}
