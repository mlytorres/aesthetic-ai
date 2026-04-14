<?php

declare(strict_types=1);

namespace Tests\Feature\Patient;

use App\Models\Evaluation;
use App\Models\Tenant;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_accessible_via_valid_secure_token(): void
    {
        $tenant = Tenant::factory()->create();
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'secure_token' => 'test-secure-token-123',
            'status' => Evaluation::STATUS_ANALYZING,
        ]);

        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/test-secure-token-123');

        $response->assertStatus(200);
        
        // Assert passed accurate inertia props
        $response->assertInertia(fn ($page) => $page
            ->component('patient/portal')
            ->has('evaluation', fn ($page) => $page
                ->where('id', $evaluation->id)
                ->where('secure_token', 'test-secure-token-123')
                ->etc()
            )
            ->where('status', 'analyzing')
            ->where('isComplete', false)
            ->has('tenant', fn ($page) => $page
                ->where('id', $tenant->id)
                ->etc()
            )
        );
    }

    public function test_portal_correctly_marks_is_complete(): void
    {
        $tenant = Tenant::factory()->create();
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'secure_token' => 'complete-token',
            'status' => Evaluation::STATUS_COMPLETE, // Should assert true
            'lead_score' => 85,
        ]);

        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/complete-token');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('isComplete', true)
                ->where('status', 'complete')
            );
    }

    public function test_portal_returns_404_for_invalid_token(): void
    {
        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/invalid-token-xyz');
        $response->assertStatus(404);
    }

    public function test_portal_passes_booking_url_when_set(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['booking_url' => 'https://calendly.com/testclinic'],
        ]);
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'secure_token' => 'booking-url-token',
        ]);

        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/booking-url-token');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('bookingUrl', 'https://calendly.com/testclinic')
                ->where('phone', null)
            );
    }

    public function test_portal_passes_phone_when_set(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['phone' => '+1 305 555 0100'],
        ]);
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'secure_token' => 'phone-token',
        ]);

        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/phone-token');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('phone', '+1 305 555 0100')
                ->where('bookingUrl', null)
            );
    }

    public function test_portal_passes_null_contact_info_when_not_configured(): void
    {
        $tenant = Tenant::factory()->create(['settings' => []]);
        $evaluation = Evaluation::factory()->create([
            'tenant_id' => $tenant->id,
            'secure_token' => 'no-contact-token',
        ]);

        $response = $this->withoutMiddleware(TenantMiddleware::class)
            ->get('/intake/portal/no-contact-token');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('phone', null)
                ->where('bookingUrl', null)
            );
    }
}
