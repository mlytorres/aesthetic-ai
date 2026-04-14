<?php

declare(strict_types=1);

namespace App\Mail\Billing;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UsageOverageWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $currentUsage,
        public readonly int $limit
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: 80% Usage Limit Reached',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing.usage-overage',
        );
    }
}
