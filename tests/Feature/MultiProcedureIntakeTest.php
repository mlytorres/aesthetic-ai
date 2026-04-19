<?php

declare(strict_types=1);

use App\Models\Evaluation;
use App\Models\Procedure;
use App\Models\QuizDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\ProcedureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Set the tenant context and return headers for HTTP requests.
 *
 * @return array<string, mixed>
 */
function tenantHeaders(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);

    return ['X-Clinic-ID' => $tenant->id];
}

// ─── Procedure + Quiz Seeding ─────────────────────────────────────────────────

test('all procedures are seeded and active', function (): void {
    $this->seed(ProcedureSeeder::class);

    $slugs = Procedure::where('active', true)->pluck('slug')->sort()->values()->all();

    // Core MVP procedures are always present
    expect($slugs)->toContain('bbl')
        ->and($slugs)->toContain('breast_augmentation')
        ->and($slugs)->toContain('facelift')
        ->and($slugs)->toContain('lipo_360')
        ->and($slugs)->toContain('rhinoplasty')
        // New procedures
        ->and($slugs)->toContain('tummy_tuck')
        ->and($slugs)->toContain('mommy_makeover')
        ->and($slugs)->toContain('breast_lift')
        ->and($slugs)->toContain('breast_reduction')
        ->and($slugs)->toContain('skinny_bbl')
        ->and($slugs)->toContain('eyelid_surgery')
        ->and($slugs)->toContain('gynecomastia')
        ->and($slugs)->toContain('face_and_neck_lift');

    // Total must include all MVP + all new procedures
    expect(count($slugs))->toBeGreaterThanOrEqual(26);
});

test('each active procedure has exactly one active quiz definition', function (): void {
    $this->seed(ProcedureSeeder::class);

    $procedures = Procedure::where('active', true)->get();

    foreach ($procedures as $procedure) {
        $activeQuizCount = QuizDefinition::where('procedure_slug', $procedure->slug)
            ->where('is_active', true)
            ->count();

        expect($activeQuizCount)
            ->toBe(1, "Procedure [{$procedure->slug}] should have exactly one active quiz definition");
    }
});

test('each quiz definition has q_timeline, q_budget, q_concerns, q_referral questions', function (): void {
    $this->seed(ProcedureSeeder::class);

    $requiredKeys = ['q_timeline', 'q_budget', 'q_concerns', 'q_referral'];

    QuizDefinition::where('is_active', true)->each(function (QuizDefinition $quiz) use ($requiredKeys): void {
        $questionIds = collect($quiz->questions)->pluck('id')->all();

        foreach ($requiredKeys as $key) {
            // Note: Pest toContain() does not accept a custom failure message as 2nd arg
            expect($questionIds)->toContain($key);
        }
    });
});

// ─── Tenant procedure filtering ────────────────────────────────────────────────

test('intake wizard only shows procedures enabled for the tenant', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['bbl', 'lipo_360']],
    ]);

    $response = $this->withHeaders(tenantHeaders($tenant))
        ->get(route('intake.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/wizard')
            ->has('procedures', 2)
        );
});

test('intake wizard shows all 5 procedures when all are enabled', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => [
            'procedures_enabled' => ['rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift'],
        ],
    ]);

    $this->withHeaders(tenantHeaders($tenant))
        ->get(route('intake.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/wizard')
            ->has('procedures', 5)
        );
});

test('each procedure in wizard response includes a quiz with questions', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['rhinoplasty', 'bbl']],
    ]);

    // Procedures are returned in DB order (not guaranteed) so assert structure, not index.
    // Quiz content is already verified by the seeder test above; here we just confirm
    // the wizard prop includes a non-null quiz with questions for each procedure.
    $this->withHeaders(tenantHeaders($tenant))
        ->get(route('intake.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/wizard')
            ->has('procedures', 2)
            ->has('procedures.0.quiz')
            ->has('procedures.0.quiz.questions')
            ->has('procedures.1.quiz')
            ->has('procedures.1.quiz.questions')
        );
});

