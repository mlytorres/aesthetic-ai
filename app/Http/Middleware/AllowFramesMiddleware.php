<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use App\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the intake wizard to be embedded only by parent origins configured on the tenant.
 *
 * Sets a full Content-Security-Policy for intake pages (see {@see ContentSecurityPolicy::forIntakeEmbed})
 * including frame-ancestors. When no parent origins are configured, framing is denied everywhere
 * via frame-ancestors 'none'.
 */
final class AllowFramesMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $tenant = TenantContext::get();

        $response->headers->remove('X-Frame-Options');

        $response->headers->set(
            'Content-Security-Policy',
            ContentSecurityPolicy::forIntakeEmbed($tenant),
            false,
        );

        return $response;
    }
}
