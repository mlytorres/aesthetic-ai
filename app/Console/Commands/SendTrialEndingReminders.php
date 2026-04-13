<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TrialEndingReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:send-trial-reminders')]
#[Description('Send trial-ending reminder emails at 7 days and 1 day before expiry')]
class SendTrialEndingReminders extends Command
{
    /**
     * Execute the console command.
     *
     * Queries tenants whose trial ends in exactly 7 or 1 day(s), have no active
     * subscription, and are not on the FREE plan. Sends a reminder to each owner.
     */
    public function handle(): int
    {
        $reminders = [7, 1]; // days before expiry to fire reminders

        foreach ($reminders as $days) {
            $start = now()->addDays($days)->startOfDay();
            $end = now()->addDays($days)->endOfDay();

            $tenants = Tenant::with(['plan', 'users'])
                ->whereBetween('trial_ends_at', [$start, $end])
                ->whereNull('stripe_id') // no Stripe subscription yet
                ->whereDoesntHave('plan', fn ($q) => $q->where('slug', 'free'))
                ->get();

            foreach ($tenants as $tenant) {
                /** @var User|null $owner */
                $owner = $tenant->users
                    ->where('role', User::ROLE_OWNER)
                    ->sortBy('created_at')
                    ->first();

                if ($owner === null) {
                    $this->warn("No owner found for tenant {$tenant->slug} — skipping.");

                    continue;
                }

                $owner->notify(new TrialEndingReminder($tenant, $days));
                $this->info("Sent {$days}-day reminder to {$owner->email} ({$tenant->slug}).");
            }
        }

        return Command::SUCCESS;
    }
}
