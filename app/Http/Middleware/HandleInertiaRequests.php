<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Handle the incoming request — bridge Laravel session flash into
     * Inertia's native flash channel so router.on('flash') fires on
     * every response, including preserveScroll patch requests.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Read session flash before the response is built (session is
        // reflashed on redirect, so we must read it here, not in share()).
        $toast = null;

        if ($explicit = $request->session()->get('toast')) {
            $toast = $explicit;
        } else {
            foreach (['success', 'error', 'warning', 'info'] as $type) {
                if ($message = $request->session()->get("flash.{$type}")) {
                    $toast = ['type' => $type, 'message' => $message];
                    break;
                }
            }
        }

        // Push into Inertia's native flash channel (page.flash) so
        // router.on('flash') fires and useFlashToast picks it up.
        if ($toast !== null) {
            Inertia::flash('toast', $toast);
        }

        return parent::handle($request, $next);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Non-null when a super admin is currently impersonating a tenant user.
            'impersonating' => session()->has('impersonating_id')
                ? ['as' => $request->user()?->name]
                : null,
            // Tenant ID for the Reverb private channel subscription.
            // Null for super-admins who are not scoped to a tenant.
            'tenantId' => $request->user()?->tenant_id,
            'features' => [
                'affiliateProgram' => fn () => TenantContext::isSet() && TenantContext::get()->hasAffiliateProgram(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('flash.success'),
                'error' => fn () => $request->session()->get('flash.error'),
                'warning' => fn () => $request->session()->get('flash.warning'),
                'info' => fn () => $request->session()->get('flash.info'),
            ],
        ];
    }
}
