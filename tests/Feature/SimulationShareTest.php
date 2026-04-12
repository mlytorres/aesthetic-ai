<?php

declare(strict_types=1);

use App\Http\Middleware\TenantMiddleware;
use App\Jobs\AI\GenerateSimulationJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Image;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeCompleteSimulation(string $procedure = 'bbl'): array
{
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'coordinator']);

    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => $procedure,
        'status' => Evaluation::STATUS_COMPLETE,
        'simulation_status' => 'complete',
        'simulation_data' => [
            'mode' => 'openai',
            'model' => 'gpt-image-1',
            'simulation_s3_key' => "{$tenant->id}/{$patient->id}/simulation.png",
            'placeholder' => false,
            'generated_at' => now()->toIso8601String(),
        ],
        'simulation_requested_at' => now(),
    ]);

    return [$tenant, $evaluation, $user];
}

// ─── SimulationShareController ────────────────────────────────────────────────

test('public simulation share page returns 200 for valid token with complete simulation', function (): void {
    [$tenant, $evaluation] = makeCompleteSimulation('bbl');

    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => $evaluation->secure_token]))
        ->assertStatus(200);
});

test('public simulation share page returns 404 for invalid token', function (): void {
    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => 'invalid-token']))
        ->assertStatus(404);
});

test('public simulation share page returns 404 when simulation is not complete', function (): void {
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'simulation_status' => 'pending',
    ]);

    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => $evaluation->secure_token]))
        ->assertStatus(404);
});

test('public simulation share page requires no authentication', function (): void {
    [$tenant, $evaluation] = makeCompleteSimulation('rhinoplasty');

    // No actingAs — unauthenticated request should succeed
    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => $evaluation->secure_token]))
        ->assertStatus(200);
});

test('public simulation share page works for rhinoplasty', function (): void {
    [$tenant, $evaluation] = makeCompleteSimulation('rhinoplasty');

    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => $evaluation->secure_token]))
        ->assertStatus(200);
});

test('public simulation share page works for facelift', function (): void {
    [$tenant, $evaluation] = makeCompleteSimulation('facelift');

    $this->withoutMiddleware(TenantMiddleware::class)
        ->get(route('intake.simulation.share', ['token' => $evaluation->secure_token]))
        ->assertStatus(200);
});

// ─── SimulationController::show() — share_url ─────────────────────────────────

test('simulation status poll includes share_url when simulation is complete', function (): void {
    [$tenant, $evaluation, $user] = makeCompleteSimulation('bbl');

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->withoutMiddleware(TenantMiddleware::class)
        ->getJson(route('evaluations.simulation.show', $evaluation))
        ->assertOk()
        ->assertJsonPath('status', 'complete')
        ->assertJsonStructure(['share_url']);
});

test('share_url contains the evaluation secure token', function (): void {
    [$tenant, $evaluation, $user] = makeCompleteSimulation('bbl');

    app(TenantContext::class)->set($tenant);

    $data = $this->actingAs($user)
        ->withoutMiddleware(TenantMiddleware::class)
        ->getJson(route('evaluations.simulation.show', $evaluation))
        ->json();

    expect($data['share_url'])->toBeString()
        ->and(str_contains($data['share_url'], $evaluation->secure_token))->toBeTrue();
});

test('simulation status poll returns null share_url when simulation is pending', function (): void {
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'coordinator']);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'simulation_status' => 'pending',
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->withoutMiddleware(TenantMiddleware::class)
        ->getJson(route('evaluations.simulation.show', $evaluation))
        ->assertOk()
        ->assertJsonPath('share_url', null);
});

// ─── Face procedure simulation job ───────────────────────────────────────────

test('GenerateSimulationJob runs successfully for rhinoplasty', function (): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'rhinoplasty',
        'status' => Evaluation::STATUS_COMPLETE,
        'simulation_status' => 'processing',
    ]);

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();
    expect($evaluation->simulation_status)->toBe('complete')
        ->and($evaluation->simulation_data['prompt'])->toContain('rhinoplasty');
});

test('GenerateSimulationJob runs successfully for facelift', function (): void {
    config(['features.ai_vision' => false]);
    Image::fake();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'procedure_slug' => 'facelift',
        'status' => Evaluation::STATUS_COMPLETE,
        'simulation_status' => 'processing',
    ]);

    (new GenerateSimulationJob($evaluation->id))->handle();

    $evaluation->refresh();
    expect($evaluation->simulation_status)->toBe('complete')
        ->and($evaluation->simulation_data['prompt'])->toContain('facelift');
});
