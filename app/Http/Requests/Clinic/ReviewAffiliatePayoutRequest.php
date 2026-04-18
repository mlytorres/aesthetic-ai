<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use App\Models\AffiliatePayoutLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAffiliatePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    AffiliatePayoutLedger::STATUS_APPROVED,
                    AffiliatePayoutLedger::STATUS_REJECTED,
                ]),
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('status') === AffiliatePayoutLedger::STATUS_REJECTED),
            ],
        ];
    }
}
