<?php

declare(strict_types=1);

use App\Models\Plan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('database seeder creates non public free plan', function (): void {
    $this->seed(DatabaseSeeder::class);

    $freePlan = Plan::query()->where('slug', 'free')->first();

    expect($freePlan)->not->toBeNull()
        ->and($freePlan?->is_public)->toBeFalse()
        ->and($freePlan?->max_procedures)->toBeNull()
        ->and($freePlan?->max_evaluations_mo)->toBeNull();
});
