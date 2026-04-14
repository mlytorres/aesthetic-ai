<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Patient-facing email delivering their Beauty Roadmap PDF.
 *
 * Sent after AI analysis completes (evaluation status = complete).
 * Contains only first name + procedure — no sensitive PHI in the email body.
 *
 * The Beauty Roadmap PDF is attached when $reportPdfBytes is provided.
 * Also includes a secure download link gated by the evaluation token.
 */
class PatientReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $firstName;

    public string $procedureLabel;

    public string $clinicName;

    public string $reportUrl;

    public string $secureToken;

    public function __construct(
        public readonly Evaluation $evaluation,
        string $reportUrl,
        private readonly ?string $reportPdfBytes = null,
        private readonly string $reportFilename = 'your-beauty-roadmap.pdf',
    ) {
        $patient = $evaluation->patient;
        $tenant = $evaluation->tenant;
        $procedure = $evaluation->procedure_slug;

        $fullName = $patient?->name_encrypted ?? null;
        $this->firstName = $fullName
            ? ucfirst(explode(' ', trim($fullName))[0])
            : 'there';

        $this->procedureLabel = ucwords(str_replace(['-', '_'], ' ', $procedure));
        $this->clinicName = $tenant?->name ?? config('app.name');
        $this->reportUrl = $reportUrl;
        $this->secureToken = $evaluation->secure_token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->procedureLabel} Beauty Roadmap — {$this->clinicName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-report',
        );
    }

    /**
     * Attach the Beauty Roadmap PDF.
     *
     * @return Attachment[]
     */
    public function attachments(): array
    {
        if ($this->reportPdfBytes === null) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $this->reportPdfBytes,
                $this->reportFilename,
            )->withMime('application/pdf'),
        ];
    }
}
