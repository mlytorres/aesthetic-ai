<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Mail\NewEvaluationMail;
use App\Models\Evaluation;
use App\Models\MagicLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClinicalBriefService;
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
 * For each recipient a fresh magic link is generated (one-time, 15-min expiry)
 * so they can access the evaluation directly from the email without logging in.
 *
 * Recipients: tenant.settings.coordinator_emails → falls back to all
 * coordinator/owner users on the tenant.
 *
 * The clinical brief PDF is generated once and attached to every outgoing email.
 * If PDF generation fails, the notification is still sent without the attachment.
 *
 * Queued on the 'notifications' queue — separate from the 'default' AI queue
 * so a slow email provider never blocks AI processing.
 *
 * In local dev with MAIL_MAILER=log, emails go to storage/logs/laravel.log.
 */
class NotifyClinicNewEvaluationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(ClinicalBriefService $briefService): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::with(['tenant', 'patient', 'photos'])
            ->findOrFail($this->evaluationId);

        $tenant = $evaluation->tenant;

        if (! $tenant) {
            Log::warning('NotifyClinicNewEvaluationJob: tenant not found', [
                'evaluation_id' => $this->evaluationId,
            ]);

            return;
        }

        $recipients = $this->resolveRecipients($tenant);

        if (empty($recipients)) {
            Log::info('NotifyClinicNewEvaluationJob: no recipients configured', [
                'evaluation_id' => $this->evaluationId,
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        // Build the tenant subdomain base URL — the magic link route requires the tenant
        // middleware, which resolves the tenant from the subdomain. Without it the link
        // would land on the root domain where tenant cannot be resolved.
        $appUrl = config('app.url');
        $appDomain = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';
        $tenantBase = "{$scheme}://{$tenant->slug}.{$appDomain}";

        // Generate the clinical brief PDF once — attached to every recipient's email.
        // Catch exceptions so a PDF generation failure never silences the notification.
        $briefBytes = null;
        $briefFilename = $briefService->filename($evaluation);

        try {
            $briefBytes = $briefService->generateBytes($evaluation);
        } catch (\Throwable $e) {
            Log::warning('NotifyClinicNewEvaluationJob: clinical brief PDF failed, sending without attachment', [
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);
        }

        $sentCount = 0;

        foreach ($recipients as $email) {
            // Generate a fresh one-time magic link per recipient.
            [, $rawToken] = MagicLink::generate($evaluation, $email);

            $magicUrl = $tenantBase.'/magic/'.$rawToken;

            Mail::to($email)->send(new NewEvaluationMail(
                evaluation: $evaluation,
                magicUrl: $magicUrl,
                briefPdfBytes: $briefBytes,
                briefFilename: $briefFilename,
            ));

            $sentCount++;
        }

        Log::info('NotifyClinicNewEvaluationJob: notifications sent', [
            'evaluation_id' => $this->evaluationId,
            'tenant_id' => $tenant->id,
            'recipient_count' => $sentCount,
            'brief_attached' => $briefBytes !== null,
        ]);
    }

    /**
     * Resolve notification recipients.
     *
     * Uses coordinator_emails from tenant settings when configured.
     * Falls back to all coordinator + owner users on the tenant.
     *
     * @return string[]
     */
    private function resolveRecipients(Tenant $tenant): array
    {
        $configured = data_get($tenant->settings, 'coordinator_emails', []);

        if (! empty($configured) && is_array($configured)) {
            return array_filter($configured, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
        }

        return User::where('tenant_id', $tenant->id)
            ->whereIn('role', [User::ROLE_COORDINATOR, User::ROLE_OWNER, User::ROLE_ADMIN])
            ->pluck('email')
            ->all();
    }
}
