<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use App\Models\AffiliateCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('affiliate_campaigns', 'slug')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', Rule::in([
                AffiliateCampaign::STATUS_DRAFT,
                AffiliateCampaign::STATUS_ACTIVE,
                AffiliateCampaign::STATUS_PAUSED,
                AffiliateCampaign::STATUS_ARCHIVED,
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_payout_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['required', 'string', 'size:3'],
            'monthly_cap_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'hold_days' => ['required', 'integer', 'min:1', 'max:60'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
