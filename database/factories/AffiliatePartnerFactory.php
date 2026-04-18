<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliatePartner;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliatePartner>
 */
class AffiliatePartnerFactory extends Factory
{
    protected $model = AffiliatePartner::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'platform' => fake()->randomElement([
                AffiliatePartner::PLATFORM_INSTAGRAM,
                AffiliatePartner::PLATFORM_TIKTOK,
                AffiliatePartner::PLATFORM_YOUTUBE,
            ]),
            'handle' => '@'.fake()->unique()->userName(),
            'status' => AffiliatePartner::STATUS_ACTIVE,
            'payout_cents' => 5000,
            'currency' => 'USD',
            'monthly_cap_cents' => 200000,
            'hold_days' => 14,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
