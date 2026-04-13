<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to clinic owners when their trial is 7 or 1 day(s) away from expiring.
 * Notifiable: the owner User — the tenant is passed for context.
 */
class TrialEndingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $daysRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urgency = $this->daysRemaining === 1 ? 'last day' : "{$this->daysRemaining} days";

        $parsedAppUrl = parse_url(config('app.url'));
        $baseHost = $parsedAppUrl['host'] ?? 'aesthetic-ai.test';
        $scheme = $parsedAppUrl['scheme'] ?? 'https';
        $port = isset($parsedAppUrl['port']) ? ':'.$parsedAppUrl['port'] : '';
        $billingUrl = "{$scheme}://{$this->tenant->slug}.{$baseHost}{$port}/clinic/billing";

        return (new MailMessage)
            ->subject("Your free trial ends in {$urgency} — {$this->tenant->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your 14-day free trial for **{$this->tenant->name}** ends in **{$urgency}**.")
            ->line('After the trial ends, patient intake and your clinic dashboard will be paused until you choose a plan.')
            ->action('Choose a plan now', $billingUrl)
            ->line('Questions? Reply to this email — we\'re happy to help.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
