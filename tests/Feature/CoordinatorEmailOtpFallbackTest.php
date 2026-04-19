<?php

declare(strict_types=1);

use App\Mail\CoordinatorEmailOtpMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('coordinator can request email otp code', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    Mail::fake();

    $tenant = Tenant::factory()->create();
    $coordinator = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($coordinator)
        ->post(route('security.coordinator-otp.send'))
        ->assertStatus(302);

    Mail::assertSent(CoordinatorEmailOtpMail::class);
});

test('coordinator can verify a valid email otp code', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $coordinator = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($coordinator);

    $code = '123456';
    Cache::put(
        'coordinator-email-otp:'.$coordinator->id,
        [
            'hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ],
        now()->addMinutes(10),
    );

    $this->post(route('security.coordinator-otp.verify'), ['code' => $code])
        ->assertStatus(302);

    expect((int) session('coordinator_email_otp_user_id'))->toBe($coordinator->id)
        ->and(session()->has('coordinator_email_otp_verified_at'))->toBeTrue();
});

test('owner cannot use coordinator email otp fallback routes', function (): void {
    config(['security.require_two_factor_for_privileged_tenant_roles' => true]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)
        ->get(route('security.coordinator-otp.show'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs($owner)
        ->post(route('security.coordinator-otp.send'))
        ->assertRedirect(route('security.edit'));
});
