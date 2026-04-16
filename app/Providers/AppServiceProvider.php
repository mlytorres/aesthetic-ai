<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\HandleStripeSubscriptionUpdated;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\SecureFileService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiters();
    }

    /**
     * Define named rate limiters for intake and public-facing endpoints.
     *
     * - intake.evaluation.create  → 3 new evaluations per 10 min per IP
     * - intake.evaluation.submit  → 3 final submits per hour per IP
     * - intake.photos             → 15 photo uploads per 10 min per token+IP
     * - intake.quiz               → 30 quiz saves per minute per token+IP
     * - access-requests           → 5 demo/access requests per hour per IP
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('intake.evaluation.create', function (Request $request): Limit {
            return Limit::perMinutes(10, 3)->by($request->ip());
        });

        RateLimiter::for('intake.evaluation.submit', function (Request $request): Limit {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('intake.photos', function (Request $request): Limit {
            // Keyed by token (from route parameter) + IP to prevent cross-token abuse.
            $token = $request->route('token') ?? $request->ip();

            return Limit::perMinutes(10, 15)->by($token.'|'.$request->ip());
        });

        RateLimiter::for('intake.quiz', function (Request $request): Limit {
            $token = $request->route('token') ?? $request->ip();

            return Limit::perMinute(30)->by($token.'|'.$request->ip());
        });

        RateLimiter::for('intake.lead', function (Request $request): Limit {
            $token = $request->route('token') ?? $request->ip();

            return Limit::perMinute(10)->by($token.'|'.$request->ip());
        });

        RateLimiter::for('access-requests', function (Request $request): Limit {
            return Limit::perHour(5)->by($request->ip());
        });
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
