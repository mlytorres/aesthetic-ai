<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\MagicLink;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Setup helpers ────────────────────────────────────────────────────────────

function makeTenantWithOwner(): array
{
    $tenant = Tenant::factory()->create(['slug' => 'testclinic']);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
    ]);

    return [$tenant, $owner];
}

function makeEvaluationForTenant(Tenant $tenant): Evaluation
{
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    return Evaluation::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'status' => Evaluation::STATUS_COMPLETE,
    ]);
}

// ─── Valid magic link ─────────────────────────────────────────────────────────

test('valid magic link logs in coordinator and redirects to evaluation', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    $response->assertRedirect(route('evaluations.show', $evaluation->id));
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($owner->id);
});

test('valid magic link resolves user by recipient_email', function (): void {
    [$tenant] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    $coordinator = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COORDINATOR,
    ]);

    [$link, $rawToken] = MagicLink::generate($evaluation, $coordinator->email);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    expect(Auth::id())->toBe($coordinator->id);
});

test('valid magic link falls back to owner when no recipient_email match', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    // Generate with an email that does not match any user
    [$link, $rawToken] = MagicLink::generate($evaluation, 'nobody@example.com');

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    // Should fall back to the owner
    expect(Auth::id())->toBe($owner->id);
});

test('magic link is marked used after consumption', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);

    $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    $link->refresh();
    expect($link->used_at)->not->toBeNull();
});

// ─── Already-used link ────────────────────────────────────────────────────────

test('already-used magic link redirects to login with error', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);
    $link->markUsed();

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

// ─── Expired link ─────────────────────────────────────────────────────────────

test('expired magic link redirects to login with error', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);
    // Force expiry
    $link->update(['expires_at' => now()->subMinutes(1)]);

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', $rawToken));

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

// ─── Invalid token ────────────────────────────────────────────────────────────

test('invalid magic link token redirects to login with error', function (): void {
    // Need a tenant in the header for TenantMiddleware to resolve
    [$tenant] = makeTenantWithOwner();

    $response = $this->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->get(route('magic-link.use', 'not-a-real-token-at-all'));

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

// ─── MagicLink::generate() ────────────────────────────────────────────────────

test('generate returns a model and a raw token', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    // Set TenantContext so HasTenantScope::creating hook can auto-fill tenant_id
    app(TenantContext::class)->set($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);

    expect($link)->toBeInstanceOf(MagicLink::class);
    expect($rawToken)->toBeString()->toHaveLength(64);
    expect($link->token_hash)->toBe(hash('sha256', $rawToken));
    expect($link->recipient_email)->toBe($owner->email);
    expect($link->expires_at->isFuture())->toBeTrue();
    expect($link->used_at)->toBeNull();
});

test('generate does not store the raw token in the database', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    app(TenantContext::class)->set($tenant);

    [$link, $rawToken] = MagicLink::generate($evaluation, $owner->email);

    // The raw token must NOT appear anywhere in the magic_links row
    $row = DB::table('magic_links')->find($link->id);
    expect($row->token_hash)->not->toBe($rawToken);
    expect($row->token_hash)->toBe(hash('sha256', $rawToken));
});

// ─── PruneMagicLinksCommand ───────────────────────────────────────────────────

test('magic-links:prune deletes expired and used links', function (): void {
    [$tenant, $owner] = makeTenantWithOwner();
    $evaluation = makeEvaluationForTenant($tenant);

    app(TenantContext::class)->set($tenant);

    // Expired
    MagicLink::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'token_hash' => hash('sha256', 'expired-token'),
        'expires_at' => now()->subHour(),
    ]);

    // Used
    MagicLink::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'token_hash' => hash('sha256', 'used-token'),
        'expires_at' => now()->addMinutes(15),
        'used_at' => now()->subMinutes(5),
    ]);

    // Still valid — should NOT be pruned
    MagicLink::create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        'token_hash' => hash('sha256', 'valid-token'),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->artisan('magic-links:prune')->assertExitCode(0);

    expect(MagicLink::withoutGlobalScopes()->count())->toBe(1);
    expect(MagicLink::withoutGlobalScopes()->where('token_hash', hash('sha256', 'valid-token'))->exists())->toBeTrue();
});
