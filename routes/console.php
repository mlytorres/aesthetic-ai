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
