<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use App\Models\AffiliatePartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId  = TenantContext::id();
        $partnerId = $this->route('affiliatePartner');

        return [
            'name'               => ['required', 'string', 'max:255'],
            'email'              => [
                'required',
                'email',
                'max:255',
                Rule::unique('affiliate_partners', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($partnerId),
            ],
            'platform'           => ['required', Rule::in([
                AffiliatePartner::PLATFORM_INSTAGRAM,
                AffiliatePartner::PLATFORM_TIKTOK,
                AffiliatePartner::PLATFORM_YOUTUBE,
                AffiliatePartner::PLATFORM_OTHER,
            ])],
            'handle'             => ['required', 'string', 'max:120'],
            'status'             => ['required', Rule::in([
                AffiliatePartner::STATUS_ACTIVE,
                AffiliatePartner::STATUS_PAUSED,
                AffiliatePartner::STATUS_BLOCKED,
            ])],
            'payout_cents'       => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency'           => ['required', 'string', 'size:3'],
            'monthly_cap_cents'  => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'hold_days'          => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
