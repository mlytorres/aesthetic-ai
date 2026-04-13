<?php

declare(strict_types=1);

use App\Concerns\ResolvesJobTenant;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\TenantContext;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'webhook_url' => 'https://hooks.zapier.com/hooks/catch/test/abc/',
        'webhook_secret' => 'test-secret',
    ]);

    $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->evaluation = Evaluation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'lead_score' => 87,
        'priority' => Evaluation::PRIORITY_HIGH,
        'quiz_answers' => [
            'timeline' => 'within_3_months',
            'budget_range' => '15000_25000',
            'concerns' => ['tip', 'bridge'],
        ],
        'analysis_data' => [
            'recommendations' => [
                'primary_finding' => 'Strong rhinoplasty candidate.',
            ],
        ],
    ]);

    Photo::factory()->create([
        'tenant_id' => $this->tenant->id,
        'evaluation_id' => $this->evaluation->id,
        'type' => Photo::TYPE_FRONT,
    ]);
});

test('evaluation.completed webhook is dispatched after AI analysis with full CRM payload', function (): void {
    $dispatched = [];

    $webhooks = $this->mock(WebhookService::class, function ($mock) use (&$dispatched): void {
        $mock->shouldReceive('dispatch')
            ->withArgs(function (Evaluation $eval, string $event, array $extra) use (&$dispatched): bool {
                $dispatched[$event] = $extra;

                return true;
            })
            ->times(2); // evaluation.analysis_complete + evaluation.completed
    });

    $scorer = $this->mock(LeadScoringService::class, function ($mock): void {
        $mock->shouldReceive('score')->andReturn([87, Evaluation::PRIORITY_HIGH]);
    });

    $auditLog = $this->mock(AuditLog::class, function ($mock): void {
        $mock->shouldReceive('recordSystem')->andReturn(null);
    });

    // Set tenant context (normally set by ResolvesJobTenant)
    app(TenantContext::class)->set($this->tenant);

    $job = new GenerateBasicRecommendationsJob($this->evaluation->id);
    $job->handle($scorer, $auditLog, app(WebhookService::class));

    expect($dispatched)->toHaveKey('evaluation.completed')
        ->and($dispatched['evaluation.completed']['procedure_interest'])->toBe('rhinoplasty')
        ->and($dispatched['evaluation.completed']['lead_score'])->toBe(87)
        ->and($dispatched['evaluation.completed']['priority'])->toBe(Evaluation::PRIORITY_HIGH)
        ->and($dispatched['evaluation.completed']['ready_for_call'])->toBeTrue()
        ->and($dispatched['evaluation.completed']['timeline'])->toBe('within_3_months')
        ->and($dispatched['evaluation.completed']['budget_range'])->toBe('15000_25000')
        ->and($dispatched['evaluation.completed']['photos_available'])->toBeTrue()
        ->and($dispatched['evaluation.completed']['ai_analysis_complete'])->toBeTrue();
});

test('evaluation.analysis_complete webhook is still dispatched for backwards compatibility', function (): void {
    $dispatchedEvents = [];

    $this->mock(WebhookService::class, function ($mock) use (&$dispatchedEvents): void {
        $mock->shouldReceive('dispatch')
            ->withArgs(function (Evaluation $eval, string $event, array $extra) use (&$dispatchedEvents): bool {
                $dispatchedEvents[] = $event;

                return true;
            })
            ->times(2);
    });

    $this->mock(LeadScoringService::class, fn ($mock) => $mock->shouldReceive('score')->andReturn([87, Evaluation::PRIORITY_HIGH]));
    $this->mock(AuditLog::class, fn ($mock) => $mock->shouldReceive('recordSystem')->andReturn(null));

    app(TenantContext::class)->set($this->tenant);

    $job = new GenerateBasicRecommendationsJob($this->evaluation->id);
    $job->handle(
        app(LeadScoringService::class),
        app(AuditLog::class),
        app(WebhookService::class)
    );

    expect($dispatchedEvents)->toContain('evaluation.analysis_complete')
        ->and($dispatchedEvents)->toContain('evaluation.completed');
});

test('evaluation.completed webhook payload contains photos_available false when no photos', function (): void {
    $evaluationNoPhotos = Evaluation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->evaluation->patient_id,
        'status' => Evaluation::STATUS_COMPLETE,
        'lead_score' => 60,
        'priority' => Evaluation::PRIORITY_MEDIUM,
        'quiz_answers' => ['timeline' => 'flexible'],
        'analysis_data' => ['recommendations' => ['primary_finding' => 'Moderate candidate.']],
    ]);

    $dispatched = [];

    $this->mock(WebhookService::class, function ($mock) use (&$dispatched): void {
        $mock->shouldReceive('dispatch')
            ->withArgs(function (Evaluation $eval, string $event, array $extra) use (&$dispatched): bool {
                $dispatched[$event] = $extra;

                return true;
            })
            ->times(2);
    });

    $this->mock(LeadScoringService::class, fn ($mock) => $mock->shouldReceive('score')->andReturn([60, Evaluation::PRIORITY_MEDIUM]));
    $this->mock(AuditLog::class, fn ($mock) => $mock->shouldReceive('recordSystem')->andReturn(null));

    app(TenantContext::class)->set($this->tenant);

    $job = new GenerateBasicRecommendationsJob($evaluationNoPhotos->id);
    $job->handle(app(LeadScoringService::class), app(AuditLog::class), app(WebhookService::class));

    expect($dispatched['evaluation.completed']['photos_available'])->toBeFalse()
        ->and($dispatched['evaluation.completed']['ready_for_call'])->toBeFalse();
});
