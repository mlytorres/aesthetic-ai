<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateLink;
use App\Models\AffiliatePartner;
use App\Models\CampaignAsset;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateLink>
 */
class AffiliateLinkFactory extends Factory
{
    protected $model = AffiliateLink::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'affiliate_partner_id' => AffiliatePartner::factory(),
            'affiliate_campaign_id' => AffiliateCampaign::factory(),
            'campaign_asset_id' => CampaignAsset::factory(),
            'token' => Str::random(40),
            'status' => AffiliateLink::STATUS_ACTIVE,
            'click_count' => 0,
            'last_clicked_at' => null,
        ];
    }
}
