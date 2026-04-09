<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MagicLink;
use Illuminate\Console\Command;

/**
 * Deletes expired or used magic link tokens.
 *
 * Should be scheduled to run hourly to keep the table lean.
 * Register in routes/console.php:
 *
 *   Schedule::command('magic-links:prune')->hourly();
 *
 * Safe to run at any time — only removes tokens that are already
 * expired or consumed, never active tokens.
 */
class PruneMagicLinksCommand extends Command
{
    protected $signature   = 'magic-links:prune';
    protected $description = 'Delete expired and used magic link tokens';

    public function handle(): int
    {
        $expired = MagicLink::withoutGlobalScopes()
            ->where('expires_at', '<', now())
            ->delete();

        $used = MagicLink::withoutGlobalScopes()
            ->whereNotNull('used_at')
            ->delete();

        $total = $expired + $used;

        $this->info("Pruned {$total} magic link(s) ({$expired} expired, {$used} used).");

        return self::SUCCESS;
    }
}
