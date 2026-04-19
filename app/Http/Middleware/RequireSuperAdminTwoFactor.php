<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSuperAdminTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_two_factor_for_super_admin', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Two-factor authentication is required for super-admin access.'),
                'reason' => 'super_admin_two_factor_required',
            ], 403);
        }

        return redirect()
            ->guest(route('security.edit'))
            ->with(
                'flash.warning',
                __('Enable two-factor authentication to access the admin panel.'),
            );
    }
}
