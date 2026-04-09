<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Mail\NewEvaluationMail;
use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies coordinator(s) that a new evaluation has completed AI analysis.
 *
 * Recipients: tenant.settings.coordinator_emails (configured in Clinic Settings).
 * Falls back to all users with role = 'coordinator' or 'owner' for this tenant.
 *
 * Queued on the 'notifications' queue — separate from the 'default' AI queue
 * so a slow email provider never blocks AI processing.
 *
 * In local dev with MAIL_MAILER=log, emails go to storage/logs/laravel.log.
 */
class NotifyClinicNewEvaluationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(): void
    {
        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::withoutGlobalScopes()
            ->with(['tenant', 'patient'])
            ->findOrFail($this->evaluationId);

        $tenant = $evaluation->tenant;

        if (!$tenant) {
            Log::warning('NotifyClinicNewEvaluationJob: tenant not found', [
                'evaluation_id' => $this->evaluationId,
            ]);
            return;
        }

        // Resolve coordinator email addresses from tenant settings
        $recipients = $this->resolveRecipients($tenant, $evaluation);

        if (empty($recipients)) {
            Log::info('NotifyClinicNewEvaluationJob: no recipients configured', [
                'evaluation_id' => $this->evaluationId,
                'tenant_id'     => $tenant->id,
            ]);
            return;
        }

        foreach ($recipients as $email) {
            Mail::to($email)->send(new NewEvaluationMail($evaluation));
        }

        Log::info('NotifyClinicNewEvaluationJob: notifications sent', [
            'evaluation_id' => $this->evaluationId,
            'recipients'    => count($recipients),
        ]);
    }

    /**
     * @return array<string>
     */
    private function resolveRecipients(\App\Models\Tenant $tenant, Evaluation $evaluation): array
    {
        // Priority 1: explicitly configured coordinator emails
        $configured = $tenant->settings['coordinator_emails'] ?? [];

        if (!empty($configured) && is_array($configured)) {
            return $configured;
        }

        // Priority 2: all coordinator + owner users on this tenant
        return \App\Models\User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['coordinator', 'owner', 'admin'])
            ->pluck('email')
            ->filter()
            ->values()
            ->toArray();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyClinicNewEvaluationJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error'         => $e->getMessage(),
        ]);
        // Non-fatal — don't update evaluation status for notification failures
    }
}
