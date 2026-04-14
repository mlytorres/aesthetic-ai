<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Mail\Billing\UsageOverageWarningMail;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Checks a tenant's evaluation usage against their plan limit.
 * If they have exactly reached the 80% threshold and haven't been notified
 * this month, an email is sent to the clinic owner(s).
 */
class CheckTenantUsageCapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $tenantId) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $limit = $tenant->plan?->max_evaluations_mo;

        // Pro plans (unlimited) or missing plan
        if (! $limit) {
            return;
        }

        $count = $tenant->currentMonthEvalCount();
        $threshold = (int) floor($limit * 0.8);

        if ($count === $threshold) {
            $currentMonth = now()->format('Y-m');
            $settings = $tenant->settings ?? [];

            // Prevent duplicate emails if they hover around the threshold
            if (($settings['last_overage_notification_month'] ?? null) === $currentMonth) {
                return;
            }

            $owners = $tenant->users()->where('role', 'owner')->get();

            if ($owners->isEmpty()) {
                Log::warning('CheckTenantUsageCapJob: Usage threshold reached but no owner found.', [
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }

            Mail::to($owners)->send(new UsageOverageWarningMail($tenant, $count, $limit));

            // Mark as notified for this month
            $settings['last_overage_notification_month'] = $currentMonth;
            $tenant->update(['settings' => $settings]);

            Log::info('CheckTenantUsageCapJob: Sent 80% overage warning to owners.', [
                'tenant_id' => $tenant->id,
                'count' => $count,
                'limit' => $limit,
            ]);
        }
    }
}
