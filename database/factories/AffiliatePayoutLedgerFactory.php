<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\AffiliatePayoutLedger;
use App\Models\AttributionEvent;
use App\Models\Evaluation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliatePayoutLedger>
 */
class AffiliatePayoutLedgerFactory extends Factory
{
    protected $model = AffiliatePayoutLedger::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'affiliate_campaign_id' => AffiliateCampaign::factory(),
            'affiliate_link_id' => AffiliateLink::factory(),
            'attribution_event_id' => AttributionEvent::factory(),
            'evaluation_id' => Evaluation::factory(),
            'reviewed_by_user_id' => User::factory(),
            'status' => AffiliatePayoutLedger::STATUS_PENDING_HOLD,
            'amount_cents' => 5000,
            'currency' => 'USD',
            'hold_until' => now()->addDays(14),
            'released_at' => null,
            'rejection_reason' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
