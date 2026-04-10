<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => 'Starter',
            'slug' => 'starter-'.fake()->unique()->bothify('??###'),
            'max_procedures' => 1,
            'max_evaluations_mo' => 50,
            'stripe_price_id' => null,
        ];
    }
}
