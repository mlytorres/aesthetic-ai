<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the patient when a video consultation is scheduled.
 *
 * Contains the secure join link, scheduled time, and clinic contact info.
 * PHI policy: patient name + scheduled time only. No clinical data.
 */
class ScheduleConsultationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $patientName;

    public string $clinicName;

    public string $formattedDate;

    public string $durationLabel;

    public string $joinUrl;

    private string $fromName;

    public function __construct(public readonly Consultation $consultation)
    {
        $patient = $consultation->evaluation?->patient;
        $tenant = $consultation->evaluation?->tenant;

        $this->patientName = $patient?->name ?? 'there';
        $this->clinicName = $tenant?->name ?? config('app.name');
        $this->fromName = $tenant?->settings['from_name'] ?? $this->clinicName;
        $this->formattedDate = $consultation->scheduled_at->format('l, F j, Y \a\t g:i A T');
        $this->durationLabel = "{$consultation->duration_minutes} minutes";
        $this->joinUrl = route('consult.join', $consultation->token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->fromName),
            subject: "Your video consultation is scheduled — {$this->clinicName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation-invite',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
