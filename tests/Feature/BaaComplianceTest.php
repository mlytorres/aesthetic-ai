<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\ProcedureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function baaTenantHeaders(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);

    return ['X-Clinic-ID' => $tenant->id];
}

function makeBaaSuperAdmin(): User
{
    return User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
}

test('intake evaluation create is forbidden when BAA enforcement is on and clinic has no BAA date', function (): void {
    config(['security.require_baa_for_intake_submissions' => true]);

    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['bbl']],
        'baa_signed_at' => null,
    ]);

    $this->withHeaders(baaTenantHeaders($tenant))
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => 'bbl'])
        ->assertForbidden()
        ->assertJson(['reason' => 'baa_required']);
});

test('intake evaluation create succeeds when BAA enforcement is on and clinic has executed BAA', function (): void {
    config(['security.require_baa_for_intake_submissions' => true]);

    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->withBaa()->create([
        'settings' => ['procedures_enabled' => ['bbl']],
    ]);

    $this->withHeaders(baaTenantHeaders($tenant))
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => 'bbl'])
        ->assertCreated();
});

test('super admin can record BAA date', function (): void {
    $admin = makeBaaSuperAdmin();
    $tenant = Tenant::factory()->create(['baa_signed_at' => null]);

    $this->actingAs($admin)
        ->patch("/admin/tenants/{$tenant->id}/baa", [
            'baa_signed_at' => '2026-01-15',
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->baa_signed_at)->not->toBeNull()
        ->and($tenant->baa_signed_at->format('Y-m-d'))->toBe('2026-01-15');
});

test('super admin can upload and download BAA PDF', function (): void {
    Storage::fake('local');

    $admin = makeBaaSuperAdmin();
    $tenant = Tenant::factory()->create();

    $file = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

    $this->actingAs($admin)
        ->post("/admin/tenants/{$tenant->id}/baa/document", [
            'document' => $file,
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->baa_document_path)->not->toBeNull();

    $this->actingAs($admin)
        ->get("/admin/tenants/{$tenant->id}/baa/document")
        ->assertOk();
});

test('super admin can delete BAA PDF', function (): void {
    Storage::fake('local');

    $admin = makeBaaSuperAdmin();
    $tenant = Tenant::factory()->create();

    $path = 'baa-documents/'.$tenant->id.'/baa-signed.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 fake');
    $tenant->update(['baa_document_path' => $path]);

    $this->actingAs($admin)
        ->delete("/admin/tenants/{$tenant->id}/baa/document")
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->baa_document_path)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});
