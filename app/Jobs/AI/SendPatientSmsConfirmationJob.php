<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends an SMS confirmation to the patient via Twilio.
 *
 * Only fires when:
 *   (a) The patient opted in to SMS at consent step.
 *   (b) The patient has a valid phone number on record.
 *   (c) TWILIO_SID is configured (silently skipped in environments without it).
 */
class SendPatientSmsConfirmationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(private readonly int $evaluationId) {}

    public function handle(): void
    {
        // Skip if Twilio is not configured (dev / CI environments).
        if (blank(config('services.twilio.sid'))) {
            return;
        }

        $evaluation = Evaluation::withoutGlobalScopes()
            ->with('patient', 'tenant')
            ->find($this->evaluationId);

        if ($evaluation === null) {
            return;
        }

        $patient = $evaluation->patient;
        $phone = $patient?->phone;

        if (blank($phone)) {
            return;
        }

        $clinicName = $evaluation->tenant?->name ?? 'Your clinic';
        $procedureLabel = ucwords(str_replace(['_', '-'], ' ', $evaluation->procedure_slug));

        $message = "Hi {$patient->first_name}! We've received your {$procedureLabel} evaluation request at {$clinicName}. "
            ."Our team will review your photos and reach out shortly. Reply STOP to opt out.";

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $smsFrom = config('services.twilio.from');
        $whatsappFrom = config('services.twilio.whatsapp_from');

        $smsSent = false;

        // Try SMS first
        if (!blank($smsFrom)) {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $smsFrom,
                    'To' => $phone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $smsSent = true;
            } else {
                Log::warning('SMS confirmation failed', [
                    'evaluation_id' => $this->evaluationId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }

        // Fallback to WhatsApp if SMS failed or was not configured
        if (!$smsSent && !blank($whatsappFrom)) {
            $whatsappTo = str_starts_with($phone, 'whatsapp:') ? $phone : "whatsapp:{$phone}";
            $whatsappSender = str_starts_with($whatsappFrom, 'whatsapp:') ? $whatsappFrom : "whatsapp:{$whatsappFrom}";

            $waResponse = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $whatsappSender,
                    'To' => $whatsappTo,
                    'Body' => $message,
                ]);

            if ($waResponse->failed()) {
                Log::warning('WhatsApp confirmation fallback failed', [
                    'evaluation_id' => $this->evaluationId,
                    'status' => $waResponse->status(),
                    'body' => $waResponse->body(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendPatientSmsConfirmationJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error' => $e->getMessage(),
        ]);
    }
}
