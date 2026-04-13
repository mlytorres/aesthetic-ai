<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// ─── Registration form ────────────────────────────────────────────────────────

test('registration page is accessible', function (): void {
    $this->get('/register')->assertOk();
});

test('clinic registration creates a tenant and owner user', function (): void {
    Notification::fake();
    $plan = Plan::factory()->create(['slug' => 'starter']);

    $this->post('/register', [
        'clinic_name' => 'Miami Aesthetics',
        'slug' => 'miami-aesthetics',
        'name' => 'Dr. Jane Smith',
        'email' => 'jane@miamiaesthetics.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ])->assertRedirect();

    // Tenant created with correct slug and starter plan
    $tenant = Tenant::where('slug', 'miami-aesthetics')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Miami Aesthetics')
        ->and($tenant->plan_id)->toBe($plan->id)
        ->and($tenant->trial_ends_at)->not->toBeNull();

    // Owner user created and linked to tenant
    $user = User::where('email', 'jane@miamiaesthetics.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe(User::ROLE_OWNER)
        ->and($user->name)->toBe('Dr. Jane Smith')
        ->and($user->email_verified_at)->toBeNull(); // must verify email first
});

test('trial starts immediately and grants billing access', function (): void {
    Notification::fake();
    Plan::factory()->create(['slug' => 'starter']);

    $this->post('/register', [
        'clinic_name' => 'Test Clinic',
        'slug' => 'test-clinic-trial',
        'name' => 'Dr. Test',
        'email' => 'test@clinic.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ]);

    $tenant = Tenant::where('slug', 'test-clinic-trial')->first();
    expect($tenant->trial_ends_at)->not->toBeNull()
        ->and($tenant->trial_ends_at->isFuture())->toBeTrue()
        ->and($tenant->hasBillingAccess())->toBeTrue();
});

test('registration sends email verification notification', function (): void {
    Notification::fake();
    Plan::factory()->create(['slug' => 'starter']);

    $this->post('/register', [
        'clinic_name' => 'Sunny Spa',
        'slug' => 'sunny-spa',
        'name' => 'Alice',
        'email' => 'alice@sunnyspa.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ]);

    $user = User::where('email', 'alice@sunnyspa.com')->first();
    Notification::assertSentTo($user, VerifyEmail::class);
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('slug must be unique', function (): void {
    Tenant::factory()->create(['slug' => 'taken-slug']);

    $this->post('/register', [
        'clinic_name' => 'Another Clinic',
        'slug' => 'taken-slug',
        'name' => 'Bob',
        'email' => 'bob@another.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ])->assertSessionHasErrors('slug');
});

test('slug must be lowercase alphanumeric with hyphens only', function (): void {
    $this->post('/register', [
        'clinic_name' => 'Bad Slug',
        'slug' => 'Bad_Slug!!',
        'name' => 'Test',
        'email' => 'test@bad.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ])->assertSessionHasErrors('slug');
});

test('reserved slugs are rejected', function (): void {
    $this->post('/register', [
        'clinic_name' => 'Admin Clinic',
        'slug' => 'admin',
        'name' => 'Hacker',
        'email' => 'hacker@admin.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ])->assertSessionHasErrors('slug');
});

test('email must be unique across users', function (): void {
    User::factory()->create(['email' => 'existing@clinic.com']);

    $this->post('/register', [
        'clinic_name' => 'Duplicate Email',
        'slug' => 'dup-email',
        'name' => 'Test',
        'email' => 'existing@clinic.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ])->assertSessionHasErrors('email');
});

test('password confirmation must match', function (): void {
    $this->post('/register', [
        'clinic_name' => 'Mismatch Clinic',
        'slug' => 'mismatch',
        'name' => 'Test',
        'email' => 'test@mismatch.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'different123!A',
    ])->assertSessionHasErrors('password');
});

// ─── Email verification ───────────────────────────────────────────────────────

test('unverified user cannot access dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/email/verify');
});

test('verified user can access dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('invited team members are pre-verified and can log in immediately', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'email_verified_at' => now(),
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)->post('/clinic/team', [
        'name' => 'New Coordinator',
        'email' => 'coord@clinic.com',
        'role' => User::ROLE_COORDINATOR,
    ])->assertRedirect();

    $invited = User::where('email', 'coord@clinic.com')->first();
    expect($invited->email_verified_at)->not->toBeNull();
});

test('email verification redirects to tenant subdomain dashboard', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'verify-test']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email_verified_at' => null,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $this->assertNotNull(User::find($user->id)->email_verified_at);
    $response->assertRedirectContains('verify-test');
    $response->assertRedirectContains('/dashboard');
});
