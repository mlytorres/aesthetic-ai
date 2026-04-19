<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets baseline security headers for HTML and JSON responses.
 *
 * Intake routes that use {@see AllowFramesMiddleware} skip CSP and X-Frame-Options here so
 * the embed middleware can supply a stricter, tenant-specific policy.
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldSkip($response)) {
            return $response;
        }

        $isIntake = $request->routeIs('intake.*');

        if (app()->isProduction() && $request->secure()) {
            $hsts = 'max-age='.config('security.hsts_max_age', 63072000);

            if (config('security.hsts_include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }

            if (config('security.hsts_preload', false)) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts, false);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        if ($isIntake) {
            $response->headers->set(
                'Permissions-Policy',
                'camera=(self), microphone=(self), geolocation=()',
                false,
            );
        } else {
            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=()',
                false,
            );
        }

        if ($isIntake) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::forApplication(), false);

        return $response;
    }

    /**
     * Skip non-HTTP responses and binary downloads where headers may already be finalized.
     */
    private function shouldSkip(Response $response): bool
    {
        if (str_contains($response->headers->get('Content-Disposition', ''), 'attachment')) {
            return true;
        }

        $type = $response->headers->get('Content-Type', '');

        if (str_starts_with($type, 'image/') || str_starts_with($type, 'video/') || str_starts_with($type, 'audio/')) {
            return true;
        }

        if (str_starts_with($type, 'application/pdf') || str_starts_with($type, 'application/octet-stream')) {
            return true;
        }

        return false;
    }
}
