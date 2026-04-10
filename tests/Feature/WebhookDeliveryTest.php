<?php

declare(strict_types=1);

use App\Jobs\DispatchWebhookJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\TenantContext;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeEvaluation(Tenant $tenant, array $overrides = []): Evaluation
{
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'lead_score' => 75,
        'priority' => 'high',
        'secure_token' => 'tok_test_abc123',
    ], $overrides));
}

// ─── WebhookService ────────────────────────────────────────────────────────────

test('WebhookService creates a delivery record and queues the job', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => 'https://crm.example.com/webhook']);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    app(WebhookService::class)->dispatch($evaluation, 'evaluation.analysis_complete');

    Queue::assertPushedOn('webhooks', DispatchWebhookJob::class);

    $delivery = WebhookDelivery::withoutGlobalScopes()->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->event)->toBe('evaluation.analysis_complete')
        ->and($delivery->status)->toBe(WebhookDelivery::STATUS_PENDING)
        ->and($delivery->tenant_id)->toBe($tenant->id)
        ->and($delivery->payload['event'])->toBe('evaluation.analysis_complete')
        ->and($delivery->payload['data']['evaluation_token'])->toBe('tok_test_abc123');
});

test('WebhookService is a no-op when tenant has no webhook_url', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => null]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    app(WebhookService::class)->dispatch($evaluation, 'evaluation.analysis_complete');

    Queue::assertNothingPushed();
    expect(WebhookDelivery::withoutGlobalScopes()->count())->toBe(0);
});

test('WebhookService payload contains no PHI — only token and slugs', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => 'https://crm.example.com/webhook']);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant, ['procedure_slug' => 'rhinoplasty']);

    app(WebhookService::class)->dispatch($evaluation, 'evaluation.analysis_complete');

    $delivery = WebhookDelivery::withoutGlobalScopes()->first();
    $data = $delivery->payload['data'];

    // Must include reference fields
    expect($data)->toHaveKey('evaluation_token')
        ->toHaveKey('procedure')
        ->toHaveKey('lead_score')
        ->toHaveKey('priority')
        ->toHaveKey('portal_url');

    // Must NOT include PHI
    expect($data)->not->toHaveKey('name')
        ->not->toHaveKey('email')
        ->not->toHaveKey('phone')
        ->not->toHaveKey('patient');
});

// ─── DispatchWebhookJob ────────────────────────────────────────────────────────

test('DispatchWebhookJob sends signed POST and marks delivery as delivered', function (): void {
    Http::fake(['https://crm.example.com/webhook' => Http::response('OK', 200)]);

    $tenant = Tenant::factory()->create([
        'webhook_url' => 'https://crm.example.com/webhook',
        'webhook_secret' => 'test-secret-key',
    ]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    $delivery = WebhookDelivery::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'event' => 'evaluation.analysis_complete',
        'payload' => ['event' => 'evaluation.analysis_complete', 'data' => ['evaluation_token' => 'tok_abc']],
        'status' => WebhookDelivery::STATUS_PENDING,
    ]);

    (new DispatchWebhookJob($delivery->id))->handle();

    $delivery->refresh();
    expect($delivery->status)->toBe(WebhookDelivery::STATUS_DELIVERED)
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->last_response['status_code'])->toBe(200);
});

test('DispatchWebhookJob sends HMAC-SHA256 signature header', function (): void {
    Http::fake(['https://crm.example.com/webhook' => Http::response('OK', 200)]);

    $secret = 'my-signing-secret';
    $tenant = Tenant::factory()->create([
        'webhook_url' => 'https://crm.example.com/webhook',
        'webhook_secret' => $secret,
    ]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);
    $payload = ['event' => 'test', 'data' => ['evaluation_token' => 'tok_xyz']];

    $delivery = WebhookDelivery::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'event' => 'test',
        'payload' => $payload,
        'status' => WebhookDelivery::STATUS_PENDING,
    ]);

    (new DispatchWebhookJob($delivery->id))->handle();

    $expectedSig = 'sha256='.hash_hmac(
        'sha256',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $secret,
    );

    Http::assertSent(function ($request) use ($expectedSig) {
        return $request->hasHeader('X-AestheticAI-Signature', $expectedSig);
    });
});

test('DispatchWebhookJob marks delivery as failed after all retries exhausted', function (): void {
    $tenant = Tenant::factory()->create([
        'webhook_url' => 'https://crm.example.com/webhook',
    ]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    $delivery = WebhookDelivery::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'event' => 'evaluation.analysis_complete',
        'payload' => ['event' => 'test'],
        'status' => WebhookDelivery::STATUS_PENDING,
    ]);

    $job = new DispatchWebhookJob($delivery->id);
    $job->failed(new RuntimeException('All retries exhausted'));

    $delivery->refresh();
    expect($delivery->status)->toBe(WebhookDelivery::STATUS_FAILED)
        ->and($delivery->next_retry_at)->toBeNull();
});

// ─── Dashboard UI — delivery log ───────────────────────────────────────────────

test('coordinator can view webhook delivery log', function (): void {
    $tenant = Tenant::factory()->create(['webhook_url' => 'https://crm.example.com/webhook']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    WebhookDelivery::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'event' => 'evaluation.analysis_complete',
        'payload' => ['event' => 'evaluation.analysis_complete'],
        'status' => WebhookDelivery::STATUS_DELIVERED,
        'attempt_count' => 1,
        'delivered_at' => now(),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('clinic.webhooks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clinic/webhooks')
            ->has('deliveries.data', 1)
            ->where('deliveries.data.0.event', 'evaluation.analysis_complete')
            ->where('deliveries.data.0.status', 'delivered')
            ->where('webhookUrl', 'https://crm.example.com/webhook')
        );
});

test('coordinator can manually retry a failed delivery', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => 'https://crm.example.com/webhook']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant);

    $delivery = WebhookDelivery::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'event' => 'evaluation.analysis_complete',
        'payload' => ['event' => 'evaluation.analysis_complete'],
        'status' => WebhookDelivery::STATUS_FAILED,
        'attempt_count' => 5,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->post(route('clinic.webhooks.retry', $delivery))
        ->assertRedirect();

    $delivery->refresh();
    expect($delivery->status)->toBe(WebhookDelivery::STATUS_PENDING);

    Queue::assertPushedOn('webhooks', DispatchWebhookJob::class);
});

// ─── EvaluationController status_changed webhook ─────────────────────────────

test('updating evaluation status fires evaluation.status_changed webhook', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => 'https://crm.example.com/webhook']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant, ['status' => Evaluation::STATUS_COMPLETE]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('evaluations.update-status', $evaluation->id), [
            'status' => Evaluation::STATUS_CONTACTED,
        ])
        ->assertRedirect();

    Queue::assertPushedOn('webhooks', DispatchWebhookJob::class);

    $delivery = WebhookDelivery::withoutGlobalScopes()->first();
    expect($delivery->event)->toBe('evaluation.status_changed')
        ->and($delivery->payload['data']['previous_status'])->toBe(Evaluation::STATUS_COMPLETE)
        ->and($delivery->payload['data']['new_status'])->toBe(Evaluation::STATUS_CONTACTED);
});

test('no webhook is fired when tenant has no webhook_url configured', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['webhook_url' => null]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);

    $evaluation = makeEvaluation($tenant, ['status' => Evaluation::STATUS_COMPLETE]);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('evaluations.update-status', $evaluation->id), [
            'status' => Evaluation::STATUS_CONTACTED,
        ])
        ->assertRedirect();

    Queue::assertNothingPushed();
});
