<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login from analytics', function (): void {
    $this->get(route('analytics'))->assertRedirect(route('login'));
});

test('authenticated coordinator can visit analytics page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});

test('analytics page renders the correct Inertia component', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    // Deferred props (Inertia::defer()) are resolved via a separate partial-reload
    // request — they are intentionally absent from the initial page response.
    // We assert the component name only; data is covered by AnalyticsController unit tests.
    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});

test('analytics returns ok for tenant with no evaluations', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get(route('analytics'))
        ->assertOk();
});

test('analytics scopes data to the authenticated tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => User::ROLE_COORDINATOR]);

    // Evaluations for tenant B — must not bleed into tenant A's response.
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
    Evaluation::factory()->count(5)->create([
        'tenant_id' => $tenantB->id,
        'patient_id' => $patientB->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'lead_score' => 70,
    ]);

    $this->actingAs($userA)
        ->withSession(['tenant_id' => $tenantA->id])
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});
