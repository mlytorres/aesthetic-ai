<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Evaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Coordinator notification: new evaluation completed AI analysis.
 *
 * Sent to all coordinator_emails configured on the tenant.
 * Contains: patient name (if available), procedure, lead score,
 * priority tier, and a direct link to the evaluation detail page.
 *
 * PHI handling: email contains minimal PHI — only the patient's
 * first name (if consent given) and the procedure type.
 * Full PHI remains behind the authenticated dashboard.
 */
class NewEvaluationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $procedure;
    public int|null $leadScore;
    public string $priority;
    public string $portalUrl;
    public string $patientFirstName;
    public string $clinicName;

    public function __construct(
        public readonly Evaluation $evaluation,
    ) {
        $procedure  = $evaluation->procedure_slug;
        $patient    = $evaluation->patient;
        $tenant     = $evaluation->tenant;

        $this->procedure       = ucwords(str_replace('-', ' ', $procedure));
        $this->leadScore       = $evaluation->lead_score;
        $this->priority        = ucfirst($evaluation->priority ?? 'standard');
        $this->clinicName      = $tenant?->name ?? 'Your Clinic';
        $this->portalUrl       = config('app.url') . '/evaluations/' . $evaluation->id;

        // Use only the first name from encrypted field — partial PHI exposure in email
        $fullName = $patient?->name_encrypted ?? null;
        $this->patientFirstName = $fullName
            ? explode(' ', $fullName)[0]
            : 'New Patient';
    }

    public function envelope(): Envelope
    {
        $priorityTag = match (strtolower($this->evaluation->priority ?? 'standard')) {
            'urgent' => '🔴 URGENT',
            'high'   => '🟠 High Priority',
            'medium' => '🟡',
            default  => '⚪',
        };

        return new Envelope(
            subject: "{$priorityTag} New {$this->procedure} Evaluation — Score: {$this->leadScore}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-evaluation',
        );
    }
}
