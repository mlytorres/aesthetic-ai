<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\HandleStripeSubscriptionUpdated;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\SecureFileService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

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
        // Tenant is the Stripe customer — each clinic has its own subscription.
        Cashier::useCustomerModel(Tenant::class);

        // Sync plan_id whenever Stripe fires a subscription webhook.
        Event::listen(WebhookReceived::class, HandleStripeSubscriptionUpdated::class);

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
