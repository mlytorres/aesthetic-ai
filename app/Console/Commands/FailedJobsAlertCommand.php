<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;

/**
 * Checks for failed jobs since the last run and reports them to Sentry + logs.
 *
 * Runs every 30 minutes via the scheduler. Uses a simple cache key to track
 * the last checked timestamp so it only reports newly failed jobs.
 */
class FailedJobsAlertCommand extends Command
{
    protected $signature = 'queue:failed-jobs-alert';

    protected $description = 'Alert on any newly failed queue jobs since last check';

    public function handle(): int
    {
        $cacheKey = 'failed_jobs_last_checked_at';
        $lastChecked = cache()->get($cacheKey, now()->subMinutes(35));

        $newFailures = DB::table('failed_jobs')
            ->where('failed_at', '>=', $lastChecked)
            ->orderBy('failed_at')
            ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at']);

        cache()->put($cacheKey, now(), now()->addHours(2));

        if ($newFailures->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($newFailures as $job) {
            $payload = json_decode($job->payload, true);
            $jobName = $payload['displayName'] ?? 'Unknown';

            Log::error('Failed queue job detected', [
                'job' => $jobName,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'uuid' => $job->uuid,
                'exception' => substr($job->exception, 0, 500),
            ]);

            // Report to Sentry so failures surface in error tracking.
            if (app()->bound('sentry')) {
                \Sentry\withScope(function (Scope $scope) use ($jobName, $job): void {
                    $scope->setTag('queue', $job->queue);
                    $scope->setContext('failed_job', [
                        'job' => $jobName,
                        'uuid' => $job->uuid,
                        'failed_at' => $job->failed_at,
                    ]);
                    \Sentry\captureMessage("Failed queue job: {$jobName}", Severity::error());
                });
            }
        }

        $this->warn("⚠ {$newFailures->count()} failed job(s) detected and reported.");

        return self::SUCCESS;
    }
}
