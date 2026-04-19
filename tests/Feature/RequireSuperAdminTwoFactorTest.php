<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin without 2fa is redirected away from admin routes when enforced', function (): void {
    config(['security.require_two_factor_for_super_admin' => true]);

    $admin = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_OWNER,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get('/admin/tenants')
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('flash.warning');
});

test('super admin with confirmed 2fa can access admin routes when enforced', function (): void {
    config(['security.require_two_factor_for_super_admin' => true]);

    $admin = User::factory()->withTwoFactor()->create([
        'tenant_id' => null,
        'role' => User::ROLE_OWNER,
    ]);

    $this->actingAs($admin)
        ->get('/admin/tenants')
        ->assertOk();
});
