<?php

declare(strict_types=1);

use App\Mail\UserInviteMail;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeSuperAdmin(): User
{
    return User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
}

function makeTenantUser(Tenant $tenant, string $role = User::ROLE_OWNER): User
{
    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
}

// ─── Access control ───────────────────────────────────────────────────────────

test('guests cannot access admin panel', function () {
    $this->get('/admin/tenants')->assertRedirect('/login');
});

test('tenant users cannot access admin panel', function () {
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant);

    $this->actingAs($user)
        ->get('/admin/tenants')
        ->assertForbidden();
});

test('super admin can access tenant list', function () {
    $admin = makeSuperAdmin();

    $this->actingAs($admin)
        ->get('/admin/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/tenants/index'));
});

// ─── Tenant list ─────────────────────────────────────────────────────────────

test('tenant list includes all tenants with correct shape', function () {
    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create(['name' => 'Acme Clinic']);

    $this->actingAs($admin)
        ->get('/admin/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/index')
            ->has('tenants', fn ($tenants) => $tenants
                ->where('0.name', 'Acme Clinic')
                ->where('0.active', true)
                ->where('0.baa_complete', false)
                ->etc()
            )
        );
});

// ─── Create tenant page ───────────────────────────────────────────────────────

test('super admin can view create tenant page', function () {
    $admin = makeSuperAdmin();

    $this->actingAs($admin)
        ->get('/admin/tenants/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/create')
            ->has('plans')
        );
});

// ─── Store tenant ─────────────────────────────────────────────────────────────

test('super admin can create a tenant and owner is invited', function () {
    Mail::fake();

    $admin = makeSuperAdmin();
    $plan = Plan::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/tenants', [
            'name'        => 'Pearl Aesthetic Center',
            'slug'        => 'pearl',
            'plan_id'     => $plan->id,
            'owner_name'  => 'Dr. Pearl',
            'owner_email' => 'owner@pearl.test',
            'procedures'  => ['rhinoplasty', 'bbl'],
        ])
        ->assertRedirect();

    // Tenant was created
    $this->assertDatabaseHas('tenants', [
        'slug' => 'pearl',
        'name' => 'Pearl Aesthetic Center',
    ]);

    // Owner user was created
    $owner = User::where('email', 'owner@pearl.test')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->role)->toBe(User::ROLE_OWNER);

    // Invitation email was sent
    Mail::assertSent(UserInviteMail::class, fn ($mail) =>
        $mail->hasTo('owner@pearl.test')
    );
});

test('slug must be unique when creating tenant', function () {
    $admin = makeSuperAdmin();
    $plan = Plan::factory()->create();
    Tenant::factory()->create(['slug' => 'taken-slug']);

    $this->actingAs($admin)
        ->post('/admin/tenants', [
            'name'        => 'Duplicate',
            'slug'        => 'taken-slug',
            'plan_id'     => $plan->id,
            'owner_name'  => 'Owner',
            'owner_email' => 'unique@test.test',
        ])
        ->assertSessionHasErrors('slug');
});

// ─── Show tenant ──────────────────────────────────────────────────────────────

test('super admin can view tenant detail page', function () {
    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/tenants/{$tenant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/show')
            ->has('tenant')
            ->has('users')
            ->has('plans')
        );
});

// ─── Update tenant ────────────────────────────────────────────────────────────

test('super admin can update tenant details', function () {
    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create(['name' => 'Old Name']);
    $plan = Plan::factory()->create();

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->id}", [
            'name'    => 'New Name',
            'slug'    => $tenant->slug,
            'plan_id' => $plan->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name']);
});

// ─── Deactivate / restore ─────────────────────────────────────────────────────

test('super admin can deactivate a tenant', function () {
    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->delete("/admin/tenants/{$tenant->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
});

test('super admin can restore a deactivated tenant', function () {
    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create();
    $tenant->delete();

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant->id}/restore")
        ->assertRedirect();

    $this->assertNotSoftDeleted('tenants', ['id' => $tenant->id]);
});

// ─── Add user ─────────────────────────────────────────────────────────────────

test('super admin can add a user to a tenant and invite email is sent', function () {
    Mail::fake();

    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant->id}/users", [
            'name'  => 'Jane Coordinator',
            'email' => 'jane@clinic.test',
            'role'  => User::ROLE_COORDINATOR,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email'     => 'jane@clinic.test',
        'tenant_id' => $tenant->id,
        'role'      => User::ROLE_COORDINATOR,
    ]);

    Mail::assertSent(UserInviteMail::class, fn ($mail) =>
        $mail->hasTo('jane@clinic.test')
    );
});

// ─── Resend invite ────────────────────────────────────────────────────────────

test('super admin can resend invite to a tenant user', function () {
    Mail::fake();

    $admin = makeSuperAdmin();
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant, User::ROLE_COORDINATOR);

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant->id}/users/{$user->id}/resend-invite")
        ->assertRedirect();

    Mail::assertSent(UserInviteMail::class, fn ($mail) =>
        $mail->hasTo($user->email)
    );
});

test('resend invite is blocked for users from a different tenant', function () {
    $admin = makeSuperAdmin();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $user = makeTenantUser($tenant2);

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant1->id}/users/{$user->id}/resend-invite")
        ->assertForbidden();
});

// ─── Login redirect ───────────────────────────────────────────────────────────

test('super admin cannot access tenant dashboard (no tenant context)', function () {
    $admin = makeSuperAdmin();

    // TenantMiddleware throws 404 when tenant_id is null (no clinic to resolve)
    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertNotFound();
});
