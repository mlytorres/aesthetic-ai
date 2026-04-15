<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('google login redirect works', function () {
    $response = $this->get(route('login.google'));

    $response->assertRedirect();
    $this->assertStringContainsString('accounts.google.com', $response->getTargetUrl());
});

test('google login redirect stores tenant in session', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->get(route('login.google', ['tenant' => $tenant->slug]));

    $response->assertRedirect();
    $this->assertEquals($tenant->slug, session('auth.social_tenant'));
});

test('users can authenticate via google', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'test@example.com',
    ]);

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-123');
    $googleUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $googleUser->shouldReceive('getName')->andReturn('Test User');
    $googleUser->token = 'fake-token';

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    $response = $this->get(route('login.google.callback'));

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect('/dashboard');

    $user->refresh();
    $this->assertEquals('google-id-123', $user->google_id);
    $this->assertEquals('fake-token', $user->google_token);

    // Verify Audit Log
    $this->assertDatabaseHas('audit_log_entries', [
        'action' => 'coordinator.logged_in',
        'subject_id' => $user->id,
        'subject_type' => 'User',
    ]);
});

test('google login fails if user does not exist', function () {
    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-456');
    $googleUser->shouldReceive('getEmail')->andReturn('nonexistent@example.com');
    $googleUser->token = 'fake-token';

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    $response = $this->get(route('login.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('google login enforces tenant isolation', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'clinic-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'clinic-b']);

    // User belongs to Tenant A
    $user = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'email' => 'staff@clinic-a.com',
    ]);

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-789');
    $googleUser->shouldReceive('getEmail')->andReturn('staff@clinic-a.com');
    $googleUser->token = 'fake-token';

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    // Attempt to login to Tenant B
    session(['auth.social_tenant' => 'clinic-b']);

    $response = $this->get(route('login.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('tenant');
    $this->assertGuest();
});
