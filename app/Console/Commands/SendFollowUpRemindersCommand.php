<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Clinical\DailyFollowUpDigestMail;
use App\Models\Evaluation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendFollowUpRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:send-follow-up-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a daily digest to clinic staff of all patients scheduled for follow-up today.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Avoid sending closed statuses
        $terminalStatuses = [
            Evaluation::STATUS_BOOKED,
            Evaluation::STATUS_NO_SHOW,
            Evaluation::STATUS_NOT_A_FIT,
            Evaluation::STATUS_FAILED,
        ];

        // Fetch all active evaluations where the follow_up_at date matches today
        $evaluations = Evaluation::withoutGlobalScopes()
            ->with(['tenant', 'patient', 'procedure'])
            ->whereDate('follow_up_at', today())
            ->whereNotIn('status', $terminalStatuses)
            ->whereNotNull('tenant_id')
            ->get();

        if ($evaluations->isEmpty()) {
            $this->info('No follow-up reminders scheduled for today.');
            return self::SUCCESS;
        }

        // Group by Tenant
        $evaluationsByTenant = $evaluations->groupBy('tenant_id');
        $emailsSent = 0;

        foreach ($evaluationsByTenant as $tenantId => $tenantEvaluations) {
            /** @var Tenant $tenant */
            $tenant = $tenantEvaluations->first()->tenant;

            $recipients = $this->resolveRecipients($tenant);

            if (empty($recipients)) {
                Log::warning('SendFollowUpRemindersCommand: No recipients found for tenant.', [
                    'tenant_id' => $tenantId,
                ]);
                continue;
            }

            Mail::to($recipients)->send(new DailyFollowUpDigestMail($tenant, $tenantEvaluations));
            $emailsSent++;

            Log::info('SendFollowUpRemindersCommand: Sent digest.', [
                'tenant_id' => $tenant->id,
                'evaluations_count' => $tenantEvaluations->count(),
            ]);
        }

        $this->info("Successfully sent $emailsSent daily digest(s).");

        return self::SUCCESS;
    }

    /**
     * Determine who gets the email for a given clinic.
     * Priority 1: explicitly mapped coordinator_emails.
     * Priority 2: users in the database with clinical actors role (coordinator, admin, owner).
     */
    private function resolveRecipients(Tenant $tenant): array
    {
        $settingsRecipients = $tenant->settings['coordinator_emails'] ?? [];

        if (! empty($settingsRecipients)) {
            return $settingsRecipients;
        }

        // Fallback to active users
        return User::where('tenant_id', $tenant->id)
            ->whereIn('role', [
                User::ROLE_COORDINATOR,
                User::ROLE_ADMIN,
                User::ROLE_OWNER,
            ])
            ->pluck('email')
            ->toArray();
    }
}
