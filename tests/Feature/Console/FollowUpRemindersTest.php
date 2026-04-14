<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Mail\Clinical\DailyFollowUpDigestMail;
use App\Models\Evaluation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FollowUpRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_daily_digest_for_today_follow_ups(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create([
            'settings' => ['coordinator_emails' => ['coordinator@example.com']],
        ]);

        // Matches today (Should send)
        Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'follow_up_at' => now(),
            'status' => Evaluation::STATUS_CONTACTED,
        ]);

        // Matches tomorrow (Should ignore)
        Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'follow_up_at' => now()->addDay(),
            'status' => Evaluation::STATUS_ANALYZING,
        ]);

        // Matches today but terminal status (Should ignore)
        Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'follow_up_at' => now(),
            'status' => Evaluation::STATUS_BOOKED,
        ]);

        $this->artisan('crm:send-follow-up-reminders')->assertSuccessful();

        Mail::assertSent(DailyFollowUpDigestMail::class, function (DailyFollowUpDigestMail $mail) use ($tenant) {
            return $mail->hasTo('coordinator@example.com')
                && $mail->tenant->id === $tenant->id
                && $mail->evaluations->count() === 1;
        });
    }

    public function test_it_does_nothing_if_no_evaluations_match(): void
    {
        Mail::fake();

        $this->artisan('crm:send-follow-up-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
