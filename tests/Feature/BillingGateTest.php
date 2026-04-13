<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function loginOwner(Tenant $tenant): User
{
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);
    test()->actingAs($owner);
    app(TenantContext::class)->set($tenant);

    return $owner;
}

// ─── hasBillingAccess() unit tests ───────────────────────────────────────────

test('tenant on active trial has billing access', function (): void {
    $tenant = Tenant::factory()->create(); // factory default includes active trial

    expect($tenant->hasBillingAccess())->toBeTrue();
});

test('tenant with expired trial has no billing access', function (): void {
    $tenant = Tenant::factory()->expired()->create();

    expect($tenant->hasBillingAccess())->toBeFalse();
});

test('tenant on FREE plan always has billing access', function (): void {
    $freePlan = Plan::factory()->free()->create();
    $tenant = Tenant::factory()->expired()->create(['plan_id' => $freePlan->id]);
    $tenant->load('plan');

    expect($tenant->hasBillingAccess())->toBeTrue();
});

test('tenant with no trial and no subscription has no billing access', function (): void {
    $tenant = Tenant::factory()->create(['trial_ends_at' => null]);

    expect($tenant->hasBillingAccess())->toBeFalse();
});

// ─── Route gate (RequireBillingAccess middleware) ─────────────────────────────

test('active trial tenant can access dashboard', function (): void {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    loginOwner($tenant);

    $this->get('/dashboard')->assertOk();
});

test('expired trial tenant is redirected from dashboard to billing', function (): void {
    $tenant = Tenant::factory()->expired()->create();
    loginOwner($tenant);

    $this->get('/dashboard')->assertRedirectContains('/clinic/billing');
});

test('expired trial tenant can still access the billing page', function (): void {
    $tenant = Tenant::factory()->expired()->create();
    loginOwner($tenant);

    // Billing page itself must be reachable (not gated) so they can upgrade.
    $this->get('/clinic/billing')->assertOk();
});

test('FREE plan tenant can access dashboard even with no trial', function (): void {
    $freePlan = Plan::factory()->free()->create();
    $tenant = Tenant::factory()->expired()->create(['plan_id' => $freePlan->id]);
    $tenant->load('plan');
    loginOwner($tenant);

    $this->get('/dashboard')->assertOk();
});

// ─── Plan visibility ──────────────────────────────────────────────────────────

test('billing page only shows public plans', function (): void {
    Plan::factory()->free()->create();
    Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'is_public' => true]);
    Plan::factory()->create(['name' => 'Growth', 'slug' => 'growth', 'is_public' => true]);

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(7)]);
    loginOwner($tenant);

    $response = $this->get('/clinic/billing');
    $response->assertOk();

    $plans = $response->original->getData()['page']['props']['plans'];
    $slugs = collect($plans)->pluck('slug')->toArray();

    expect($slugs)->not->toContain('free')
        ->and($slugs)->toContain('starter')
        ->and($slugs)->toContain('growth');
});

test('admin can assign FREE plan to a tenant', function (): void {
    $freePlan = Plan::factory()->free()->create();
    $tenant = Tenant::factory()->expired()->create();

    // Super-admin assigns the FREE plan.
    $superAdmin = User::factory()->create(['tenant_id' => null]);
    $this->actingAs($superAdmin);

    $this->patch("/admin/tenants/{$tenant->id}", [
        'name' => $tenant->name,
        'slug' => $tenant->slug,
        'plan_id' => $freePlan->id,
    ])->assertRedirect();

    $tenant->refresh()->load('plan');
    expect($tenant->plan->slug)->toBe('free')
        ->and($tenant->hasBillingAccess())->toBeTrue();
});
