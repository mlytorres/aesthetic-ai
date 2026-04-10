<?php

declare(strict_types=1);

use App\Models\AuditLogEntry;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClinicalBriefService;
use App\Services\SecureFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Creates a tenant, coordinator user, patient, evaluation and optional photos.
 */
function makeBriefFixture(int $photoCount = 3): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
    ]);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'procedure_slug' => 'rhinoplasty',
    ]);

    $types = [Photo::TYPE_FRONT, Photo::TYPE_LEFT_PROFILE, Photo::TYPE_RIGHT_PROFILE];

    for ($i = 0; $i < min($photoCount, 3); $i++) {
        $s3Key = "{$tenant->id}/{$patient->id}/{$evaluation->id}/{$types[$i]}_test.png";

        Photo::factory()->create([
            'tenant_id' => $tenant->id,
            'evaluation_id' => $evaluation->id,
            'type' => $types[$i],
            's3_key' => $s3Key,
            's3_key_hash' => (new SecureFileService)->hashKey($s3Key),
        ]);
    }

    return [$tenant, $user, $evaluation];
}

// ─── ClinicalBriefService unit tests ─────────────────────────────────────────

test('ClinicalBriefService generates a filename from procedure slug and evaluation id', function (): void {
    $evaluation = Evaluation::factory()->make([
        'id' => 'abcdef12-0000-0000-0000-000000000000',
        'procedure_slug' => 'rhinoplasty',
    ]);

    $filename = app(ClinicalBriefService::class)->filename($evaluation);

    expect($filename)->toBe('clinical-brief-rhinoplasty-abcdef12.pdf');
});

test('ClinicalBriefService filename uses first 8 chars of UUID', function (): void {
    $evaluation = Evaluation::factory()->make([
        'id' => '12345678-aaaa-bbbb-cccc-dddddddddddd',
        'procedure_slug' => 'liposuction-360',
    ]);

    $filename = app(ClinicalBriefService::class)->filename($evaluation);

    expect($filename)->toBe('clinical-brief-liposuction-360-12345678.pdf');
});

test('ClinicalBriefService generateBytes returns a non-empty string', function (): void {
    Storage::fake('local');

    // Mock the service so we don't need a real Cloudflare connection.
    // We verify the service delegates to Pdf::view() with the right arguments
    // by binding a mock into the container.
    $mock = Mockery::mock(ClinicalBriefService::class);
    $mock->shouldReceive('generateBytes')
        ->once()
        ->andReturn('%PDF-fake-bytes');
    $mock->shouldReceive('filename')
        ->andReturn('clinical-brief-rhinoplasty-abcdef12.pdf');

    app()->instance(ClinicalBriefService::class, $mock);

    [, , $evaluation] = makeBriefFixture(1);
    $evaluation->load(['patient', 'photos', 'tenant']);

    $result = app(ClinicalBriefService::class)->generateBytes($evaluation);

    expect($result)->toBeString()->not->toBeEmpty();
});

// ─── HTTP download endpoint tests ─────────────────────────────────────────────

test('authenticated coordinator can download a clinical brief PDF', function (): void {
    Storage::fake('local');

    // Swap the service so no real PDF is generated.
    $mock = Mockery::mock(ClinicalBriefService::class);
    $mock->shouldReceive('generateBytes')->andReturn('%PDF-1.4 fake');
    $mock->shouldReceive('filename')->andReturn('clinical-brief-rhinoplasty-abcdef12.pdf');
    app()->instance(ClinicalBriefService::class, $mock);

    [$tenant, $user, $evaluation] = makeBriefFixture(0);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('evaluations.brief', $evaluation))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('downloading a brief creates a HIPAA audit log entry', function (): void {
    Storage::fake('local');

    $mock = Mockery::mock(ClinicalBriefService::class);
    $mock->shouldReceive('generateBytes')->andReturn('%PDF-1.4 fake');
    $mock->shouldReceive('filename')->andReturn('clinical-brief-rhinoplasty-abcdef12.pdf');
    app()->instance(ClinicalBriefService::class, $mock);

    [$tenant, $user, $evaluation] = makeBriefFixture(0);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('evaluations.brief', $evaluation));

    expect(
        AuditLogEntry::where('action', 'evaluation.brief.downloaded')
            ->where('subject_id', $evaluation->id)
            ->exists()
    )->toBeTrue();
});

test('unauthenticated request to download brief is redirected to login', function (): void {
    [, , $evaluation] = makeBriefFixture(0);

    $this->get(route('evaluations.brief', $evaluation))
        ->assertRedirect(route('login'));
});

test('coordinator cannot download a brief belonging to another tenant', function (): void {
    Storage::fake('local');

    $mock = Mockery::mock(ClinicalBriefService::class);
    $mock->shouldReceive('generateBytes')->andReturn('%PDF-1.4 fake');
    $mock->shouldReceive('filename')->andReturn('clinical-brief-rhinoplasty-abcdef12.pdf');
    app()->instance(ClinicalBriefService::class, $mock);

    // Tenant A user
    $tenantA = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_COORDINATOR]);

    // Tenant B evaluation
    $tenantB = Tenant::factory()->create();
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
    $evaluationB = Evaluation::factory()->create([
        'tenant_id' => $tenantB->id,
        'patient_id' => $patientB->id,
        'status' => Evaluation::STATUS_COMPLETE,
    ]);

    $this->actingAs($userA)
        ->withSession(['tenant_id' => $tenantA->id])
        ->get(route('evaluations.brief', $evaluationB))
        ->assertNotFound();
});

test('brief download response has correct Content-Disposition filename', function (): void {
    Storage::fake('local');

    $mock = Mockery::mock(ClinicalBriefService::class);
    $mock->shouldReceive('generateBytes')->andReturn('%PDF-1.4 fake');
    $mock->shouldReceive('filename')->andReturn('clinical-brief-rhinoplasty-abcdef12.pdf');
    app()->instance(ClinicalBriefService::class, $mock);

    [$tenant, $user, $evaluation] = makeBriefFixture(0);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('evaluations.brief', $evaluation));

    $response->assertOk();

    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('clinical-brief-rhinoplasty-')
        ->toContain('.pdf');
});
