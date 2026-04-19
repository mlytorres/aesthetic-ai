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
     * - affiliate.click           → 60 clicks per minute per token+IP (also covers short_code)
     * - affiliate.portal          → 60 portal visits per minute per token+IP
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

        // Affiliate click tracking — public, unauthenticated. Limit per link-identifier + IP so a
        // single bad actor cannot run up click counts (skews attribution + potential payout fraud).
        // Covers both /intake/a/{token} and /intake/s/{code}.
        RateLimiter::for('affiliate.click', function (Request $request): Limit {
            $identifier = $request->route('token')
                ?? $request->route('code')
                ?? $request->ip();

            return Limit::perMinute(60)->by($identifier.'|'.$request->ip());
        });

        // Partner portal — signed with partner_id + portal_access_token. Limit per token+IP.
        RateLimiter::for('affiliate.portal', function (Request $request): Limit {
            $token = $request->route('token') ?? $request->ip();

            return Limit::perMinute(60)->by($token.'|'.$request->ip());
        });

        // Magic login links are sensitive auth entrypoints; keep enumeration/noise low.
        RateLimiter::for('magic-link', function (Request $request): Limit {
            $token = $request->route('token') ?? 'unknown';

            return Limit::perMinute(10)->by($token.'|'.$request->ip());
        });

        // Public widget loader can be scraped aggressively by bots.
        RateLimiter::for('widget-loader', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

        // External API v1 limit per tenant (or user) and IP.
        RateLimiter::for('api.v1', function (Request $request): Limit {
            $identifier = $request->user()?->tenant_id
                ?? $request->user()?->id
                ?? $request->header('X-Clinic-ID')
                ?? $request->ip();

            return Limit::perMinute(120)->by($identifier.'|'.$request->ip());
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
