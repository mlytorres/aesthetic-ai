<?php

declare(strict_types=1);

namespace App\Mail\Clinical;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyFollowUpDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Tenant $tenant
     * @param Collection $evaluations
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Collection $evaluations
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->evaluations->count();
        return new Envelope(
            subject: "Action Required: {$count} Patient Follow-up(s) Scheduled for Today",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clinical.follow-up-digest',
        );
    }
}
