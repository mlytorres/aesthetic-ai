<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\CoordinatorEmailOtpMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

final class CoordinatorEmailOtpController extends Controller
{
    private const SESSION_USER_KEY = 'coordinator_email_otp_user_id';

    private const SESSION_VERIFIED_AT_KEY = 'coordinator_email_otp_verified_at';

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->resolveEligibleUser($request);

        if ($user === null) {
            return redirect()->route('security.edit');
        }

        return Inertia::render('settings/coordinator-email-otp', [
            'canUseEmailFallback' => true,
            'codeExpiresInMinutes' => (int) config('security.coordinator_email_otp_code_minutes', 10),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $this->resolveEligibleUser($request);

        if ($user === null) {
            return redirect()->route('security.edit');
        }
        $otpCode = (string) random_int(100000, 999999);
        $expiresAt = CarbonImmutable::now()->addMinutes(
            (int) config('security.coordinator_email_otp_code_minutes', 10),
        );

        Cache::put($this->cacheKey($user), [
            'hash' => hash('sha256', $otpCode),
            'attempts' => 0,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);

        Mail::to($user->email)->send(new CoordinatorEmailOtpMail(
            user: $user,
            code: $otpCode,
            expiresAt: $expiresAt,
        ));

        return back()->with(
            'flash.success',
            __('Security code sent. Check your email and enter the 6-digit code below.'),
        );
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->resolveEligibleUser($request);

        if ($user === null) {
            return redirect()->route('security.edit');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $payload = Cache::get($this->cacheKey($user));

        if (! is_array($payload) || ! isset($payload['hash'], $payload['attempts'], $payload['expires_at'])) {
            return back()->withErrors([
                'code' => __('No active code found. Request a new email code.'),
            ]);
        }

        $maxAttempts = (int) config('security.coordinator_email_otp_max_attempts', 5);
        $attempts = (int) $payload['attempts'];
        $expiresAt = (int) $payload['expires_at'];

        if ($attempts >= $maxAttempts || CarbonImmutable::createFromTimestamp($expiresAt)->isPast()) {
            Cache::forget($this->cacheKey($user));

            return back()->withErrors([
                'code' => __('This code expired. Request a new code and try again.'),
            ]);
        }

        if (! hash_equals((string) $payload['hash'], hash('sha256', $validated['code']))) {
            $payload['attempts'] = $attempts + 1;
            Cache::put(
                $this->cacheKey($user),
                $payload,
                CarbonImmutable::createFromTimestamp($expiresAt),
            );

            return back()->withErrors([
                'code' => __('Invalid code. Please try again.'),
            ]);
        }

        Cache::forget($this->cacheKey($user));

        $request->session()->put([
            self::SESSION_USER_KEY => $user->id,
            self::SESSION_VERIFIED_AT_KEY => now()->timestamp,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    private function resolveEligibleUser(Request $request): ?User
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $request->session()->has('impersonating_id')
            || ! $user->isCoordinator()
            || $user->hasEnabledTwoFactorAuthentication()
        ) {
            return null;
        }

        return $user;
    }

    private function cacheKey(User $user): string
    {
        return 'coordinator-email-otp:'.$user->id;
    }
}
