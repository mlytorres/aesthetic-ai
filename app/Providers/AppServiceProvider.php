<?php

declare(strict_types=1);

namespace App\Providers;

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
        // TenantContext is a per-request singleton — holds the resolved tenant
        $this->app->singleton(TenantContext::class);

        // AuditLog depends on the current request for IP/user-agent
        $this->app->singleton(\App\Services\AuditLog::class);
        $this->app->singleton(\App\Services\SecureFileService::class);
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
