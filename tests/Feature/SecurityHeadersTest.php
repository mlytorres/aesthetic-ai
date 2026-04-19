<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\ProcedureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home response includes security headers and denies framing', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toBeString()->toContain("frame-ancestors 'none'");
});

test('intake wizard uses tenant csp without x-frame-options deny', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => [
            'procedures_enabled' => ['rhinoplasty'],
        ],
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('intake.show'));

    $response->assertOk()
        ->assertHeaderMissing('X-Frame-Options');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toBeString()->toContain("frame-ancestors 'none'");
});

test('intake wizard allows framing from configured parent origins', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => [
            'procedures_enabled' => ['rhinoplasty'],
            'embed_parent_origins' => ['https://partner.example'],
        ],
    ]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('intake.show'));

    $response->assertOk();

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('frame-ancestors https://partner.example');
});

test('hsts is not sent in testing environment', function (): void {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

test('clinic settings rejects invalid embed parent origins', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['rhinoplasty']],
    ]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
    ]);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patchJson(route('clinic.settings.update'), [
            'name' => $tenant->name,
            'theme' => 'luxury-dark',
            'brand_primary' => null,
            'brand_font' => null,
            'from_name' => null,
            'custom_domain' => null,
            'locale' => 'en',
            'procedures_enabled' => ['rhinoplasty'],
            'coordinator_emails' => [],
            'phone' => null,
            'booking_url' => null,
            'lead_capture_position' => 'end',
            'embed_parent_origins' => ['https://bad host'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('embed_parent_origins');
});
