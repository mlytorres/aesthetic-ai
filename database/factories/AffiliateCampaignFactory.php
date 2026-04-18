<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateCampaign>
 */
class AffiliateCampaignFactory extends Factory
{
    protected $model = AffiliateCampaign::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->words(2, true));

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'status' => AffiliateCampaign::STATUS_ACTIVE,
            'description' => fake()->sentence(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'default_payout_cents' => 5000,
            'currency' => 'USD',
            'monthly_cap_cents' => 500000,
            'hold_days' => 14,
            'settings' => ['approved_assets_only' => true],
        ];
    }
}
