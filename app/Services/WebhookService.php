<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\TenantContext;
use App\Jobs\DispatchWebhookJob;
use App\Models\Evaluation;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Creates signed webhook delivery records and dispatches them to the queue.
 *
 * Payload contract: NO PHI is included. All references use the evaluation's
 * secure_token so the receiving CRM can store a reference without any PII.
 *
 * Events emitted:
 *   evaluation.analysis_complete  — AI pipeline finished; lead score + priority available
 *   evaluation.status_changed     — coordinator changed status (contacted/booked/no_show/not_a_fit)
 */
class WebhookService
{
    /**
     * Create a WebhookDelivery record and queue it for dispatch.
     *
     * Silently no-ops if the tenant has no webhook_url configured.
     *
     * @param  array<string, mixed>  $extraData  Merged into the `data` envelope
     */
    public function dispatch(Evaluation $evaluation, string $event, array $extraData = []): void
    {
        $tenant = TenantContext::get();

        if (blank($tenant->webhook_url)) {
            return;
        }

        $payload = $this->buildEnvelope($evaluation, $event, $extraData);

        $delivery = WebhookDelivery::create([
            'tenant_id' => $tenant->id,
            'evaluation_id' => $evaluation->id,
            'event' => $event,
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        DispatchWebhookJob::dispatch($delivery->id)->onQueue('webhooks');
    }

    /**
     * Build the full signed envelope.
     *
     * @param  array<string, mixed>  $extraData
     * @return array<string, mixed>
     */
    private function buildEnvelope(Evaluation $evaluation, string $event, array $extraData): array
    {
        return [
            'event' => $event,
            'api_version' => '2025-01',
            'idempotency_key' => Str::uuid()->toString(),
            'timestamp' => now()->toIso8601String(),
            'data' => array_merge([
                'evaluation_token' => $evaluation->secure_token,
                'procedure' => $evaluation->procedure_slug,
                'status' => $evaluation->status,
                'lead_score' => $evaluation->lead_score,
                'priority' => $evaluation->priority,
                'portal_url' => url("/evaluations/{$evaluation->id}"),
            ], $extraData),
        ];
    }
}
