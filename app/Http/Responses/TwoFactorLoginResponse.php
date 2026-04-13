<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Tenant;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides Fortify's default post-2FA redirect.
 *
 * Mirrors LoginResponse: super-admins (tenant_id = null) go to /admin,
 * tenant users go to their subdomain /dashboard.
 */
class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
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

        // 2. Tenant users
        $tenant = Tenant::find($user->tenant_id);

        $parsedAppUrl = parse_url(config('app.url'));
        $baseHost = $parsedAppUrl['host'] ?? 'aesthetic-ai.test';
        $scheme = $parsedAppUrl['scheme'] ?? 'https';
        $port = isset($parsedAppUrl['port']) ? ':'.$parsedAppUrl['port'] : '';

        $tenantHost = $tenant->slug.'.'.$baseHost;
        $tenantBaseUrl = $scheme.'://'.$tenantHost.$port;

        $intended = session()->pull('url.intended');

        if ($intended) {
            $parsedIntended = parse_url($intended);
            $path = $parsedIntended['path'] ?? '/dashboard';
            $query = isset($parsedIntended['query']) ? '?'.$parsedIntended['query'] : '';
            $redirectTo = $tenantBaseUrl.$path.$query;
        } else {
            $redirectTo = $tenantBaseUrl.'/dashboard';
        }

        return $request->header('X-Inertia')
            ? Inertia::location($redirectTo)
            : redirect()->to($redirectTo);
    }
}
