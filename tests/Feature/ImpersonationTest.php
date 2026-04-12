<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function superAdmin(): User
{
    return User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
}

function tenantWithOwner(): array
{
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);

    return [$tenant, $owner];
}

// ─── Impersonate ──────────────────────────────────────────────────────────────

test('super admin can impersonate a tenant user', function (): void {
    $admin = superAdmin();
    [$tenant, $owner] = tenantWithOwner();

    $this->actingAs($admin)
        ->post("/admin/users/{$owner->id}/impersonate")
        ->assertRedirect();

    expect(auth()->id())->toBe($owner->id)
        ->and(session()->has('impersonating_id'))->toBeTrue()
        ->and(session('impersonating_id'))->toBe($admin->id);
});

test('non-super-admin cannot reach the impersonate route', function (): void {
    [$tenant, $owner] = tenantWithOwner();
    $anotherUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($anotherUser)
        ->post("/admin/users/{$owner->id}/impersonate")
        ->assertStatus(403);
});

test('cannot impersonate another super admin', function (): void {
    $admin = superAdmin();
    $anotherAdmin = superAdmin();

    $this->actingAs($admin)
        ->post("/admin/users/{$anotherAdmin->id}/impersonate")
        ->assertStatus(403);
});

// ─── Leave impersonation ──────────────────────────────────────────────────────

test('admin can stop impersonation and return to their session', function (): void {
    $admin = superAdmin();
    [$tenant, $owner] = tenantWithOwner();

    $this->actingAs($owner);
    session()->put('impersonating_id', $admin->id);

    $this->delete('/impersonate')
        ->assertRedirect(route('admin.tenants.index'));

    expect(auth()->id())->toBe($admin->id)
        ->and(session()->has('impersonating_id'))->toBeFalse();
});

test('cannot stop impersonation without an active session', function (): void {
    [$tenant, $owner] = tenantWithOwner();

    $this->actingAs($owner)
        ->delete('/impersonate')
        ->assertStatus(403);
});
