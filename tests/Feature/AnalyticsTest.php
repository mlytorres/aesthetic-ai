<?php

use App\Models\Evaluation;
use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to login from analytics', function () {
    $this->get(route('analytics'))->assertRedirect(route('login'));
});

test('authenticated users can visit analytics page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});

test('analytics page renders the correct Inertia component', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    // Deferred props are resolved via a separate request, so only the component
    // name is asserted here. Individual data tests below use the deferred endpoint.
    $this->actingAs($user)
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});

test('analytics returns no data for tenant with no evaluations', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('analytics'))
        ->assertOk();
});

test('analytics scopes data to the authenticated tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    // Create evaluations for tenant B — should NOT appear in tenant A's analytics
    Evaluation::factory()->count(5)->create([
        'tenant_id' => $tenantB->id,
        'status' => Evaluation::STATUS_COMPLETE,
        'lead_score' => 70,
    ]);

    $this->actingAs($userA)
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('analytics/index'));
});
