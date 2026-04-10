<?php

declare(strict_types=1);

use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Jobs\AI\SendPatientReportJob;
use App\Mail\PatientReportMail;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\PatientReportService;
use App\Services\TenantContext;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makePatientEvaluation(Tenant $tenant, array $overrides = []): Evaluation
{
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'name_encrypted' => 'Sofia Reyes',
        'email_encrypted' => 'sofia@example.com',
        'phone_encrypted' => '+1-305-555-0100',
    ]);

    return Evaluation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'procedure_slug' => 'rhinoplasty',
        'lead_score' => 72,
        'priority' => 'high',
        'secure_token' => 'patient_tok_'.str_repeat('a', 32),
        'quiz_answers' => [
            'q_concerns' => ['bridge', 'tip'],
            'q_prior_surgery' => false,
            'q_timeline' => '3_to_6_months',
            'q_budget' => '10k_to_15k',
            'q_referral' => 'instagram',
        ],
        'analysis_data' => [
            'proportions' => [
                'overall_harmony' => 74,
                'nasal_symmetry' => ['symmetry_score' => 81, 'deviation_mm' => 0.9],
                'goodes_ratio' => 0.57,
                '_avg_photo_quality' => 88,
            ],
            'recommendations' => [
                'procedure' => 'rhinoplasty',
                'confidence' => 'high',
                'primary_finding' => 'Primary concerns: dorsal hump, nasal tip.',
                'flags' => [],
                'key_points' => ['Targeted refinements should achieve an excellent result.'],
                'technique_notes' => ['Tip refinement ± structural grafting'],
                'harmony_score' => 74,
            ],
        ],
    ], $overrides));
}

// ─── PatientReportService ─────────────────────────────────────────────────────

test('PatientReportService buildReportData includes expected keys', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Miami Life Cosmetic']);
    $evaluation = makePatientEvaluation($tenant);

    $service = app(PatientReportService::class);
    $data = $service->buildReportData($evaluation);

    expect($data)->toHaveKeys([
        'first_name', 'procedure', 'procedure_label', 'clinic_name',
        'generated_at', 'harmony_score', 'harmony_label', 'harmony_summary',
        'proportion_highlights', 'primary_finding', 'key_insights', 'faqs', 'next_steps',
    ]);
});

test('PatientReportService extracts first name from patient', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makePatientEvaluation($tenant);

    $service = app(PatientReportService::class);
    $data = $service->buildReportData($evaluation);

    expect($data['first_name'])->toBe('Sofia');
});

test('PatientReportService maps harmony score to label', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makePatientEvaluation($tenant);

    $service = app(PatientReportService::class);
    $data = $service->buildReportData($evaluation);

    // Score 74 -> Very Good
    expect($data['harmony_score'])->toBe(74)
        ->and($data['harmony_label'])->toBe('Very Good');
});

test('PatientReportService builds FAQs from quiz answers', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makePatientEvaluation($tenant, [
        'quiz_answers' => [
            'q_concerns' => ['bridge', 'tip'],
            'q_prior_surgery' => true,
            'q_timeline' => '3_to_6_months',
            'q_budget' => '10k_to_15k',
            'q_referral' => 'friend',
        ],
    ]);

    $service = app(PatientReportService::class);
    $data = $service->buildReportData($evaluation);

    $questions = array_column($data['faqs'], 'q');

    expect($questions)->toContain('I have had surgery before — how does that affect the process?');
});

test('PatientReportService caps FAQs at 6 items', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makePatientEvaluation($tenant, [
        'quiz_answers' => [
            'q_concerns' => ['bridge', 'tip', 'nostrils', 'asymmetry'],
            'q_prior_surgery' => true,
            'q_smoker' => true,
            'q_timeline' => '3_to_6_months',
            'q_budget' => '10k_to_15k',
            'q_referral' => 'friend',
        ],
    ]);

    $service = app(PatientReportService::class);
    $data = $service->buildReportData($evaluation);

    expect(count($data['faqs']))->toBeLessThanOrEqual(6);
});

test('PatientReportService filename uses evaluation token', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makePatientEvaluation($tenant);

    $service = app(PatientReportService::class);
    $filename = $service->filename($evaluation);

    expect($filename)->toStartWith('your-aesthetic-roadmap-')
        ->toEndWith('.pdf');
});

// ─── PatientReportController ──────────────────────────────────────────────────

test('patient can download their Beauty Roadmap PDF when evaluation is complete', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    $evaluation = makePatientEvaluation($tenant);

    // Mock PDF generation so we don't need a real browser renderer in tests
    $this->mock(PatientReportService::class, function ($mock) {
        $mock->shouldReceive('generateBytes')->once()->andReturn('%PDF-1.4 fake-pdf-bytes');
        $mock->shouldReceive('filename')->once()->andReturn('your-aesthetic-roadmap-test.pdf');
    });

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get('/intake/evaluations/'.$evaluation->secure_token.'/report');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('patient report returns 404 for unknown token', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    app(TenantContext::class)->set($tenant);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get('/intake/evaluations/totally-wrong-token/report');

    $response->assertNotFound();
});

test('patient report returns 404 when evaluation is not yet complete', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    $evaluation = makePatientEvaluation($tenant, ['status' => Evaluation::STATUS_ANALYZING]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get('/intake/evaluations/'.$evaluation->secure_token.'/report');

    $response->assertNotFound();
});

// ─── SendPatientReportJob ─────────────────────────────────────────────────────

test('SendPatientReportJob sends PatientReportMail to patient email', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    $evaluation = makePatientEvaluation($tenant);

    $this->mock(PatientReportService::class, function ($mock) {
        $mock->shouldReceive('generateBytes')->once()->andReturn('%PDF-1.4 fake');
        $mock->shouldReceive('filename')->once()->andReturn('roadmap.pdf');
    });

    app(TenantContext::class)->set($tenant);
    $job = new SendPatientReportJob($evaluation->id);
    $job->handle(app(PatientReportService::class));

    Mail::assertSent(PatientReportMail::class, function ($mail) {
        return $mail->hasTo('sofia@example.com');
    });
});

test('SendPatientReportJob skips sending when patient has no email', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_encrypted' => '',
        'name_encrypted' => 'No Email Patient',
    ]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'secure_token' => 'tok_noemail_'.str_repeat('b', 32),
        'procedure_slug' => 'rhinoplasty',
    ]);

    $mockService = $this->mock(PatientReportService::class);
    $mockService->shouldNotReceive('generateBytes');

    app(TenantContext::class)->set($tenant);
    $job = new SendPatientReportJob($evaluation->id);
    $job->handle($mockService);

    Mail::assertNothingSent();
});

test('GenerateBasicRecommendationsJob dispatches SendPatientReportJob when notifications enabled', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create(['slug' => 'miamilife']);
    $evaluation = makePatientEvaluation($tenant, [
        'status' => Evaluation::STATUS_ANALYZING,
    ]);

    config(['features.notifications' => true]);
    config(['features.ai_vision' => false]);

    app(TenantContext::class)->set($tenant);
    $job = new GenerateBasicRecommendationsJob($evaluation->id);
    $job->handle(
        app(LeadScoringService::class),
        app(AuditLog::class),
        app(WebhookService::class),
    );

    Queue::assertPushedOn('notifications', SendPatientReportJob::class);
});
