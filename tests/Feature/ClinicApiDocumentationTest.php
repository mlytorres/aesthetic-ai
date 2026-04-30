<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('authenticated owner can view clinic api-docs', function (): void {
    $tenant = Tenant::factory()->create();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
    ]);

    $this->actingAs($user)
        ->get(route('clinic.api-docs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clinic/api-docs'));
});
