<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AffiliatePartner;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email sent to an influencer / creator when a clinic adds them
 * to their affiliate program. Contains their magic-link portal URL plus a
 * reminder to accept the program terms before their tracking link goes live.
 */
class AffiliatePartnerInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $portalUrl;

    public function __construct(
        public readonly AffiliatePartner $partner,
        public readonly Tenant $tenant,
    ) {
        $this->portalUrl = route('affiliate.portal.show', [
            'partner' => $partner->id,
            'token' => $partner->portal_access_token,
        ], absolute: true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're in — {$this->tenant->name} affiliate program",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affiliate-partner-invite',
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
