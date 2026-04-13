<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-challenge'),
        );
});

test('super admin is redirected to /admin after 2FA', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $superAdmin = User::factory()->withTwoFactor()->create(['tenant_id' => null]);

    $this->post(route('login'), [
        'email' => $superAdmin->email,
        'password' => 'password',
    ]);

    $this->post(route('two-factor.login'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect('/admin');
});

test('tenant user is redirected to their subdomain dashboard after 2FA', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTwoFactor()->create(['tenant_id' => $tenant->id]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('two-factor.login'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirectContains($tenant->slug)
        ->assertRedirectContains('/dashboard');
});