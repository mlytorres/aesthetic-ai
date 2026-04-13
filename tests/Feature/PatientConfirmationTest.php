<?php

declare(strict_types=1);

use App\Jobs\AI\SendPatientConfirmationJob;
use App\Mail\PatientConfirmationMail;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeConfirmationEvaluation(Tenant $tenant, array $overrides = []): Evaluation
{
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'name_encrypted' => 'Maria Gonzalez',
        'email_encrypted' => 'maria@example.com',
    ]);

    return Evaluation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_SUBMITTED,
        'procedure_slug' => 'rhinoplasty',
    ], $overrides));
}

// ─── Job dispatches correctly ─────────────────────────────────────────────────

it('dispatches SendPatientConfirmationJob on the notifications queue', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $evaluation = makeConfirmationEvaluation($tenant);

    SendPatientConfirmationJob::dispatch($evaluation->id)->onQueue('notifications');

    Queue::assertPushedOn('notifications', SendPatientConfirmationJob::class, function ($job) use ($evaluation) {
        return $job->evaluationId === $evaluation->id;
    });
});

// ─── Mail is sent ─────────────────────────────────────────────────────────────

it('sends PatientConfirmationMail to the patient email', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create(['name' => 'Miami Life Aesthetics']);
    $evaluation = makeConfirmationEvaluation($tenant);

    app(TenantContext::class)->set($tenant);
    (new SendPatientConfirmationJob($evaluation->id))->handle();

    Mail::assertSent(PatientConfirmationMail::class, function ($mail) {
        return $mail->hasTo('maria@example.com');
    });
});

it('sends exactly one confirmation email per submission', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $evaluation = makeConfirmationEvaluation($tenant);

    app(TenantContext::class)->set($tenant);
    (new SendPatientConfirmationJob($evaluation->id))->handle();

    Mail::assertSent(PatientConfirmationMail::class, 1);
});

// ─── Mail content ─────────────────────────────────────────────────────────────

it('confirmation mail uses patient first name only', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Beverly Hills Clinic']);
    $evaluation = makeConfirmationEvaluation($tenant);

    $mailable = new PatientConfirmationMail($evaluation);

    expect($mailable->firstName)->toBe('Maria')
        ->and($mailable->clinicName)->toBe('Beverly Hills Clinic')
        ->and($mailable->procedureLabel)->toBe('Rhinoplasty');
});

it('confirmation mail subject includes procedure and clinic name', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Beverly Hills Clinic']);
    $evaluation = makeConfirmationEvaluation($tenant);

    $mailable = new PatientConfirmationMail($evaluation);
    $envelope = $mailable->envelope();

    expect($envelope->subject)
        ->toContain('Rhinoplasty')
        ->toContain('Beverly Hills Clinic');
});

it('confirmation mail has no attachments', function (): void {
    $tenant = Tenant::factory()->create();
    $evaluation = makeConfirmationEvaluation($tenant);

    $mailable = new PatientConfirmationMail($evaluation);

    expect($mailable->attachments())->toBeEmpty();
});

// ─── Skips silently when patient has no email ─────────────────────────────────

it('does not send when patient has no email address', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_encrypted' => null,
    ]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_SUBMITTED,
    ]);

    (new SendPatientConfirmationJob($evaluation->id))->handle();

    Mail::assertNothingSent();
});

it('does not send when patient email is invalid', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'email_encrypted' => 'not-an-email',
    ]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_SUBMITTED,
    ]);

    (new SendPatientConfirmationJob($evaluation->id))->handle();

    Mail::assertNothingSent();
});
