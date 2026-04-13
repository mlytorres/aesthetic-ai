<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;

describe('Evaluation CSV export', function (): void {
    beforeEach(function (): void {
        Plan::factory()->create(['slug' => 'starter']);
        $this->tenant = Tenant::factory()->create();
    });

    it('allows owners to download the CSV', function (): void {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_OWNER,
        ]);

        Evaluation::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($owner)
            ->get('/evaluations/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');
    });

    it('allows admins to download the CSV', function (): void {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get('/evaluations/export')
            ->assertOk();
    });

    it('allows coordinators to download the CSV', function (): void {
        $coordinator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_COORDINATOR,
        ]);

        $this->actingAs($coordinator)
            ->get('/evaluations/export')
            ->assertOk();
    });

    it('forbids viewers from downloading the CSV', function (): void {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_VIEWER,
        ]);

        $this->actingAs($viewer)
            ->get('/evaluations/export')
            ->assertForbidden();
    });

    it('forbids surgeons from downloading the CSV', function (): void {
        $surgeon = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_SURGEON,
        ]);

        $this->actingAs($surgeon)
            ->get('/evaluations/export')
            ->assertForbidden();
    });

    it('CSV contains a header row and one row per evaluation', function (): void {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_OWNER,
        ]);

        Evaluation::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($owner)->get('/evaluations/export');
        $response->assertOk();

        $lines = array_filter(explode("\n", ltrim($response->streamedContent(), "\xEF\xBB\xBF")));
        // Header + 5 data rows
        expect(count($lines))->toBe(6);
    });

    it('filters by status when query param is provided', function (): void {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_OWNER,
        ]);

        Evaluation::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => Evaluation::STATUS_BOOKED,
        ]);

        Evaluation::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => Evaluation::STATUS_ANALYZING,
        ]);

        $response = $this->actingAs($owner)->get('/evaluations/export?status=booked');
        $response->assertOk();

        $lines = array_filter(explode("\n", ltrim($response->streamedContent(), "\xEF\xBB\xBF")));
        // Header + 2 booked rows
        expect(count($lines))->toBe(3);
    });

    it('scopes export to the authenticated tenant only', function (): void {
        $otherTenant = Tenant::factory()->create();

        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_OWNER,
        ]);

        Evaluation::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        Evaluation::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($owner)->get('/evaluations/export');
        $response->assertOk();

        $lines = array_filter(explode("\n", ltrim($response->streamedContent(), "\xEF\xBB\xBF")));
        // Header + 2 own rows — other tenant's 5 must NOT appear
        expect(count($lines))->toBe(3);
    });
});
