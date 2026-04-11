<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

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
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $tenantDomain = $scheme . '://' . $tenant->slug . '.' . $host . $port;

        return Inertia::render('clinic/integrations', [
            'tenant' => [
                'id' => $tenant->id,
                'webhook_url' => $tenant->webhook_url,
                'webhook_secret' => $tenant->webhook_secret, // Automatically decrypted by Eloquent cast
            ],
            'tenantDomain' => $tenantDomain,
            'widgetUrl' => $appUrl . '/widget/v1/loader.js',
            'availableProcedures' => \App\Models\Procedure::where('active', true)
                ->get(['slug', 'label'])
                ->values(),
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
}
