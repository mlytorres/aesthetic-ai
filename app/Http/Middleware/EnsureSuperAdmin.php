<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes to the super-admin panel.
 *
 * Super-admins are platform operators whose User record has tenant_id = null.
 * They can create and manage tenants, invite clinic owners, and view platform
 * metrics. They never belong to a specific clinic.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id !== null) {
            abort(403, 'Access restricted to platform administrators.');
        }

        return $next($request);
    }
}
