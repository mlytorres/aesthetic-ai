<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliatePartner;
use App\Models\AffiliateTermsAcceptance;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateTermsAcceptance>
 */
class AffiliateTermsAcceptanceFactory extends Factory
{
    protected $model = AffiliateTermsAcceptance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'terms_version' => 'v1.0',
            'accepted_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'proof_url' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
