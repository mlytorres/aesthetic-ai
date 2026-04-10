<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a signed HTTP POST to the tenant's webhook URL.
 *
 * Retry policy (5 total attempts, exponential backoff):
 *   Attempt 1 → immediate
 *   Attempt 2 → 30 seconds later
 *   Attempt 3 → 2 minutes later
 *   Attempt 4 → 10 minutes later
 *   Attempt 5 → 1 hour later
 *
 * After all attempts fail, failed() marks the delivery as permanently failed.
 *
 * Signature: X-AestheticAI-Signature: sha256={hex_hmac}
 * The HMAC is computed over the raw JSON body using the tenant's webhook_secret.
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 15;

    public function __construct(public readonly string $deliveryId) {}

    /**
     * Exponential backoff delays (seconds) between retry attempts.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [30, 120, 600, 3600];
    }

    public function handle(): void
    {
        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::withoutGlobalScopes()->findOrFail($this->deliveryId);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($delivery->tenant_id);

        if (blank($tenant->webhook_url)) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED]);

            return;
        }

        // Mark as retrying on any attempt after the first
        if ($this->attempts() > 1) {
            $delivery->update(['status' => WebhookDelivery::STATUS_RETRYING]);
        }

        // Encode payload and sign with HMAC-SHA256
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $secret = $tenant->webhook_secret ?? '';
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $startedAt = microtime(true);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-AestheticAI-Signature' => $signature,
                'X-AestheticAI-Event' => $delivery->event,
                'User-Agent' => 'AestheticAI-Webhook/1.0',
            ])
            ->withBody($body, 'application/json')
            ->post($tenant->webhook_url);

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        $delivery->update([
            'attempt_count' => $this->attempts(),
            'last_response' => [
                'status_code' => $response->status(),
                'body' => substr($response->body(), 0, 500),
                'latency_ms' => $latencyMs,
                'attempted_at' => now()->toIso8601String(),
            ],
        ]);

        if (! $response->successful()) {
            // Throw so Laravel retries via backoff(); failed() fires when tries exhausted
            throw new \RuntimeException("Webhook endpoint returned HTTP {$response->status()}");
        }

        $delivery->update([
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'delivered_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        WebhookDelivery::withoutGlobalScopes()
            ->where('id', $this->deliveryId)
            ->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'next_retry_at' => null,
            ]);

        Log::warning('Webhook delivery permanently failed after all retry attempts', [
            'delivery_id' => $this->deliveryId,
            'error' => $e->getMessage(),
        ]);
    }
}
