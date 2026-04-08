<?php

declare(strict_types=1);

namespace App\Http\Requests\Intake;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // 🔒 PHI — validated but immediately encrypted by model casts
            'patient.name'  => ['required', 'string', 'max:255'],
            'patient.email' => ['required', 'email', 'max:255'],
            'patient.phone' => ['nullable', 'string', 'max:30'],

            // Consent — required for HIPAA compliance
            'consent.hipaa_acknowledged' => ['required', 'accepted'],
            'consent.terms_accepted'     => ['required', 'accepted'],
            'consent.photo_use_consent'  => ['required', 'accepted'],
            'consent.consented_at'       => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'consent.hipaa_acknowledged.accepted' => 'You must acknowledge the HIPAA notice to continue.',
            'consent.terms_accepted.accepted'      => 'You must accept the terms of service to continue.',
            'consent.photo_use_consent.accepted'   => 'You must consent to photo use for AI analysis to continue.',
        ];
    }
}
