<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\CampaignAsset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignAsset>
 */
class CampaignAssetFactory extends Factory
{
    protected $model = CampaignAsset::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'affiliate_campaign_id' => AffiliateCampaign::factory(),
            'approved_by_user_id' => User::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'asset_type' => CampaignAsset::TYPE_IMAGE,
            'storage_path' => 'campaign-assets/'.fake()->uuid().'.png',
            'checksum' => hash('sha256', fake()->uuid()),
            'status' => CampaignAsset::STATUS_APPROVED,
            'approved_at' => now(),
            'revoked_at' => null,
            'compliance_notes' => null,
        ];
    }
}
