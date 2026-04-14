<?php

declare(strict_types=1);

use App\Models\Procedure;
use App\Models\QuizDefinition;
use App\Models\Tenant;
use App\Models\User;

// ─── Helpers (reuse pattern from AdminTenantTest) ─────────────────────────────

function quizSuperAdmin(): User
{
    return User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_OWNER]);
}

function quizTenantUser(): User
{
    $tenant = Tenant::factory()->create();

    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);
}

/** @return array<int, array<string, mixed>> */
function sampleQuestions(): array
{
    return [
        [
            'id' => 'q_concerns',
            'type' => 'multiselect',
            'label' => 'Primary goals?',
            'required' => true,
            'options' => [
                ['value' => 'volume', 'label' => 'More volume'],
                ['value' => 'lift',   'label' => 'Lift and reshape'],
            ],
            'branches' => [],
        ],
        [
            'id' => 'q_timeline',
            'type' => 'select',
            'label' => 'Your timeline?',
            'required' => true,
            'options' => [
                ['value' => 'asap',     'label' => 'As soon as possible'],
                ['value' => '3_months', 'label' => 'Within 3 months'],
            ],
            'branches' => ['*' => ['next' => 'q_budget']],
        ],
        [
            'id' => 'q_budget',
            'type' => 'select',
            'label' => 'Approximate budget?',
            'required' => true,
            'options' => [
                ['value' => 'under_10k', 'label' => 'Under $10,000'],
                ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
            ],
            'branches' => ['*' => ['next' => 'q_referral']],
        ],
        [
            'id' => 'q_referral',
            'type' => 'select',
            'label' => 'How did you hear about us?',
            'required' => false,
            'options' => [
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'google',    'label' => 'Google'],
            ],
            'branches' => [],
        ],
    ];
}

// ─── Access control ───────────────────────────────────────────────────────────

test('guests cannot access quiz editor', function () {
    $this->get('/admin/quizzes')->assertRedirect('/login');
});

test('tenant users cannot access quiz editor', function () {
    $user = quizTenantUser();

    $this->actingAs($user)
        ->get('/admin/quizzes')
        ->assertForbidden();
});

test('super admin can access quiz index', function () {
    $admin = quizSuperAdmin();

    $this->actingAs($admin)
        ->get('/admin/quizzes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/quizzes/index'));
});

// ─── Quiz index ───────────────────────────────────────────────────────────────

test('quiz index lists all procedures with quiz status', function () {
    $admin = quizSuperAdmin();

    $procedure = Procedure::factory()->create([
        'slug' => 'test_procedure',
        'label' => 'Test Procedure',
        'category' => 'body',
        'active' => true,
    ]);

    QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => true,
        'version' => 1,
        'questions' => sampleQuestions(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/quizzes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/quizzes/index')
            ->has('procedures')
        );
});

// ─── Quiz show ────────────────────────────────────────────────────────────────

test('super admin can view quiz for a procedure', function () {
    $admin = quizSuperAdmin();

    $procedure = Procedure::factory()->create([
        'slug' => 'test_show_procedure',
        'label' => 'Show Test',
        'category' => 'face',
        'active' => true,
    ]);

    QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => true,
        'version' => 1,
        'questions' => sampleQuestions(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/quizzes/test_show_procedure')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/quizzes/show')
            ->where('procedure.slug', 'test_show_procedure')
            ->has('activeQuiz.questions')
        );
});

test('quiz show page works even when no quiz exists yet', function () {
    $admin = quizSuperAdmin();

    Procedure::factory()->create([
        'slug' => 'test_no_quiz',
        'label' => 'No Quiz Yet',
        'category' => 'body',
        'active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/quizzes/test_no_quiz')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/quizzes/show')
            ->where('activeQuiz', null)
        );
});

// ─── Quiz update ─────────────────────────────────────────────────────────────

test('super admin can create a quiz for a procedure that has none', function () {
    $admin = quizSuperAdmin();

    Procedure::factory()->create([
        'slug' => 'test_create_quiz',
        'label' => 'Create Quiz Test',
        'category' => 'body',
        'active' => true,
    ]);

    $this->actingAs($admin)
        ->patch('/admin/quizzes/test_create_quiz', ['questions' => sampleQuestions()])
        ->assertRedirect('/admin/quizzes/test_create_quiz');

    expect(
        QuizDefinition::where('procedure_slug', 'test_create_quiz')
            ->where('is_active', true)
            ->exists()
    )->toBeTrue();
});

