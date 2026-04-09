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
 * Sent individually per recipient — each email contains a unique magic link
 * (one-time, 15-min expiry) for direct authenticated access to the evaluation.
 *
 * PHI: email contains only patient first name + procedure type.
 * Full PHI remains behind the authenticated coordinator portal.
 */
class NewEvaluationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $procedure;
    public int|null $leadScore;
    public string $priority;
    public string $magicUrl;
    public string $patientFirstName;
    public string $clinicName;

    public function __construct(
        public readonly Evaluation $evaluation,
        string $magicUrl,
    ) {
        $procedure = $evaluation->procedure_slug;
        $patient   = $evaluation->patient;
        $tenant    = $evaluation->tenant;

        $this->procedure       = ucwords(str_replace(['-', '_'], ' ', $procedure));
        $this->leadScore       = $evaluation->lead_score;
        $this->priority        = ucfirst($evaluation->priority ?? 'standard');
        $this->clinicName      = $tenant?->name ?? 'Your Clinic';
        $this->magicUrl        = $magicUrl;

        // Use only the first name — partial PHI exposure in email body
        $fullName = $patient?->name_encrypted ?? null;
        $this->patientFirstName = $fullName
            ? explode(' ', trim($fullName))[0]
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
