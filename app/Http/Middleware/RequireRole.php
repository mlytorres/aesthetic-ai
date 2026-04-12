<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate access to routes by tenant role.
 *
 * Usage in routes:
 *   ->middleware('role:owner,admin')           // owner OR admin
 *   ->middleware('role:owner,admin,coordinator') // owner OR admin OR coordinator
 *
 * Super-admins (tenant_id = null) are NOT tenant users and should never hit
 * tenant routes — they are blocked by the absence of 'tenant' middleware.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin() || ! in_array($user->role, $roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
