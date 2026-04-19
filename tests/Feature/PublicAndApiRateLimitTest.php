<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('widget loader is rate limited after 30 requests per minute per ip', function (): void {
    RateLimiter::clear('widget-loader');

    for ($i = 0; $i < 30; $i++) {
        $response = $this->get('/widget.js');
        expect($response->status())->not->toBe(429);
    }

    $this->get('/widget.js')->assertStatus(429);
});

test('magic link route is rate limited after 10 requests per minute per token and ip', function (): void {
    RateLimiter::clear('magic-link');

    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $token = 'fake-magic-token';

    for ($i = 0; $i < 10; $i++) {
        $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
            ->get("/magic/{$token}");
        expect($response->status())->not->toBe(429);
    }

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get("/magic/{$token}")
        ->assertStatus(429);
});

test('api v1 ping is rate limited after 120 requests per minute per tenant and ip', function (): void {
    RateLimiter::clear('api.v1');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    app(TenantContext::class)->set($tenant);

    for ($i = 0; $i < 120; $i++) {
        $response = $this->actingAs($user)->get('/api/v1/ping');
        expect($response->status())->toBe(200);
    }

    $this->actingAs($user)
        ->get('/api/v1/ping')
        ->assertStatus(429);
});
