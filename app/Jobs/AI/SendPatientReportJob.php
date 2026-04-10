<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Mail\PatientReportMail;
use App\Models\Evaluation;
use App\Services\PatientReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the patient-facing Beauty Roadmap PDF email.
 *
 * Dispatched after GenerateBasicRecommendationsJob completes (evaluation = 'complete').
 * The PDF is generated once and attached to the email; a secure download link is also
 * included gated by the evaluation token.
 *
 * PHI: email is sent to the patient's own address. Only first name is used in the body.
 * No clinical scores, coordinator notes, or full PHI are included.
 *
 * Queued on the 'notifications' queue — separate from the AI queue so delivery delays
 * never affect the core AI pipeline.
 */
class SendPatientReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(PatientReportService $reportService): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::with(['tenant', 'patient'])
            ->findOrFail($this->evaluationId);

        $patient = $evaluation->patient;

        if (! $patient) {
            Log::warning('SendPatientReportJob: no patient on evaluation', [
                'evaluation_id' => $this->evaluationId,
            ]);

            return;
        }

        $patientEmail = $patient->email_encrypted;

        if (blank($patientEmail) || ! filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('SendPatientReportJob: patient has no valid email', [
                'evaluation_id' => $this->evaluationId,
            ]);

            return;
        }

        // Build the secure download URL (no auth required — gated by token).
        $tenant = $evaluation->tenant;
        $appUrl = config('app.url');
        $appDomain = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';
        $tenantBase = $tenant
            ? "{$scheme}://{$tenant->slug}.{$appDomain}"
            : $appUrl;

        $reportUrl = $tenantBase.'/intake/evaluations/'.$evaluation->secure_token.'/report';

        // Generate PDF — catch failures so a rendering error never silences the email.
        $pdfBytes = null;
        $filename = $reportService->filename($evaluation);

        try {
            $pdfBytes = $reportService->generateBytes($evaluation);
        } catch (\Throwable $e) {
            Log::warning('SendPatientReportJob: PDF generation failed, sending email without attachment', [
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);
        }

        Mail::to($patientEmail)->send(new PatientReportMail(
            evaluation: $evaluation,
            reportUrl: $reportUrl,
            reportPdfBytes: $pdfBytes,
            reportFilename: $filename,
        ));

        Log::info('SendPatientReportJob: patient report sent', [
            'evaluation_id' => $this->evaluationId,
            'pdf_attached' => $pdfBytes !== null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendPatientReportJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error' => $e->getMessage(),
        ]);
    }
}
