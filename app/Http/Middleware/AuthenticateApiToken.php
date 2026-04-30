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
 * Authenticates External REST API v1 requests via Bearer token or ecosystem-style API key header.
 *
 * Accepts either:
 *   - Bearer token: `Authorization: Bearer aai_live_xxx`
 *   - Same raw token via `X-Api-Key` (MiamiLife-style integrations; matches Bearer lookup)
 *   - Session auth (for clinic dashboard users calling the API directly)
 *
 * When a token is used, the associated tenant is loaded and set in TenantContext —
 * the X-Clinic-ID header is required for Bearer/X-Api-Key calls unless resolving from session.
 *
 * Token validation:
 *   1. Hash the raw token with SHA-256 (from Bearer or X-Api-Key)
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
        $headerApiKey = $request->header('X-Api-Key');
        $credential = (is_string($headerApiKey) && $headerApiKey !== '')
            ? $headerApiKey
            : (! blank($bearer) ? $bearer : null);

        if (blank($credential)) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'An API token is required. Pass Authorization: Bearer {token}, or the same token as X-Api-Key.',
                    'status' => 401,
                ],
            ], 401);
        }

        $apiToken = ApiToken::findByRaw($credential);

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
