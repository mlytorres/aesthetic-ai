<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

test('it reports newly failed jobs', function () {
    Log::shouldReceive('error')->once()->withArgs(fn ($message, $context) => $message === 'Failed queue job detected' && $context['job'] === 'TestJob'
    );

    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'TestJob']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    $this->artisan('queue:failed-jobs-alert')
        ->assertExitCode(0);
});

test('it handles __PHP_Incomplete_Class in cache gracefully', function () {
    // Manually inject a fake incomplete class into the cache to simulate the error condition
    Cache::put('failed_jobs_last_checked_at', unserialize('O:22:"Carbon\CarbonImmutable":0:{}'));

    $this->artisan('queue:failed-jobs-alert')
        ->assertExitCode(0);

    // It should have overwritten the bad cache key with a valid string
    expect(Cache::get('failed_jobs_last_checked_at'))->toBeString();
});
