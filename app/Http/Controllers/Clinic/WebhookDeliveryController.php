<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebhookDeliveryController extends Controller
{
    /**
     * Webhook delivery log — paginated list of all outbound deliveries for this tenant.
     */
    public function index(): Response
    {
        $deliveries = WebhookDelivery::with('evaluation:id,procedure_slug,secure_token')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('clinic/webhooks', [
            'deliveries' => $deliveries->through(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'event' => $d->event,
                'status' => $d->status,
                'attempt_count' => $d->attempt_count,
                'last_response' => $d->last_response,
                'delivered_at' => $d->delivered_at?->toIso8601String(),
                'created_at' => $d->created_at->toIso8601String(),
                'evaluation' => $d->evaluation ? [
                    'id' => $d->evaluation->id,
                    'procedure' => $d->evaluation->procedure_slug,
                    'token' => $d->evaluation->secure_token,
                ] : null,
            ]),
            'webhookUrl' => TenantContext::get()->webhook_url,
            'stats' => [
                'delivered' => WebhookDelivery::where('status', WebhookDelivery::STATUS_DELIVERED)->count(),
                'failed' => WebhookDelivery::where('status', WebhookDelivery::STATUS_FAILED)->count(),
                'pending' => WebhookDelivery::whereIn('status', [
                    WebhookDelivery::STATUS_PENDING,
                    WebhookDelivery::STATUS_RETRYING,
                ])->count(),
            ],
        ]);
    }

    /**
     * Manually retry a failed delivery — resets to pending and re-queues.
     */
    public function retry(WebhookDelivery $webhookDelivery): RedirectResponse
    {
        $webhookDelivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        DispatchWebhookJob::dispatch($webhookDelivery->id)->onQueue('webhooks');

        return back()->with('flash.success', 'Webhook queued for retry.');
    }
}
