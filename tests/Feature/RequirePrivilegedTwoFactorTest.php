<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner without 2FA is redirected to security settings when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('flash.warning');
});

test('coordinator without 2FA cannot access dashboard JSON when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->getJson(route('dashboard'))
        ->assertForbidden()
        ->assertJson(['reason' => 'coordinator_auth_step_required']);
});

test('coordinator without 2FA is redirected to coordinator otp page when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.coordinator-otp.show'));
});

test('viewer without 2FA may access dashboard when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_VIEWER,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk();
});

test('surgeon without 2FA may access dashboard when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $surgeon = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_SURGEON,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($surgeon)
        ->get(route('dashboard'))
        ->assertOk();
});

test('coordinator with confirmed 2FA may access dashboard when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTwoFactor()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('coordinator with active email otp session may access dashboard when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user);
    session()->put('coordinator_email_otp_user_id', $user->id);
    session()->put('coordinator_email_otp_verified_at', now()->timestamp);

    $this->get(route('dashboard'))
        ->assertOk();
});

test('impersonated tenant session bypasses 2FA gate when enforcement is on', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner);
    session()->put('impersonating_id', 999);

    $this->get(route('dashboard'))
        ->assertOk();
});
