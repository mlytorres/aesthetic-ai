<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides Fortify's default post-login redirect.
 *
 * Super-admins (tenant_id = null) land on the platform admin panel.
 * All other users land on /dashboard.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        $redirectTo = ($user?->tenant_id === null)
            ? '/admin'
            : config('fortify.home', '/dashboard');

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($redirectTo);
    }
}
