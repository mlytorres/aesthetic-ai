<?php

declare(strict_types=1);

use App\Mail\UserInviteMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('owner can invite a team member and invite email is sent', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($owner)->post('/clinic/team', [
        'name' => 'Dr. Jane Smith',
        'email' => 'jane@example.com',
        'role' => User::ROLE_SURGEON,
    ])->assertRedirect();

    // User was created in the correct tenant
    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_SURGEON,
    ]);

    // Invite email was sent to the new user
    Mail::assertSent(UserInviteMail::class, fn (UserInviteMail $mail) => $mail->hasTo('jane@example.com')
        && $mail->user->email === 'jane@example.com'
        && $mail->tenant->id === $tenant->id
    );
});

test('admin can invite a team member and email is sent', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    app(TenantContext::class)->set($tenant);

    $this->actingAs($admin)->post('/clinic/team', [
        'name' => 'Coordinator Bob',
        'email' => 'bob@example.com',
        'role' => User::ROLE_COORDINATOR,
    ])->assertRedirect();

    Mail::assertSent(UserInviteMail::class, fn (UserInviteMail $mail) => $mail->hasTo('bob@example.com')
    );
});

test('invite email is not sent on validation failure', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);
    app(TenantContext::class)->set($tenant);

    // Missing required fields
    $this->actingAs($owner)->post('/clinic/team', [
        'name' => '',
        'email' => 'not-an-email',
        'role' => User::ROLE_SURGEON,
    ])->assertSessionHasErrors(['name', 'email']);

    Mail::assertNothingSent();
});
