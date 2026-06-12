<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\HandbookService;

test('guests are redirected away from the help center', function () {
    $this->get(route('help.index'))->assertRedirect(route('login'));
});

test('authenticated staff can view the help center index', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('help.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/index')
            ->has('chapters', 8));
});

test('authenticated staff can view a handbook chapter', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('help.show', 'evaluations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/show')
            ->where('article.slug', 'evaluations')
            ->where('article.title', 'Evaluations')
            ->has('article.content'));
});

test('unknown help slugs return not found', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('help.show', 'does-not-exist'))
        ->assertNotFound();
});

test('the handbook service strips the leading H1', function () {
    $article = app(HandbookService::class)->article('getting-started');

    expect($article)->not->toBeNull()
        ->and($article['content'])->not->toStartWith('# ')
        ->and($article['content'])->toContain('## Sign in');
});
