<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Procedure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $tenant = TenantContext::get();

        // Ensure the tenant has a webhook secret. If not, generate one.
        if (empty($tenant->webhook_secret)) {
            $tenant->update(['webhook_secret' => Str::random(64)]);
        }

        $appUrl = rtrim(config('app.url'), '/');
        $parsed = parse_url($appUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'aesthetic-ai.test';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $tenantDomain = $scheme.'://'.$tenant->slug.'.'.$host.$port;

        $apiTokens = ApiToken::where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'scopes', 'last_used_at', 'created_at'])
            ->values();

        return Inertia::render('clinic/integrations', [
            'tenant' => [
                'id' => $tenant->id,
                'webhook_url' => $tenant->webhook_url,
                'webhook_secret' => $tenant->webhook_secret,
                'theme' => $tenant->settings['theme'] ?? 'luxury-dark',
                'brand_primary' => $tenant->settings['brand_primary'] ?? '#0E9E8E',
                'brand_font' => $tenant->settings['brand_font'] ?? 'system-ui, sans-serif',
                'locale' => $tenant->settings['locale'] ?? 'en',
            ],
            'tenantDomain' => $tenantDomain,
            'widgetUrl' => $appUrl.'/widget/v1/loader.js',
            'availableProcedures' => Procedure::where('active', true)
                ->get(['slug', 'label'])
                ->values(),
            'apiTokens' => $apiTokens,
        ]);
    }

    /**
     * Update the target webhook URL.
     */
    public function updateWebhook(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $tenant->update([
            'webhook_url' => $validated['webhook_url'],
        ]);

        return back()->with('flash.success', 'Webhook configuration saved.');
    }

    /**
     * Fire a synchronous test payload to the configured webhook URL.
     *
     * Returns the HTTP status code and latency immediately so the admin can
     * confirm their endpoint is reachable and verifying the signature correctly.
     * No WebhookDelivery record is persisted — this is a one-off connectivity check.
     */
    public function sendTest(): JsonResponse
    {
        $tenant = TenantContext::get();

        if (blank($tenant->webhook_url)) {
            return response()->json(['error' => 'No webhook URL configured.'], 422);
        }

        // Sample payload mirrors evaluation.completed shape so Zapier can map
        // all fields (including evaluation_token) even on a connectivity test.
        $payload = [
            'event' => 'evaluation.test',
            'api_version' => '2025-01',
            'idempotency_key' => Str::uuid()->toString(),
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'evaluation_token' => 'test_'.Str::random(40),
                'procedure_interest' => 'rhinoplasty',
                'lead_score' => 87,
                'priority' => 'high',
                'ready_for_call' => true,
                'timeline' => 'within_3_months',
                'budget_range' => '15000_25000',
                'photos_available' => true,
                'ai_analysis_complete' => true,
                'message' => 'This is a test webhook from SymetriHealth. Your endpoint is correctly configured.',
                'clinic' => $tenant->name,
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $secret = $tenant->webhook_secret ?? '';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-SymetriHealth-Signature' => $signature,
                    'X-SymetriHealth-Event' => 'evaluation.test',
                    'User-Agent' => 'SymetriHealth-Webhook/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($tenant->webhook_url);

            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

            return response()->json([
                'ok' => $response->successful(),
                'status_code' => $response->status(),
                'latency_ms' => $latencyMs,
                'body' => substr($response->body(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

            return response()->json([
                'ok' => false,
                'status_code' => null,
                'latency_ms' => $latencyMs,
                'body' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rotate the webhook HMAC signing secret.
     */
    public function rotateSecret(): RedirectResponse
    {
        $tenant = TenantContext::get();

        // 64-character cryptographically secure string
        $tenant->update([
            'webhook_secret' => Str::random(64),
        ]);

        // In a production app with grace periods, we'd queue an event to maintain
        // the old secret temporarily. For this MVP, we enforce immediate rotation.

        return back()->with('flash.success', 'Webhook secret successfully rotated.');
    }

    // ─── REST API Token Management ────────────────────────────────────────────

    /**
     * Return active API tokens for the tenant (no raw values — hashes only stored).
     */
    public function listTokens(): JsonResponse
    {
        $tenant = TenantContext::get();

        $tokens = ApiToken::where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'scopes', 'last_used_at', 'created_at'])
            ->values();

        return response()->json(['data' => $tokens]);
    }

    /**
     * Generate a new API token. Returns the raw token ONCE — it is never stored.
     */
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $tenant = TenantContext::get();

        $raw = ApiToken::generateRaw();

        $token = ApiToken::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'token_hash' => $raw['hash'],
            'scopes' => ['evaluations:read', 'phi:read'],
        ]);

        return response()->json([
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'raw_token' => $raw['raw'], // shown once, never again
                'scopes' => $token->scopes,
                'created_at' => $token->created_at,
            ],
        ], 201);
    }

    /**
     * Revoke (soft-delete) an API token.
     */
    public function revokeToken(ApiToken $apiToken): RedirectResponse
    {
        $tenant = TenantContext::get();

        abort_unless($apiToken->tenant_id === $tenant->id, 403);

        $apiToken->update(['revoked_at' => now()]);

        return back()->with('flash.success', 'API token revoked.');
    }
}
