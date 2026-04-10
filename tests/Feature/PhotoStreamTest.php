<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SecureFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeAuthUserWithPhoto(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    // Use the real hash so SecureFileService and PhotoStreamController agree on the key
    $s3Key = "{$tenant->id}/{$patient->id}/{$evaluation->id}/front_test.png";
    $hash = (new SecureFileService)->hashKey($s3Key);

    $photo = Photo::factory()->create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        's3_key' => $s3Key,
        's3_key_hash' => $hash,
        'type' => Photo::TYPE_FRONT,
    ]);

    return [$user, $photo, $s3Key, $hash];
}

// ─── Authenticated access ─────────────────────────────────────────────────────

test('authenticated user can stream their tenant photo', function (): void {
    Storage::fake('local');

    [$user, $photo, $s3Key, $hash] = makeAuthUserWithPhoto();

    // Put a fake image file at the expected path on the local disk
    Storage::disk('local')->put($s3Key, 'fake-image-bytes');

    $response = $this->actingAs($user)->get(route('photos.stream', $hash));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
});

test('photo stream returns correct mime type for jpeg', function (): void {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $evaluation = Evaluation::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $s3Key = "{$tenant->id}/{$patient->id}/{$evaluation->id}/front_test.jpg";
    $hash = (new SecureFileService)->hashKey($s3Key);

    Photo::factory()->create([
        'tenant_id' => $tenant->id,
        'evaluation_id' => $evaluation->id,
        's3_key' => $s3Key,
        's3_key_hash' => $hash,
    ]);

    Storage::disk('local')->put($s3Key, 'fake-jpeg-bytes');

    $response = $this->actingAs($user)->get(route('photos.stream', $hash));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
});

// ─── Tenant isolation ─────────────────────────────────────────────────────────

test('user from a different tenant cannot stream another tenant photo', function (): void {
    Storage::fake('local');

    [$ownerUser, $photo, $s3Key, $hash] = makeAuthUserWithPhoto();

    // Create a completely separate tenant and user
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => User::ROLE_COORDINATOR]);

    Storage::disk('local')->put($s3Key, 'fake-image-bytes');

    // Other user tries to access photo belonging to different tenant
    $response = $this->actingAs($otherUser)->get(route('photos.stream', $hash));

    $response->assertNotFound();
});

// ─── Missing file on disk ─────────────────────────────────────────────────────

test('returns 404 when photo record exists but file is missing from disk', function (): void {
    Storage::fake('local'); // empty — no files

    [$user, $photo, $s3Key, $hash] = makeAuthUserWithPhoto();

    // File is NOT placed on disk
    $response = $this->actingAs($user)->get(route('photos.stream', $hash));

    $response->assertNotFound();
});

// ─── Invalid hash ─────────────────────────────────────────────────────────────

test('returns 404 for unknown hash', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);

    $response = $this->actingAs($user)->get(route('photos.stream', 'not-a-real-hash'));

    $response->assertNotFound();
});

// ─── Unauthenticated ─────────────────────────────────────────────────────────

test('unauthenticated request to photo stream is redirected to login', function (): void {
    Storage::fake('local');

    [$user, $photo, $s3Key, $hash] = makeAuthUserWithPhoto();
    Storage::disk('local')->put($s3Key, 'fake-image-bytes');

    $response = $this->get(route('photos.stream', $hash));

    $response->assertRedirect(route('login'));
});

// ─── SecureFileService::getSignedUrl() in dev mode ───────────────────────────

test('getSignedUrl returns photos.stream route URL when FEATURE_AI_VISION=false', function (): void {
    config(['features.ai_vision' => false]);

    $key = 'tenant-id/patient-id/eval-id/front_20260409.jpg';
    $svc = new SecureFileService;

    $url = $svc->getSignedUrl($key);

    $expectedHash = $svc->hashKey($key);
    expect($url)->toBe(route('photos.stream', $expectedHash));
});
