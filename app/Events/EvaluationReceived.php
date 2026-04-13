<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Evaluation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the clinic's private channel when a patient submits an evaluation.
 *
 * Channel: private-tenant.{tenant_id}
 * Payload: enough to render a toast and update the badge count without a round trip.
 */
class EvaluationReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $evaluationId;

    public readonly string $tenantId;

    public readonly string $procedureSlug;

    public readonly string $createdAt;

    public function __construct(Evaluation $evaluation)
    {
        $this->evaluationId = $evaluation->id;
        $this->tenantId = $evaluation->tenant_id;
        $this->procedureSlug = $evaluation->procedure_slug;
        $this->createdAt = $evaluation->created_at->toIso8601String();
    }

    /**
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}"),
        ];
    }

    /**
     * Explicit event name so the frontend listener is predictable.
     */
    public function broadcastAs(): string
    {
        return 'evaluation.received';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'procedure_slug' => $this->procedureSlug,
            'created_at' => $this->createdAt,
        ];
    }
}
