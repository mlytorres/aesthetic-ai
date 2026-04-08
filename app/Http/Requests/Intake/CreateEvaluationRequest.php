<?php

declare(strict_types=1);

namespace App\Http\Requests\Intake;

use App\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $enabledProcedures = TenantContext::get()->enabledProcedures();

        return [
            'procedure_slug' => ['required', 'string', Rule::in($enabledProcedures)],
        ];
    }
}
