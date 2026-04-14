<?php

declare(strict_types=1);

namespace App\Http\Requests\Intake;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo'            => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'], // 10MB
            'type'             => ['required', 'string', Rule::in([
                'front', 'left_profile', 'right_profile',
                'back', 'left_side', 'right_side',
                'abdomen_front', 'abdomen_side',
                'chest_front', 'eyes_closed',
                'arm_front',
                'additional', // legacy — kept for backward-compatibility
            ])],
            'quality_score'    => ['required', 'integer', 'min:0', 'max:100'],
            'capture_metadata' => ['nullable', 'array'],
        ];
    }
}
