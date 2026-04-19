<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'plan_id' => Plan::factory(),
            // Default to an active 14-day trial so tests don't hit the billing gate.
            // Use Tenant::factory()->expired() or set trial_ends_at explicitly to test billing logic.
            'trial_ends_at' => now()->addDays(14),
            'settings' => [],
        ];
    }

    /**
     * Tenant whose trial has already expired and has no subscription.
     * Use to test billing gate / paywall behaviour.
     */
    public function expired(): static
    {
        return $this->state([
            'trial_ends_at' => now()->subDay(),
            'stripe_id' => null,
        ]);
    }

    /**
     * Clinic has a recorded Business Associate Agreement (HIPAA).
     */
    public function withBaa(): static
    {
        return $this->state([
            'baa_signed_at' => now()->subMonth(),
        ]);
    }
}
