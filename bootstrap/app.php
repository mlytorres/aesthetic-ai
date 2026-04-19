<?php

use App\Http\Middleware\AllowFramesMiddleware;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireAffiliateProgram;
use App\Http\Middleware\RequireBillingAccess;
use App\Http\Middleware\RequireExecutedBaa;
use App\Http\Middleware\RequirePrivilegedTwoFactor;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireSuperAdminTwoFactor;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Cashier's Stripe webhook endpoint must not be CSRF-protected —
        // Stripe signs the payload with a secret instead.
        $middleware->validateCsrfTokens(except: ['stripe/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeadersMiddleware::class,
        ]);

        // Named middleware aliases — use in route groups
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'super-admin' => EnsureSuperAdmin::class,
            'role' => RequireRole::class,
            'plan.limits' => EnforcePlanLimits::class,
            'billing.access' => RequireBillingAccess::class,
            'allow.frames' => AllowFramesMiddleware::class,
            'affiliate.access' => RequireAffiliateProgram::class,
            'baa.intake' => RequireExecutedBaa::class,
            'privileged.2fa' => RequirePrivilegedTwoFactor::class,
            'super-admin.2fa' => RequireSuperAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (! app()->environment('local') && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                return inertia('error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })
    ->create();
