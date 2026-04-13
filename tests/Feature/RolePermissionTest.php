<?php

declare(strict_types=1);

use App\Http\Resources\EvaluationResource;
use App\Jobs\AI\GenerateSimulationJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function roleUser(Tenant $tenant, string $role): User
{
    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
}

function roleEvaluation(Tenant $tenant): Evaluation
{
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
    ]);
}

// ─── Super-admin detection ────────────────────────────────────────────────────

test('user with tenant_id=null is a super admin', function (): void {
    $superAdmin = User::factory()->create(['tenant_id' => null]);

    expect($superAdmin->isSuperAdmin())->toBeTrue();
});

test('tenant user is never a super admin', function (): void {
    $tenant = Tenant::factory()->create();

    foreach ([User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR, User::ROLE_SURGEON, User::ROLE_VIEWER] as $role) {
        $user = roleUser($tenant, $role);
        expect($user->isSuperAdmin())->toBeFalse("Expected {$role} not to be super admin");
    }
});

test('super admin cannot access tenant dashboard', function (): void {
    $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);

    $this->actingAs($superAdmin)
        ->get('/dashboard')
        ->assertStatus(404); // TenantMiddleware 404s — no tenant to resolve
});

test('tenant user cannot access admin panel', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = roleUser($tenant, User::ROLE_OWNER);

    $this->actingAs($owner)
        ->get('/admin/tenants')
        ->assertForbidden();
});

// ─── Clinic settings & team — owner/admin only ────────────────────────────────

test('owner can access clinic settings', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = roleUser($tenant, User::ROLE_OWNER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)
        ->get('/clinic/settings')
        ->assertOk();
});

test('admin can access clinic settings', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = roleUser($tenant, User::ROLE_ADMIN);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($admin)
        ->get('/clinic/settings')
        ->assertOk();
});

test('coordinator cannot access clinic settings', function (): void {
    $tenant = Tenant::factory()->create();
    $coordinator = roleUser($tenant, User::ROLE_COORDINATOR);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($coordinator)
        ->get('/clinic/settings')
        ->assertForbidden();
});

test('surgeon cannot access clinic settings', function (): void {
    $tenant = Tenant::factory()->create();
    $surgeon = roleUser($tenant, User::ROLE_SURGEON);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($surgeon)
        ->get('/clinic/settings')
        ->assertForbidden();
});

test('viewer cannot access clinic settings', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = roleUser($tenant, User::ROLE_VIEWER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($viewer)
        ->get('/clinic/settings')
        ->assertForbidden();
});

// ─── Analytics — all except surgeon ──────────────────────────────────────────

test('surgeon cannot access analytics', function (): void {
    $tenant = Tenant::factory()->create();
    $surgeon = roleUser($tenant, User::ROLE_SURGEON);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($surgeon)
        ->get('/analytics')
        ->assertForbidden();
});

test('viewer can access analytics', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = roleUser($tenant, User::ROLE_VIEWER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($viewer)
        ->get('/analytics')
        ->assertOk();
});

// ─── Evaluation status updates — clinical actors only ────────────────────────

test('coordinator can update evaluation status', function (): void {
    $tenant = Tenant::factory()->create();
    $coordinator = roleUser($tenant, User::ROLE_COORDINATOR);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($coordinator)
        ->patch("/evaluations/{$evaluation->id}/status", ['status' => Evaluation::STATUS_CONTACTED])
        ->assertRedirect();
});

test('surgeon cannot update evaluation status', function (): void {
    $tenant = Tenant::factory()->create();
    $surgeon = roleUser($tenant, User::ROLE_SURGEON);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($surgeon)
        ->patch("/evaluations/{$evaluation->id}/status", ['status' => Evaluation::STATUS_CONTACTED])
        ->assertForbidden();
});

test('viewer cannot update evaluation status', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = roleUser($tenant, User::ROLE_VIEWER);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($viewer)
        ->patch("/evaluations/{$evaluation->id}/status", ['status' => Evaluation::STATUS_CONTACTED])
        ->assertForbidden();
});

// ─── Simulation — owner/admin/coordinator/surgeon, not viewer ─────────────────

test('viewer cannot request simulation', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = roleUser($tenant, User::ROLE_VIEWER);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($viewer)
        ->post("/evaluations/{$evaluation->id}/simulation")
        ->assertForbidden();
});

test('surgeon can request simulation', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $surgeon = roleUser($tenant, User::ROLE_SURGEON);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($surgeon)
        ->post("/evaluations/{$evaluation->id}/simulation")
        ->assertStatus(202);

    Queue::assertPushed(GenerateSimulationJob::class);
});

// ─── Team management — owner-only for owner role assignment ──────────────────

test('admin cannot invite user with owner role', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = roleUser($tenant, User::ROLE_ADMIN);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($admin)
        ->post('/clinic/team', [
            'name' => 'New Owner',
            'email' => 'newowner@example.com',
            'role' => User::ROLE_OWNER,
        ])
        ->assertForbidden();
});

test('owner can invite user with owner role', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = roleUser($tenant, User::ROLE_OWNER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)
        ->post('/clinic/team', [
            'name' => 'Another Owner',
            'email' => 'another@example.com',
            'role' => User::ROLE_OWNER,
        ])
        ->assertRedirect();
});

test('admin cannot remove owner from team', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = roleUser($tenant, User::ROLE_ADMIN);
    $ownerToRemove = roleUser($tenant, User::ROLE_OWNER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($admin)
        ->delete("/clinic/team/{$ownerToRemove->id}")
        ->assertForbidden();
});

test('owner can remove another owner from team', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = roleUser($tenant, User::ROLE_OWNER);
    $ownerToRemove = roleUser($tenant, User::ROLE_OWNER);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)
        ->delete("/clinic/team/{$ownerToRemove->id}")
        ->assertRedirect();
});

// ─── PHI stripping in EvaluationResource ─────────────────────────────────────

test('surgeon receives null PHI fields in evaluation response', function (): void {
    $tenant = Tenant::factory()->create();
    $surgeon = roleUser($tenant, User::ROLE_SURGEON);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    auth()->login($surgeon);
    $request = request();
    $request->setUserResolver(fn () => auth()->user());

    $resource = (new EvaluationResource($evaluation->load('patient')))
        ->toArray($request);

    // Patient block exists but PHI fields are null
    expect($resource['patient']['id'])->not->toBeNull()
        ->and($resource['patient']['name'])->toBeNull()
        ->and($resource['patient']['email'])->toBeNull()
        ->and($resource['patient']['phone'])->toBeNull();
});

test('coordinator receives full PHI fields in evaluation response', function (): void {
    $tenant = Tenant::factory()->create();
    $coordinator = roleUser($tenant, User::ROLE_COORDINATOR);
    $evaluation = roleEvaluation($tenant);
    app(TenantContext::class)->set($tenant);

    auth()->login($coordinator);
    $request = request();
    $request->setUserResolver(fn () => auth()->user());

    $resource = (new EvaluationResource($evaluation->load('patient')))
        ->toArray($request);

    expect($resource['patient']['id'])->not->toBeNull()
        ->and($resource['patient']['name'])->not->toBeNull();
});
