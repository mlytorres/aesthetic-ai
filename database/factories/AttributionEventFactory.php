<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributionEvent>
 */
class AttributionEventFactory extends Factory
{
    protected $model = AttributionEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'affiliate_link_id' => AffiliateLink::factory(),
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'affiliate_campaign_id' => AffiliateCampaign::factory(),
            'evaluation_id' => Evaluation::factory(),
            'event_type' => AttributionEvent::TYPE_CLICK,
            'idempotency_key' => fake()->uuid(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'metadata' => ['source' => 'factory'],
            'occurred_at' => now(),
        ];
    }
}
