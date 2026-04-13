<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Tenant;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects after email verification.
 *
 * Self-registered clinic owners are sent to their tenant subdomain dashboard
 * so they land in their branded environment on first login.
 *
 * Super-admins (tenant_id = null) go to the platform admin panel.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user?->tenant_id === null) {
            $redirectTo = '/admin';

            return $request->header('X-Inertia')
                ? Inertia::location($redirectTo)
                : redirect()->to($redirectTo);
        }

        $tenant = Tenant::find($user->tenant_id);

        $parsedAppUrl = parse_url(config('app.url'));
        $baseHost = $parsedAppUrl['host'] ?? 'aesthetic-ai.test';
        $scheme = $parsedAppUrl['scheme'] ?? 'https';
        $port = isset($parsedAppUrl['port']) ? ':'.$parsedAppUrl['port'] : '';

        $redirectTo = "{$scheme}://{$tenant->slug}.{$baseHost}{$port}/dashboard";

        return $request->header('X-Inertia')
            ? Inertia::location($redirectTo)
            : redirect()->to($redirectTo);
    }
}
