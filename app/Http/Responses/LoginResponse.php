<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Inertia\Inertia;

/**
 * Overrides Fortify's default post-login redirect.
 *
 * Super-admins (tenant_id = null) land on the platform admin panel.
 * All other users land on /dashboard or their intended URL,
 * accurately mapped to their specific tenant subdomain.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        // 1. Super-admins
        if ($user?->tenant_id === null) {
            $redirectTo = session()->pull('url.intended', '/admin');
            
            return $request->header('X-Inertia') 
                ? Inertia::location($redirectTo) 
                : redirect()->to($redirectTo);
        }

        // 2. Tenant Users
        $tenant = Tenant::find($user->tenant_id);
        
        $parsedAppUrl = parse_url(config('app.url'));
        $baseHost = $parsedAppUrl['host'] ?? 'aesthetic-ai.test';
        $scheme = $parsedAppUrl['scheme'] ?? 'https';
        $port = isset($parsedAppUrl['port']) ? ':' . $parsedAppUrl['port'] : '';
        
        $tenantHost = $tenant->slug . '.' . $baseHost;
        $tenantBaseUrl = $scheme . '://' . $tenantHost . $port;

        $intended = session()->pull('url.intended');

        // Extract path and query from intended URL to append to the tenant's base URL
        if ($intended) {
            $parsedIntended = parse_url($intended);
            $path = $parsedIntended['path'] ?? '/dashboard';
            $query = isset($parsedIntended['query']) ? '?' . $parsedIntended['query'] : '';
            $redirectTo = $tenantBaseUrl . $path . $query;
        } else {
            $redirectTo = $tenantBaseUrl . '/dashboard';
        }

        // Use Inertia::location() to hard-redirect cross-domain without CORS issues
        return $request->header('X-Inertia') 
            ? Inertia::location($redirectTo) 
            : redirect()->to($redirectTo);
    }
}
