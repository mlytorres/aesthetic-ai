<?php

declare(strict_types=1);

namespace App\Providers;

use App\Facades\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Sentry\State\Scope;

use function Sentry\configureScope;

/**
 * Enriches Sentry error reports with user and tenant context.
 *
 * Attaches coordinator identity and clinic slug to every Sentry event so
 * issues can be filtered by tenant or user in the Sentry dashboard.
 *
 * Context is set lazily via a terminating callback so it is only populated
 * after middleware (auth + tenant) has run — never before.
 */
class SentryContextServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        // Run just before the response is sent — auth & tenant middleware
        // have already executed so context is fully resolved.
        $this->app->terminating(static function (): void {
            $user = Auth::user();

            configureScope(static function (Scope $scope) use ($user): void {
                if ($user !== null) {
                    $scope->setUser([
                        'id' => $user->id,
                        'email' => $user->email,
                        'role' => $user->role ?? 'unknown',
                    ]);
                }

                if (TenantContext::isSet()) {
                    $scope->setTag('tenant.id', TenantContext::id());
                    $scope->setTag('tenant.slug', TenantContext::get()->slug);
                }
            });
        });
    }
}
