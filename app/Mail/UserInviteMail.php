<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email sent to a new clinic user when their account is created.
 *
 * Contains their login URL, email, and temporary password.
 * The recipient should change their password after first login.
 */
class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Full login URL for the tenant's subdomain. */
    public string $loginUrl;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $temporaryPassword,
    ) {
        $appDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';

        $this->loginUrl = "{$scheme}://{$tenant->slug}.{$appDomain}/login";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to {$this->tenant->name} on SymetriHealth",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invite',
        );
    }

    /** @return array<int, \Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
