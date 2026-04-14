<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled maintenance ──────────────────────────────────────────────────────

// Prune expired and consumed magic links hourly (keeps the table lean)
Schedule::command('magic-links:prune')->hourly();

// Trial ending reminders — fires daily at 9 AM.
// Sends to clinic owners whose trial expires in exactly 7 or 1 day(s).
Schedule::command('billing:send-trial-reminders')->dailyAt('09:00');

// CRM Patient Follow-Up Reminders — fires daily at 8 AM.
// Bundles up all patients due for a follow-up today and sends a digest to the clinic.
Schedule::command('crm:send-follow-up-reminders')->dailyAt('08:00');

