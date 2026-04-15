<?php

declare(strict_types=1);

use App\Facades\TenantContext;
use App\Jobs\SendConsultationInviteJob;
use App\Models\Consultation;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function consultTenant(array $settings = [], ?string $planSlug = 'growth'): Tenant
{
    $plan = Plan::where('slug', $planSlug)->first()
        ?? Plan::factory()->create(['slug' => $planSlug, 'name' => ucfirst($planSlug)]);

    return Tenant::factory()->create([
        'plan_id' => $plan->id,
        'settings' => $settings,
    ]);
}

function consultUser(Tenant $tenant, string $role = User::ROLE_COORDINATOR): User
{
    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
}

function consultEvaluation(Tenant $tenant): Evaluation
{
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
    ]);
}

// ─── Feature flag — Tenant model ─────────────────────────────────────────────

test('tenant on pro plan has video consultations enabled automatically', function (): void {
    $tenant = consultTenant(planSlug: 'pro');

    expect($tenant->hasVideoConsultations())->toBeTrue();
});

test('tenant on growth plan does not have video consultations by default', function (): void {
    $tenant = consultTenant(planSlug: 'growth');

    expect($tenant->hasVideoConsultations())->toBeFalse();
});

test('tenant on growth plan has video consultations when manually toggled on', function (): void {
    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');

    expect($tenant->hasVideoConsultations())->toBeTrue();
});

// ─── Admin feature flag toggle ────────────────────────────────────────────────

test('super admin can toggle video consultations on for a tenant', function (): void {
    $admin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
    $tenant = consultTenant(planSlug: 'growth');

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->id}/features", [
            'video_consultations_enabled' => true,
        ])
        ->assertRedirect();

    expect($tenant->fresh()->settings['video_consultations_enabled'])->toBeTrue();
});

test('super admin can toggle video consultations off for a tenant', function (): void {
    $admin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->id}/features", [
            'video_consultations_enabled' => false,
        ])
        ->assertRedirect();

    expect($tenant->fresh()->settings['video_consultations_enabled'])->toBeFalse();
});

// ─── Scheduling endpoint ──────────────────────────────────────────────────────

test('coordinator cannot schedule consultation when feature is disabled', function (): void {
    $tenant = consultTenant(planSlug: 'growth'); // no flag set
    $user = consultUser($tenant);
    $evaluation = consultEvaluation($tenant);

    TenantContext::set($tenant);

    $this->actingAs($user)
        ->postJson("/evaluations/{$evaluation->id}/consultations", [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
        ])
        ->assertForbidden();
});

test('coordinator can schedule consultation when feature is enabled', function (): void {
    Queue::fake();

    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');
    $user = consultUser($tenant);
    $evaluation = consultEvaluation($tenant);

    // Mock DailyService so we don't hit the real API
    $this->mock(DailyService::class, function ($mock) {
        $mock->shouldReceive('createRoom')
            ->once()
            ->andReturn(['name' => 'consult-abc', 'url' => 'https://yourco.daily.co/consult-abc']);
    });

    TenantContext::set($tenant);

    $this->actingAs($user)
        ->postJson("/evaluations/{$evaluation->id}/consultations", [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
        ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'scheduled_at', 'duration_minutes', 'status', 'daily_room_url', 'patient_join_url']);

    Queue::assertPushedOn('notifications', SendConsultationInviteJob::class);
});

test('scheduled consultation is persisted in the database', function (): void {
    Queue::fake();

    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');
    $user = consultUser($tenant);
    $evaluation = consultEvaluation($tenant);

    $this->mock(DailyService::class, function ($mock) {
        $mock->shouldReceive('createRoom')
            ->once()
            ->andReturn(['name' => 'consult-xyz', 'url' => 'https://yourco.daily.co/consult-xyz']);
    });

    TenantContext::set($tenant);

    $this->actingAs($user)
        ->postJson("/evaluations/{$evaluation->id}/consultations", [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'duration_minutes' => 45,
            'notes' => 'Discuss rhinoplasty options',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('consultations', [
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'daily_room_name' => 'consult-xyz',
        'status' => Consultation::STATUS_SCHEDULED,
        'duration_minutes' => 45,
    ]);
});

// ─── Cancel ───────────────────────────────────────────────────────────────────

test('coordinator can cancel a scheduled consultation', function (): void {
    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');
    $user = consultUser($tenant);
    $evaluation = consultEvaluation($tenant);

    $consultation = Consultation::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'coordinator_id' => $user->id,
        'scheduled_at' => now()->addDays(1),
        'duration_minutes' => 30,
        'daily_room_name' => 'consult-cancel-test',
        'daily_room_url' => 'https://yourco.daily.co/consult-cancel-test',
        'token' => (string) Str::uuid(),
        'status' => Consultation::STATUS_SCHEDULED,
    ]);

    $this->mock(DailyService::class, function ($mock) {
        $mock->shouldReceive('deleteRoom')->once();
    });

    TenantContext::set($tenant);

    $this->actingAs($user)
        ->deleteJson("/consultations/{$consultation->id}")
        ->assertOk()
        ->assertJson(['status' => 'cancelled']);

    expect($consultation->fresh()->status)->toBe(Consultation::STATUS_CANCELLED);
});

// ─── Viewer cannot schedule ───────────────────────────────────────────────────

test('viewer cannot schedule a consultation', function (): void {
    $tenant = consultTenant(['video_consultations_enabled' => true], 'growth');
    $user = consultUser($tenant, User::ROLE_VIEWER);
    $evaluation = consultEvaluation($tenant);

    TenantContext::set($tenant);

    $this->actingAs($user)
        ->postJson("/evaluations/{$evaluation->id}/consultations", [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'duration_minutes' => 30,
        ])
        ->assertForbidden();
});
