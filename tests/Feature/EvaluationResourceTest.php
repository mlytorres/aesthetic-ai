<?php

declare(strict_types=1);

use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeCoordinator(): User
{
    $tenant = Tenant::factory()->create();

    return User::factory()->create([
        'tenant_id' => $this->tenant->id ?? $tenant->id,
        'role'      => User::ROLE_COORDINATOR,
    ]);
}

// ── score_breakdown presence ──────────────────────────────────────────────────

test('score_breakdown is present when evaluation has analysis_data and quiz_answers', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()
        ->withAnalysis()
        ->create([
            'tenant_id'  => $tenant->id,
            'patient_id' => $patient->id,
        ]);

    // withAnalysis sets analysis_data; factory default sets quiz_answers (q_timeline, q_budget etc.)

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data)->toHaveKey('score_breakdown')
        ->and($data['score_breakdown'])->toBeArray()
        ->and($data['score_breakdown'])->toHaveKeys([
            'timeline', 'budget', 'ai_harmony', 'photo_quality', 'concerns', 'referral',
        ]);
});

test('each breakdown factor has label, earned, and max keys', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'  => $tenant->id,
        'patient_id' => $patient->id,
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data      = (new EvaluationResource($evaluation))->toArray($request);
    $breakdown = $data['score_breakdown'];

    foreach (['timeline', 'budget', 'ai_harmony', 'photo_quality', 'concerns', 'referral'] as $key) {
        expect($breakdown[$key])->toHaveKeys(['label', 'earned', 'max'])
            ->and($breakdown[$key]['earned'])->toBeInt()
            ->and($breakdown[$key]['earned'])->toBeGreaterThanOrEqual(0)
            ->and($breakdown[$key]['earned'])->toBeLessThanOrEqual($breakdown[$key]['max']);
    }
});

test('breakdown factor max values sum to 100', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'  => $tenant->id,
        'patient_id' => $patient->id,
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data  = (new EvaluationResource($evaluation))->toArray($request);
    $total = array_sum(array_column($data['score_breakdown'], 'max'));

    expect($total)->toBe(100);
});

test('score_breakdown is absent when analysis_data is empty', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->create([
        'tenant_id'    => $tenant->id,
        'patient_id'   => $patient->id,
        'analysis_data' => [],        // empty — pipeline not yet complete
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data)->not->toHaveKey('score_breakdown');
});

test('score_breakdown is absent when quiz_answers is empty', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'    => $tenant->id,
        'patient_id'   => $patient->id,
        'quiz_answers' => [],         // no answers yet
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data)->not->toHaveKey('score_breakdown');
});

// ── Breakdown values reflect quiz_answers ─────────────────────────────────────

test('timeline factor earns 30 points for asap timeline', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'    => $tenant->id,
        'patient_id'   => $patient->id,
        'quiz_answers' => ['q_timeline' => 'asap', 'q_budget' => 'under_10k', 'q_concerns' => [], 'q_referral' => null],
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data['score_breakdown']['timeline']['earned'])->toBe(30)
        ->and($data['score_breakdown']['timeline']['max'])->toBe(30);
});

test('budget factor earns 25 points for over_25k budget', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'    => $tenant->id,
        'patient_id'   => $patient->id,
        'quiz_answers' => ['q_timeline' => 'researching', 'q_budget' => 'over_25k', 'q_concerns' => [], 'q_referral' => null],
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data['score_breakdown']['budget']['earned'])->toBe(25)
        ->and($data['score_breakdown']['budget']['max'])->toBe(25);
});

test('referral factor earns 5 points for word-of-mouth referral', function (): void {
    $tenant  = Tenant::factory()->create();
    $user    = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COORDINATOR]);
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $evaluation = Evaluation::factory()->withAnalysis()->create([
        'tenant_id'    => $tenant->id,
        'patient_id'   => $patient->id,
        'quiz_answers' => ['q_timeline' => 'researching', 'q_budget' => 'under_10k', 'q_concerns' => [], 'q_referral' => 'referral'],
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $data = (new EvaluationResource($evaluation))->resolve($request);

    expect($data['score_breakdown']['referral']['earned'])->toBe(5)
        ->and($data['score_breakdown']['referral']['max'])->toBe(5);
});
