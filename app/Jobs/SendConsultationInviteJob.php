<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ScheduleConsultationMail;
use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the patient their video consultation invite via email (and SMS if opted in).
 *
 * Dispatched immediately after a consultation is scheduled by a coordinator.
 * Runs on the 'notifications' queue.
 */
class SendConsultationInviteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly string $consultationId) {}

    public function handle(): void
    {
        $consultation = Consultation::with('evaluation.patient', 'evaluation.tenant')
            ->find($this->consultationId);

        if ($consultation === null) {
            return;
        }

        if ($consultation->status === Consultation::STATUS_CANCELLED) {
            return;
        }

        $patient = $consultation->evaluation?->patient;

        if ($patient === null) {
            Log::warning('SendConsultationInviteJob: no patient for consultation', [
                'consultation_id' => $this->consultationId,
            ]);

            return;
        }

        // ── Email ─────────────────────────────────────────────────────────────
        if (! blank($patient->email)) {
            Mail::to($patient->email)->send(new ScheduleConsultationMail($consultation));
        }

        // ── SMS (if patient opted in during intake) ───────────────────────────
        $this->sendSmsIfOptedIn($consultation, $patient->phone ?? '');
    }

    private function sendSmsIfOptedIn(Consultation $consultation, string $phone): void
    {
        if (blank($phone)) {
            return;
        }

        if (blank(config('services.twilio.sid'))) {
            return;
        }

        $optedInSms = (bool) ($consultation->evaluation?->consent_data['opt_in_sms'] ?? false);

        if (! $optedInSms) {
            return;
        }

        $joinUrl = route('consult.join', $consultation->token);
        $clinic = $consultation->evaluation?->tenant?->name ?? config('app.name');
        $dateStr = $consultation->scheduled_at->format('M j \a\t g:i A');

        $body = "{$clinic}: Your video consultation is scheduled for {$dateStr}. Join here: {$joinUrl}. Reply STOP to opt out.";

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $phone,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            Log::warning('SendConsultationInviteJob: SMS failed', [
                'consultation_id' => $this->consultationId,
                'status' => $response->status(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendConsultationInviteJob failed', [
            'consultation_id' => $this->consultationId,
            'error' => $e->getMessage(),
        ]);
    }
}
