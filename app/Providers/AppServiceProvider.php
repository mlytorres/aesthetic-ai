<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AuditLog;
use App\Services\SecureFileService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped bindings reset between requests under Octane — safe alternative to singleton.
        // TenantContext holds the resolved tenant for the current request; must never bleed
        // across requests in long-running Octane workers.
        $this->app->scoped(TenantContext::class);

        // AuditLog captures request IP/user-agent; SecureFileService uses the tenant context.
        // Both must be request-scoped under Octane.
        $this->app->scoped(AuditLog::class);
        $this->app->scoped(SecureFileService::class);
    }


    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
