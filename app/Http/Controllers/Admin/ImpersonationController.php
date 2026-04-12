<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Super-admin impersonation — allows a super admin to log in as any tenant
 * user for debugging and support purposes without knowing their password.
 *
 * Session flow:
 *   impersonate()  →  session['impersonating_id'] = super-admin ID
 *                     auth()->login($tenantUser)
 *                     redirect → tenant subdomain /dashboard
 *
 *   leave()        →  auth()->loginUsingId(session['impersonating_id'])
 *                     session['impersonating_id'] removed
 *                     redirect → /admin/tenants
 */
class ImpersonationController extends Controller
{
    /**
     * Begin impersonating a tenant user.
     *
     * Accessible only inside the 'auth + super-admin' middleware group.
     * Cannot impersonate another super admin (tenant_id must not be null).
     */
    public function impersonate(User $user): RedirectResponse
    {
        // Never allow impersonating a super admin (tenant_id === null).
        abort_if($user->tenant_id === null, 403, 'Cannot impersonate a super admin.');

        // Store the super admin's ID so we can restore the session on leave.
        session()->put('impersonating_id', auth()->id());

        auth()->login($user);

        // Redirect to the tenant's subdomain dashboard for a seamless context switch.
        $user->loadMissing('tenant');
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';

        return redirect("{$scheme}://{$user->tenant->slug}.{$domain}/dashboard");
    }

    /**
     * Stop impersonating and restore the original super-admin session.
     *
     * This route is intentionally outside the 'super-admin' middleware group
     * because the current auth user is a tenant user while impersonating.
     */
    public function leave(): RedirectResponse
    {
        $originalId = session()->pull('impersonating_id');

        abort_if($originalId === null, 403, 'No active impersonation session.');

        auth()->loginUsingId($originalId);

        return redirect()->route('admin.tenants.index');
    }
}
