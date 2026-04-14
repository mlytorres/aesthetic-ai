<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Mail\PatientConfirmationMail;
use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an immediate submission confirmation email to the patient.
 *
 * Dispatched right after the evaluation is persisted — before the AI pipeline
 * runs. Confirms receipt and sets expectations on timing.
 *
 * PHI: only the patient's first name and procedure type are included.
 * No scores, photos, or clinical data are transmitted.
 *
 * Queued on the 'notifications' queue, separate from the AI pipeline queue,
 * so a slow mail provider never blocks AI processing.
 */
class SendPatientConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::with(['tenant', 'patient'])
            ->findOrFail($this->evaluationId);

        $patientEmail = $evaluation->patient?->email_encrypted;

        if (blank($patientEmail) || ! filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('SendPatientConfirmationJob: patient has no valid email', [
                'evaluation_id' => $this->evaluationId,
            ]);

            return;
        }

        Mail::to($patientEmail)->send(new PatientConfirmationMail($evaluation));

        Log::info('SendPatientConfirmationJob: confirmation sent', [
            'evaluation_id' => $this->evaluationId,
            'tenant_id' => $evaluation->tenant_id,
        ]);

        $this->sendSmsIfOptedIn($evaluation);
    }

    private function sendSmsIfOptedIn(Evaluation $evaluation): void
    {
        if (! config('features.sms_enabled')) {
            return;
        }

        $optedIn = $evaluation->quiz_answers['_consent']['opt_in_sms'] ?? false;
        if (! $optedIn) {
            return;
        }

        $phone = $evaluation->patient?->phone_encrypted;
        if (blank($phone)) {
            return;
        }

        try {
            $client = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $clinicName = $evaluation->tenant?->name ?? 'SymetriHealth';

            $client->messages->create($phone, [
                'from' => config('services.twilio.from'),
                'body' => "{$clinicName}: We've received your evaluation. Your clinical team is reviewing it now.",
            ]);

            Log::info('SendPatientConfirmationJob: SMS confirmation sent', [
                'evaluation_id' => $this->evaluationId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendPatientConfirmationJob: Twilio SMS failed', [
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendPatientConfirmationJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error' => $e->getMessage(),
        ]);
    }
}
