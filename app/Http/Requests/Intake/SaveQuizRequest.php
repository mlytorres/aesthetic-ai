<?php

declare(strict_types=1);

namespace App\Http\Requests\Intake;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class SaveQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers'   => ['required', 'array'],
            'answers.*' => ['nullable'],
        ];
    }
}