test('saving a quiz creates a new version and archives the old one', function () {
    $admin = quizSuperAdmin();

    $procedure = Procedure::factory()->create([
        'slug' => 'test_version_bump',
        'label' => 'Version Bump',
        'category' => 'body',
        'active' => true,
    ]);

    QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => true,
        'version' => 1,
        'questions' => sampleQuestions(),
    ]);

    $this->actingAs($admin)
        ->patch('/admin/quizzes/test_version_bump', ['questions' => sampleQuestions()])
        ->assertRedirect('/admin/quizzes/test_version_bump');

    $active = QuizDefinition::where('procedure_slug', 'test_version_bump')
        ->where('is_active', true)
        ->first();

    expect($active->version)->toBe(2);

    $archived = QuizDefinition::where('procedure_slug', 'test_version_bump')
        ->where('is_active', false)
        ->first();

    expect($archived)->not->toBeNull();
    expect($archived->version)->toBe(1);
});

test('saving a quiz replaces the previous archived version', function () {
    $admin = quizSuperAdmin();

    $procedure = Procedure::factory()->create([
        'slug' => 'test_archive_replace',
        'label' => 'Archive Replace',
        'category' => 'body',
        'active' => true,
    ]);

    QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => true,
        'version' => 2,
        'questions' => sampleQuestions(),
    ]);

    QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => false,
        'version' => 1,
        'questions' => sampleQuestions(),
    ]);

    $this->actingAs($admin)
        ->patch('/admin/quizzes/test_archive_replace', ['questions' => sampleQuestions()])
        ->assertRedirect('/admin/quizzes/test_archive_replace');

    // Only one archived version should exist (old v1 was purged, v2 is now archived)
    $archivedCount = QuizDefinition::where('procedure_slug', 'test_archive_replace')
        ->where('is_active', false)
        ->count();

    expect($archivedCount)->toBe(1);

    $active = QuizDefinition::where('procedure_slug', 'test_archive_replace')
        ->where('is_active', true)
        ->first();

    expect($active->version)->toBe(3);
});

test('quiz update validates required fields', function () {
    $admin = quizSuperAdmin();

    Procedure::factory()->create([
        'slug' => 'test_validation',
        'label' => 'Validation Test',
        'category' => 'body',
        'active' => true,
    ]);

    $this->actingAs($admin)
        ->patch('/admin/quizzes/test_validation', ['questions' => []])
        ->assertSessionHasErrors('questions');
});

test('quiz update rejects unknown question type', function () {
    $admin = quizSuperAdmin();

    Procedure::factory()->create([
        'slug' => 'test_bad_type',
        'label' => 'Bad Type',
        'category' => 'body',
        'active' => true,
    ]);

    $badQuestions = sampleQuestions();
    $badQuestions[0]['type'] = 'slider'; // invalid type

    $this->actingAs($admin)
        ->patch('/admin/quizzes/test_bad_type', ['questions' => $badQuestions])
        ->assertSessionHasErrors('questions.0.type');
});

// ─── Version activation ───────────────────────────────────────────────────────

test('super admin can restore a previous version', function () {
    $admin = quizSuperAdmin();

    $procedure = Procedure::factory()->create([
        'slug' => 'test_restore',
        'label' => 'Restore Test',
        'category' => 'body',
        'active' => true,
    ]);

    $active = QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => true,
        'version' => 2,
        'questions' => sampleQuestions(),
    ]);

    $archived = QuizDefinition::factory()->create([
        'procedure_slug' => $procedure->slug,
        'is_active' => false,
        'version' => 1,
        'questions' => sampleQuestions(),
    ]);

    $this->actingAs($admin)
        ->post("/admin/quizzes/test_restore/versions/{$archived->id}/activate")
        ->assertRedirect('/admin/quizzes/test_restore');

    expect(
        QuizDefinition::where('procedure_slug', 'test_restore')
            ->where('is_active', true)
            ->where('version', 1)
            ->exists()
    )->toBeTrue();
});
