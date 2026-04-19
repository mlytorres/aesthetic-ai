<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AttributionEvent;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateAnalyticsController extends Controller
{
    public function index(): Response
    {
        $tenantId = TenantContext::id();

        // ── Summary cards ────────────────────────────────────────────────
        $totalClicks = AttributionEvent::where('tenant_id', $tenantId)
            ->where('event_type', AttributionEvent::TYPE_CLICK)
            ->count();

        $totalIntakes = AttributionEvent::where('tenant_id', $tenantId)
            ->where('event_type', AttributionEvent::TYPE_INTAKE_STARTED)
            ->count();

        $totalConversions = AttributionEvent::where('tenant_id', $tenantId)
            ->where('event_type', AttributionEvent::TYPE_EVALUATION_COMPLETED)
            ->count();

        $payoutTotals = AffiliatePayoutLedger::where('tenant_id', $tenantId)
            ->selectRaw('
                SUM(CASE WHEN status = ? THEN amount_cents ELSE 0 END) as pending_cents,
                SUM(CASE WHEN status = ? THEN amount_cents ELSE 0 END) as approved_cents,
                SUM(CASE WHEN status = ? THEN amount_cents ELSE 0 END) as released_cents,
                SUM(CASE WHEN status != ? THEN amount_cents ELSE 0 END) as total_cents
            ', [
                AffiliatePayoutLedger::STATUS_PENDING_HOLD,
                AffiliatePayoutLedger::STATUS_APPROVED,
                AffiliatePayoutLedger::STATUS_RELEASED,
                AffiliatePayoutLedger::STATUS_REJECTED,
            ])
            ->first();

        // ── 30-day daily trend (clicks + conversions) ───────────────────
        $dailyTrend = AttributionEvent::where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("
                DATE(occurred_at) as date,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as conversions
            ", [AttributionEvent::TYPE_CLICK, AttributionEvent::TYPE_EVALUATION_COMPLETED])
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in zeros for days with no activity
        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $date        = now()->subDays($i)->format('Y-m-d');
            $trend[] = [
                'date'        => $date,
                'clicks'      => (int) ($dailyTrend[$date]->clicks ?? 0),
                'conversions' => (int) ($dailyTrend[$date]->conversions ?? 0),
            ];
        }

        // ── Per-partner performance table ───────────────────────────────
        $partners = AffiliatePartner::where('tenant_id', $tenantId)
            ->withCount([
                'payoutLedgers as total_payouts',
                'payoutLedgers as released_payouts' => fn ($q) => $q->where('status', AffiliatePayoutLedger::STATUS_RELEASED),
            ])
            ->withSum(
                ['payoutLedgers as released_cents' => fn ($q) => $q->where('status', AffiliatePayoutLedger::STATUS_RELEASED)],
                'amount_cents'
            )
            ->withSum(
                ['payoutLedgers as pending_cents' => fn ($q) => $q->whereIn('status', [
                    AffiliatePayoutLedger::STATUS_PENDING_HOLD,
                    AffiliatePayoutLedger::STATUS_APPROVED,
                ])],
                'amount_cents'
            )
            ->orderBy('name')
            ->get(['id', 'name', 'platform', 'handle', 'status', 'currency'])
            ->map(function (AffiliatePartner $p): array {
                // Clicks and conversions from attribution events
                $events = AttributionEvent::where('tenant_id', $p->tenant_id)
                    ->where('affiliate_partner_id', $p->id)
                    ->selectRaw("
                        SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as clicks,
                        SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as conversions
                    ", [AttributionEvent::TYPE_CLICK, AttributionEvent::TYPE_EVALUATION_COMPLETED])
                    ->first();

                $clicks      = (int) ($events->clicks ?? 0);
                $conversions = (int) ($events->conversions ?? 0);

                return [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'platform'         => $p->platform,
                    'handle'           => $p->handle,
                    'status'           => $p->status,
                    'currency'         => $p->currency,
                    'clicks'           => $clicks,
                    'conversions'      => $conversions,
                    'conversion_rate'  => $clicks > 0 ? round($conversions / $clicks * 100, 1) : 0,
                    'total_payouts'    => (int) $p->total_payouts,
                    'released_cents'   => (int) ($p->released_cents ?? 0),
                    'pending_cents'    => (int) ($p->pending_cents ?? 0),
                ];
            });

        return Inertia::render('clinic/affiliate-analytics', [
            'summary' => [
                'total_clicks'      => $totalClicks,
                'total_intakes'     => $totalIntakes,
                'total_conversions' => $totalConversions,
                'conversion_rate'   => $totalClicks > 0 ? round($totalConversions / $totalClicks * 100, 1) : 0,
                'pending_cents'     => (int) ($payoutTotals->pending_cents ?? 0),
                'approved_cents'    => (int) ($payoutTotals->approved_cents ?? 0),
                'released_cents'    => (int) ($payoutTotals->released_cents ?? 0),
                'total_cents'       => (int) ($payoutTotals->total_cents ?? 0),
            ],
            'trend'    => $trend,
            'partners' => $partners,
        ]);
    }
}
