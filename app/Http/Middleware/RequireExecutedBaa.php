<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks patient intake mutations when the clinic has no recorded Business Associate Agreement.
 *
 * @see Tenant::hasExecutedBaa()
 */
final class RequireExecutedBaa
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();

        if (! config('security.require_baa_for_intake_submissions', true)) {
            return $next($request);
        }

        if ($tenant->hasExecutedBaa()) {
            return $next($request);
        }

        return $this->blocked(
            $request,
            'This clinic\'s intake is not yet available. Please contact the clinic directly.',
            'baa_required',
        );
    }

    private function blocked(Request $request, string $message, string $reason): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reason' => $reason], 403);
        }

        return redirect()->route('intake.blocked')->with([
            'blocked_reason' => $reason,
            'blocked_message' => $message,
        ]);
    }
}
