<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AffiliatePayoutLedger;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateFraudQueueController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function index(): Response
    {
        $tenant = TenantContext::get();

        $flagged = AffiliatePayoutLedger::query()
            ->where('tenant_id', $tenant->id)
            ->where('fraud_review_required', true)
            ->with([
                'partner:id,name,handle,platform',
                'campaign:id,name',
                'evaluation:id,secure_token,status,completed_at',
            ])
            ->orderByRaw('CASE WHEN fraud_reviewed_at IS NULL THEN 0 ELSE 1 END')
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
                'metadata',
                'fraud_review_required',
                'fraud_reviewed_at',
                'fraud_reviewed_by_user_id',
                'created_at',
            ])
            ->map(function (AffiliatePayoutLedger $payout): array {
                $data = $payout->toArray();
                $data['fraud_flags'] = $payout->metadata['fraud_flags'] ?? [];
                $data['fraud_risk'] = $payout->metadata['fraud_risk'] ?? 'low';
                unset($data['metadata']);

                return $data;
            });

        $pendingCount = $flagged->filter(fn (array $row) => $row['fraud_reviewed_at'] === null)->count();

        return Inertia::render('clinic/affiliate-fraud-queue', [
            'flaggedPayouts' => $flagged,
            'stats' => [
                'total_flagged' => $flagged->count(),
                'pending_review' => $pendingCount,
                'cleared' => $flagged->filter(fn (array $row) => $row['fraud_reviewed_at'] !== null && $row['status'] !== AffiliatePayoutLedger::STATUS_REJECTED)->count(),
                'confirmed_fraud' => $flagged->filter(fn (array $row) => $row['status'] === AffiliatePayoutLedger::STATUS_REJECTED && $row['fraud_reviewed_at'] !== null)->count(),
            ],
            'serverNow' => now()->toIso8601String(),
        ]);
    }

    public function clear(Request $request, string $affiliatePayoutLedger): RedirectResponse
    {
        $payoutLedger = $this->resolvePayoutLedger($affiliatePayoutLedger);

        if (! $payoutLedger->isFraudReviewPending()) {
            return back()->with('flash.error', 'This payout has already been fraud-reviewed.');
        }

        DB::transaction(function () use ($payoutLedger, $request): void {
            $payoutLedger->update([
                'fraud_reviewed_at' => now(),
                'fraud_reviewed_by_user_id' => $request->user()?->id,
            ]);

            $this->auditLog->record('affiliate.fraud_queue.cleared', $payoutLedger, [
                'payout_id' => $payoutLedger->id,
                'fraud_flags' => $payoutLedger->metadata['fraud_flags'] ?? [],
            ]);
        });

        return back()->with('flash.success', 'Fraud flag cleared. Payout can now proceed through normal review.');
    }

    public function reject(Request $request, string $affiliatePayoutLedger): RedirectResponse
    {
        $payoutLedger = $this->resolvePayoutLedger($affiliatePayoutLedger);

        if (! $payoutLedger->isFraudReviewPending()) {
            return back()->with('flash.error', 'This payout has already been fraud-reviewed.');
        }

        DB::transaction(function () use ($payoutLedger, $request): void {
            $payoutLedger->update([
                'status' => AffiliatePayoutLedger::STATUS_REJECTED,
                'fraud_reviewed_at' => now(),
                'fraud_reviewed_by_user_id' => $request->user()?->id,
                'reviewed_by_user_id' => $request->user()?->id,
                'rejection_reason' => 'Confirmed fraud via velocity review queue.',
            ]);

            $this->auditLog->record('affiliate.fraud_queue.confirmed_fraud', $payoutLedger, [
                'payout_id' => $payoutLedger->id,
                'fraud_flags' => $payoutLedger->metadata['fraud_flags'] ?? [],
            ]);
        });

        return back()->with('flash.success', 'Payout rejected as confirmed fraud.');
    }

    private function resolvePayoutLedger(string $id): AffiliatePayoutLedger
    {
        $payoutLedger = AffiliatePayoutLedger::findOrFail($id);

        abort_unless($payoutLedger->tenant_id === TenantContext::id(), 404);
        abort_unless($payoutLedger->fraud_review_required, 404);

        return $payoutLedger;
    }
}
