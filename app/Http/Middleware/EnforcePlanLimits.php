<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates intake form submissions against the tenant's active plan.
 *
 * Blocks the request when:
 *   (a) The tenant has no active subscription and their trial has expired.
 *   (b) The tenant has exceeded their monthly evaluation limit.
 *   (c) The requested procedure is not in the tenant's enabled procedures list.
 *
 * Applied to POST /intake/* routes so browsing the form is always allowed —
 * only actual submission is gated. This keeps the UX smooth for patients.
 */
class EnforcePlanLimits
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();

        if ($tenant === null) {
            return $next($request);
        }

        // (a) Active subscription / trial check.
        if (! $tenant->hasBillingAccess()) {
            return $this->blocked(
                $request,
                'This clinic\'s subscription has expired. Please contact the clinic for assistance.',
                'subscription_expired',
            );
        }

        // (b) Monthly evaluation limit.
        if (! $tenant->withinEvalLimit()) {
            return $this->blocked(
                $request,
                'This clinic has reached its monthly evaluation limit. Please try again next month.',
                'eval_limit_reached',
            );
        }

        // (c) Procedure eligibility — only checked when the request carries a procedure slug.
        $procedure = $request->input('procedure_slug') ?? $request->route('procedure');

        if ($procedure !== null && ! $tenant->canUseProcedure($procedure)) {
            return $this->blocked(
                $request,
                'This procedure is not available for this clinic\'s plan.',
                'procedure_not_allowed',
            );
        }

        return $next($request);
    }

    private function blocked(Request $request, string $message, string $reason): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reason' => $reason], 402);
        }

        // Redirect the patient to an informational page rather than a raw error.
        return redirect()->route('intake.blocked')->with([
            'blocked_reason' => $reason,
            'blocked_message' => $message,
        ]);
    }
}
