<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Facades\TenantContext;
use App\Models\ApiToken;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates External REST API v1 requests via Bearer token.
 *
 * Accepts either:
 *   - Bearer token in Authorization header: `Authorization: Bearer aai_live_xxx`
 *   - Session auth (for clinic dashboard users calling the API directly)
 *
 * When a Bearer token is used, the associated tenant is loaded and set in
 * TenantContext — the X-Clinic-ID header is still required so the tenant
 * can be resolved before this middleware runs, or we can resolve from the token.
 *
 * Token validation:
 *   1. Hash the raw Bearer value with SHA-256
 *   2. Look up matching non-revoked, non-expired api_tokens row
 *   3. If the tenant_id on the token does not match the TenantContext, reject
 *   4. Touch last_used_at
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is already authenticated via session, resolve tenant from them
        if ($request->user() !== null) {
            $user = $request->user();

            if (! empty($user->tenant_id)) {
                $tenant = Tenant::find($user->tenant_id);

                if ($tenant) {
                    TenantContext::set($tenant);
                }
            }

            return $next($request);
        }

        $bearer = $request->bearerToken();

        if (blank($bearer)) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'An API token is required. Pass it as: Authorization: Bearer {token}',
                    'status' => 401,
                ],
            ], 401);
        }

        $apiToken = ApiToken::findByRaw($bearer);

        if ($apiToken === null) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Invalid or expired API token.',
                    'status' => 401,
                ],
            ], 401);
        }

        // Resolve tenant from X-Clinic-ID header or fall back to token's tenant
        $clinicId = $request->header('X-Clinic-ID');
        $tenant = $clinicId ? Tenant::find($clinicId) : null;

        if ($tenant === null) {
            $tenant = $apiToken->tenant;
        }

        if ($tenant === null || $tenant->id !== $apiToken->tenant_id) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_MISMATCH',
                    'message' => 'Token does not belong to the specified clinic.',
                    'status' => 403,
                ],
            ], 403);
        }

        // Set tenant context so TenantScope works for all subsequent queries
        TenantContext::set($tenant);

        $apiToken->touchLastUsed();

        // Store token on the request so controllers can check scopes if needed
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
