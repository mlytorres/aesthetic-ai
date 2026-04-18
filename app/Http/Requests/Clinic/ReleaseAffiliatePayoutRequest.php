<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseAffiliatePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
