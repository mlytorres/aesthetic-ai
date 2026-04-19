<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliatePayoutRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $portalUrl;

    public string $formattedAmount;

    public function __construct(
        public readonly AffiliatePayoutLedger $payout,
        public readonly AffiliatePartner $partner,
        public readonly Tenant $tenant,
    ) {
        $this->portalUrl = route('affiliate.portal.show', [
            'partner' => $partner->id,
            'token'   => $partner->portal_access_token,
        ], absolute: true);

        $this->formattedAmount = $payout->currency.' '.number_format($payout->amount_cents / 100, 2);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payout update from {$this->tenant->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.affiliate-payout-rejected');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
