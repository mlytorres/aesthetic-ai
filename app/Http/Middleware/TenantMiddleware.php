<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the current tenant for every request and loads it into TenantContext.
 *
 * Resolution order:
 *  1. Subdomain       — {slug}.aesthetic-ai.test (local) / {slug}.aestheticai.com (prod)
 *  2. API header      — X-Clinic-ID header with Bearer token (REST API routes)
 *  3. Authenticated user — staff accessing the dashboard on the main domain; their
 *                          tenant_id attribute is authoritative (requires auth middleware
 *                          to run before this one in the stack)
 *
 * Magic link tenant resolution is handled in MagicLinkService,
 * as it requires DB lookup and establishes a session.
 *
 * NOTE: Apply this middleware ONLY to routes that require a tenant context.
 * Public routes (home, admin panel) should NOT use this middleware.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->resolveFromSubdomain($request)
            ?? $this->resolveFromClinicHeader($request)
            ?? $this->resolveFromAuthenticatedUser($request);

        if ($tenant === null) {
            throw new NotFoundHttpException('Tenant could not be resolved for this request.');
        }

        TenantContext::set($tenant);

        return $next($request);
    }

    /**
     * Resolve tenant from subdomain: miamilife.aesthetic-ai.test → slug = 'miamilife'
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $appDomain = parse_url(config('app.url'), PHP_URL_HOST);

        // Not a subdomain of the app domain — skip
        if (! str_ends_with($host, '.' . $appDomain)) {
            return null;
        }

        $slug = explode('.', $host)[0];

        if ($slug === $appDomain) {
            return null; // bare domain, no subdomain
        }

        return Tenant::where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Resolve tenant from X-Clinic-ID header (REST API v1).
     * The Bearer token is validated separately in auth:sanctum middleware.
     */
    private function resolveFromClinicHeader(Request $request): ?Tenant
    {
        $clinicId = $request->header('X-Clinic-ID');

        if (empty($clinicId)) {
            return null;
        }

        return Tenant::find($clinicId);
    }

    /**
     * Resolve tenant from the authenticated user's tenant_id.
     *
     * Used when staff access the clinic dashboard on the main domain
     * (aesthetic-ai.test/dashboard) rather than a tenant subdomain.
     * Requires the 'auth' middleware to run before 'tenant' in the stack.
     */
    private function resolveFromAuthenticatedUser(Request $request): ?Tenant
    {
        $user = $request->user();

        if ($user === null || empty($user->tenant_id)) {
            return null;
        }

        return Tenant::find($user->tenant_id);
    }
}
