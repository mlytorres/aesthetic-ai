<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * HIPAA: No PHI is sent to Google. We only request openid, profile, and email.
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Capture tenant context if provided via query param or subdomain
        $tenantSlug = $request->query('tenant');

        if ($tenantSlug) {
            session(['auth.social_tenant' => $tenantSlug]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     *
     * HIPAA:
     *  1. Multi-tenant isolation: we verify the user exists and belongs to the
     *     resolved tenant before logging them in.
     *  2. Audit logging: every successful login is recorded.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['google' => 'Google authentication failed.']);
        }

        // 1. Find user by google_id or email
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'No account found with this Google email. Please contact your clinic administrator.',
            ]);
        }

        // 2. Tenant Validation
        // If we have a tenant in session (from redirect), ensure user belongs to it.
        $sessionTenantSlug = session()->pull('auth.social_tenant');
        if ($sessionTenantSlug) {
            $tenant = Tenant::where('slug', $sessionTenantSlug)->first();
            if (! $tenant || $user->tenant_id !== $tenant->id) {
                return redirect()->route('login')->withErrors([
                    'tenant' => 'You do not have access to this clinic.',
                ]);
            }
        }

        // 3. Update User with Google ID and Token (encrypted via model cast)
        $user->update([
            'google_id' => $googleUser->getId(),
            'google_token' => $googleUser->token,
        ]);

        // 4. Log the user in
        Auth::login($user);

        // 5. Audit Log (HIPAA compliance)
        app(AuditLog::class)->record(
            action: 'coordinator.logged_in',
            subject: $user,
            metadata: [
                'source' => 'google',
            ]
        );

        // 6. Redirect based on role
        if ($user->isSuperAdmin()) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended('/dashboard');
    }
}
