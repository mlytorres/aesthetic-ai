<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires confirmed two-factor authentication for clinic roles that can view
 * patient PHI (owner, admin, coordinator). Surgeon and viewer are exempt.
 *
 * @see User::requiresMandatoryTwoFactor()
 */
final class RequirePrivilegedTwoFactor
{
    private const OTP_SESSION_USER_KEY = 'coordinator_email_otp_user_id';

    private const OTP_SESSION_VERIFIED_AT_KEY = 'coordinator_email_otp_verified_at';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_two_factor_for_privileged_tenant_roles', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->requiresMandatoryTwoFactor()) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($request->session()->has('impersonating_id')) {
            return $next($request);
        }

        if ($user->isCoordinator() && $this->hasValidCoordinatorOtpSession($request, $user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $user->isCoordinator()
                    ? __('Authenticator app or email verification is required for your account.')
                    : __('Two-factor authentication must be enabled for your account.'),
                'reason' => $user->isCoordinator() ? 'coordinator_auth_step_required' : 'two_factor_required',
            ], 403);
        }

        if ($user->isCoordinator()) {
            return redirect()
                ->guest(route('security.coordinator-otp.show'))
                ->with(
                    'flash.warning',
                    __('For coordinators, either enable an authenticator app or verify with an emailed code to continue.'),
                );
        }

        return redirect()
            ->guest(route('security.edit'))
            ->with(
                'flash.warning',
                __('Two-factor authentication is required for your role. Enable it on this page before using the clinic dashboard.'),
            );
    }

    private function hasValidCoordinatorOtpSession(Request $request, User $user): bool
    {
        $verifiedUserId = $request->session()->get(self::OTP_SESSION_USER_KEY);
        $verifiedAt = $request->session()->get(self::OTP_SESSION_VERIFIED_AT_KEY);

        if (! is_int($verifiedUserId) || $verifiedUserId !== $user->id) {
            return false;
        }

        if (! is_int($verifiedAt)) {
            return false;
        }

        $expiresAfterMinutes = (int) config('security.coordinator_email_otp_session_minutes', 720);

        return Carbon::createFromTimestamp($verifiedAt)
            ->addMinutes($expiresAfterMinutes)
            ->isFuture();
    }
}
