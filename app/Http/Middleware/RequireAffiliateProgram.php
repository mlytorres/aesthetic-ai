<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access to affiliate program features.
 * 
 * Only allowed for tenants on the PRO plan or with the feature explicitly enabled.
 */
class RequireAffiliateProgram
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();

        if ($tenant === null || ! $tenant->hasAffiliateProgram()) {
            if ($request->expectsJson()) {
                abort(403, 'The Affiliate Program is only available on the PRO plan.');
            }

            return redirect()->route('dashboard')
                ->with('flash.error', 'The Affiliate Program is only available on the PRO plan. Please upgrade to continue.');
        }

        return $next($request);
    }
}