// ─── Multi-procedure intake flow ───────────────────────────────────────────────

test('can create an evaluation for bbl procedure', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['bbl']],
    ]);

    $this->withHeaders(tenantHeaders($tenant))
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => 'bbl'])
        ->assertCreated()
        ->assertJsonStructure(['token', 'status']);

    expect(
        Evaluation::withoutGlobalScopes()
            ->where('procedure_slug', 'bbl')
            ->where('tenant_id', $tenant->id)
            ->exists()
    )->toBeTrue();
});

test('can create an evaluation for each of the 5 procedures', function (string $slug): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => [$slug]],
    ]);

    $this->withHeaders(tenantHeaders($tenant))
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => $slug])
        ->assertCreated();

    expect(
        Evaluation::withoutGlobalScopes()
            ->where('procedure_slug', $slug)
            ->where('tenant_id', $tenant->id)
            ->exists()
    )->toBeTrue();
})->with(['rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift']);

test('cannot create evaluation for a procedure not enabled by tenant', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['rhinoplasty']],
    ]);

    $this->withHeaders(tenantHeaders($tenant))
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => 'bbl'])
        ->assertStatus(402); // plan enforcement: procedure not in tenant's enabled list
});

test('quiz answers can be saved for a bbl evaluation', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['bbl']],
    ]);

    $headers = tenantHeaders($tenant);

    // Create the evaluation
    $response = $this->withHeaders($headers)
        ->postJson(route('intake.evaluations.store'), ['procedure_slug' => 'bbl'])
        ->assertCreated();

    $token = $response->json('token');

    // Submit quiz answers
    $this->withHeaders($headers)
        ->postJson(route('intake.evaluations.quiz', $token), [
            'answers' => [
                'q_concerns' => ['volume', 'hourglass'],
                'q_donor_areas' => ['abdomen', 'flanks'],
                'q_weight_stable' => true,
                'q_timeline' => '3_months',
                'q_budget' => '10k_15k',
                'q_referral' => 'instagram',
            ],
        ])
        ->assertOk();

    $evaluation = Evaluation::withoutGlobalScopes()
        ->where('procedure_slug', 'bbl')
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($evaluation->quiz_answers['q_concerns'])->toBe(['volume', 'hourglass'])
        ->and($evaluation->funnel_step)->toBe(Evaluation::FUNNEL_QUIZ);
});

// ─── Clinic settings — procedure management ────────────────────────────────────

test('clinic settings update can enable and disable procedures', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['rhinoplasty']],
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

    app(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Clinic-ID' => $tenant->id])
        ->patch(route('clinic.settings.update'), [
            'name' => $tenant->name,
            'theme' => 'luxury-dark',
            'brand_primary' => null,
            'brand_font' => null,
            'from_name' => null,
            'custom_domain' => null,
            'locale' => 'en',
            'procedures_enabled' => ['bbl', 'lipo_360'],
            'coordinator_emails' => [],
            'phone' => null,
            'booking_url' => null,
            'lead_capture_position' => 'end',
            'embed_parent_origins' => [],
        ])
        ->assertRedirect();

    $tenant->refresh();
    expect($tenant->settings['procedures_enabled'])->toBe(['bbl', 'lipo_360']);
});

test('clinic settings requires at least one procedure enabled', function (): void {
    $this->seed(ProcedureSeeder::class);

    $tenant = Tenant::factory()->create([
        'settings' => ['procedures_enabled' => ['rhinoplasty']],
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

    app(TenantContext::class)->set($tenant);

    // Use patchJson so validation failures return 422 rather than 302 redirect
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
            'procedures_enabled' => [],
            'coordinator_emails' => [],
            'phone' => null,
            'booking_url' => null,
            'lead_capture_position' => 'end',
            'embed_parent_origins' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('procedures_enabled');
});
