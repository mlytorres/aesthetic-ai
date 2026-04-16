<?php

declare(strict_types=1);

namespace App\Http\Requests\Intake;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient.name'      => ['required', 'string', 'max:255'],
            'patient.email'     => ['required', 'email', 'max:255'],
            'patient.phone'     => ['nullable', 'string', 'max:30'],
            'turnstile_token'   => ['required', 'string'],
        ];
    }
}
