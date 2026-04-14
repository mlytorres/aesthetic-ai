<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\UsageOverageAlertMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a usage alert to the clinic Owner when monthly evaluations cross 80% of the plan limit.
 *
 * Idempotent: the alert is cached for the remainder of the billing month so it
 * only fires once per tenant per calendar month, regardless of how many evaluations
 * are created after the threshold is crossed.
 */
class SendUsageOverageAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly int $tenantId) {}

    public function handle(): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $limit = $tenant->plan?->max_evaluations_mo;

        if ($limit === null || $limit === 0) {
            return; // unlimited plan — no alert needed
        }

        $currentCount = $tenant->currentMonthEvalCount();
        $percentUsed = (int) round(($currentCount / $limit) * 100);

        // Only send once per tenant per calendar month.
        $cacheKey = "usage_alert_sent:{$this->tenantId}:".now()->format('Y-m');
        if (Cache::has($cacheKey)) {
            return;
        }

        // Alert at 80% or above — but only if not already at the hard limit
        // (billing gate handles the blocked state at 100%).
        if ($percentUsed < 80) {
            return;
        }

        // Find the clinic Owner to send the alert to.
        $owner = User::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('role', 'owner')
            ->first();

        if ($owner === null) {
            Log::warning('UsageOverageAlert: no owner found for tenant', ['tenant_id' => $this->tenantId]);

            return;
        }

        Mail::to($owner->email)
            ->send(new UsageOverageAlertMail(
                tenant: $tenant,
                currentCount: $currentCount,
                limit: $limit,
                percentUsed: $percentUsed,
            ));

        // Cache until end of current month so the alert isn't repeated.
        Cache::put($cacheKey, true, now()->endOfMonth());
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendUsageOverageAlertJob failed', [
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);
    }
}
