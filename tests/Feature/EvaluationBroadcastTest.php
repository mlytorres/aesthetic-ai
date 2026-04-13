<?php

declare(strict_types=1);

use App\Events\EvaluationReceived;
use App\Models\Evaluation;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('EvaluationReceived broadcast event', function (): void {
    beforeEach(function (): void {
        Plan::factory()->create(['slug' => 'starter']);

        $this->tenant = Tenant::factory()->create();

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_OWNER,
        ]);
    });

    it('is dispatched when an evaluation is submitted', function (): void {
        Event::fake([EvaluationReceived::class]);

        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Evaluation::STATUS_ANALYZING,
        ]);

        EvaluationReceived::dispatch($evaluation);

        Event::assertDispatched(EvaluationReceived::class, function (EvaluationReceived $event) use ($evaluation): bool {
            return $event->evaluationId === $evaluation->id
                && $event->tenantId === $this->tenant->id;
        });
    });

    it('broadcasts on the correct private tenant channel', function (): void {
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $event = new EvaluationReceived($evaluation);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe("private-tenant.{$this->tenant->id}");
    });

    it('uses the correct broadcast event name', function (): void {
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $event = new EvaluationReceived($evaluation);

        expect($event->broadcastAs())->toBe('evaluation.received');
    });

    it('broadcasts required payload fields', function (): void {
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'procedure_slug' => 'rhinoplasty',
        ]);

        $event = new EvaluationReceived($evaluation);
        $payload = $event->broadcastWith();

        expect($payload)
            ->toHaveKey('evaluation_id', $evaluation->id)
            ->toHaveKey('procedure_slug', 'rhinoplasty')
            ->toHaveKey('created_at');
    });
});
