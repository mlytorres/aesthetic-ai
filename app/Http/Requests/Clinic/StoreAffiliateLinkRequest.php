<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use App\Models\AffiliateLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = TenantContext::id();

        return [
            'affiliate_partner_id' => [
                'required',
                'uuid',
                Rule::exists('affiliate_partners', 'id')->where('tenant_id', $tenantId),
            ],
            'campaign_asset_id' => [
                'required',
                'uuid',
                Rule::exists('campaign_assets', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['sometimes', Rule::in([
                AffiliateLink::STATUS_ACTIVE,
                AffiliateLink::STATUS_PAUSED,
                AffiliateLink::STATUS_REVOKED,
            ])],
        ];
    }
}
