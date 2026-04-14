<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the clinic Owner when monthly evaluation usage crosses 80% of the plan limit.
 * Fires once per month per tenant (cached to avoid repeat sends).
 */
class UsageOverageAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $currentCount,
        public readonly int $limit,
        public readonly int $percentUsed,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ You've used {$this->percentUsed}% of your monthly evaluations — {$this->tenant->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.usage-overage-alert',
        );
    }
}
