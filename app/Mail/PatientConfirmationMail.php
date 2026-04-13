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
 * Immediate submission confirmation sent to the patient.
 *
 * Dispatched synchronously-queued right after evaluation is persisted —
 * before the AI pipeline runs. Lets the patient know their submission was
 * received and sets expectations on next steps.
 *
 * PHI policy: only first name + procedure label are included in the email body.
 * No scores, photos, or clinical data are transmitted.
 */
class PatientConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $firstName;

    public string $procedureLabel;

    public string $clinicName;

    public string $secureToken;

    public function __construct(public readonly Evaluation $evaluation)
    {
        $patient = $evaluation->patient;
        $tenant = $evaluation->tenant;

        $fullName = $patient?->name_encrypted ?? null;
        $this->firstName = $fullName
            ? ucfirst(explode(' ', trim($fullName))[0])
            : 'there';

        $this->procedureLabel = ucwords(str_replace(['-', '_'], ' ', $evaluation->procedure_slug));
        $this->clinicName = $tenant?->name ?? config('app.name');
        $this->secureToken = $evaluation->secure_token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We received your {$this->procedureLabel} evaluation — {$this->clinicName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
