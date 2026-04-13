<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard-gates all clinic routes when the tenant's trial has expired and they
 * have no active subscription or FREE plan.
 *
 * Redirects to /clinic/billing with a query flag so the billing page can
 * display the trial-expired paywall. Billing routes themselves are excluded
 * from this middleware to avoid an infinite redirect loop.
 *
 * Applied as the 'billing.access' alias in bootstrap/app.php.
 */
class RequireBillingAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();

        if ($tenant === null || $tenant->hasBillingAccess()) {
            return $next($request);
        }

        $billingUrl = '/clinic/billing?trial_expired=1';

        // Inertia SPA navigation: use location() for a full-page redirect.
        if ($request->header('X-Inertia')) {
            return Inertia::location($billingUrl);
        }

        return redirect($billingUrl);
    }
}
