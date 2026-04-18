<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinic;

use App\Facades\TenantContext;
use App\Models\CampaignAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TenantContext::isSet();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asset_type' => ['required', Rule::in([
                CampaignAsset::TYPE_IMAGE,
                CampaignAsset::TYPE_VIDEO,
                CampaignAsset::TYPE_CAPTION,
            ])],
            'storage_path' => ['required', 'string', 'max:2048'],
            'checksum' => ['nullable', 'string', 'max:128'],
            'status' => ['required', Rule::in([
                CampaignAsset::STATUS_PENDING,
                CampaignAsset::STATUS_APPROVED,
                CampaignAsset::STATUS_REJECTED,
                CampaignAsset::STATUS_REVOKED,
            ])],
            'compliance_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
