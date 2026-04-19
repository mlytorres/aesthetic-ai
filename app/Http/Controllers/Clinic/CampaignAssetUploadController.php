<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCampaign;
use App\Models\CampaignAsset;
use App\Services\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignAssetUploadController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    /**
     * Return a pre-signed S3 PUT URL so the browser can upload directly.
     * No file data passes through Laravel — just the signed URL.
     */
    public function presign(Request $request, AffiliateCampaign $affiliateCampaign): JsonResponse
    {
        abort_unless($affiliateCampaign->tenant_id === TenantContext::id(), 404);

        $request->validate([
            'filename'  => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/jpg,image/png,image/gif,image/webp'],
        ]);

        $tenantId  = TenantContext::id();
        $extension = pathinfo($request->input('filename'), PATHINFO_EXTENSION) ?: 'jpg';
        $key       = "affiliates/{$tenantId}/assets/{$affiliateCampaign->id}/".Str::uuid().".{$extension}";

        // In local dev, generate a fake upload URL and return metadata only.
        if (! app()->environment('production')) {
            return response()->json([
                'upload_url' => null,
                'key'        => $key,
                'dev_mode'   => true,
            ]);
        }

        $s3 = Storage::disk('s3');

        /** @var \Aws\S3\S3Client $client */
        $client  = $s3->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket'               => config('filesystems.disks.s3.bucket'),
            'Key'                  => $key,
            'ContentType'          => $request->input('mime_type'),
            'ServerSideEncryption' => 'aws:kms',
            'ACL'                  => 'private',
        ]);

        $presignedRequest = $client->createPresignedRequest($command, '+10 minutes');

        return response()->json([
            'upload_url' => (string) $presignedRequest->getUri(),
            'key'        => $key,
        ]);
    }

    /**
     * Confirm a completed upload — creates the CampaignAsset record.
     */
    public function confirm(Request $request, AffiliateCampaign $affiliateCampaign): JsonResponse
    {
        abort_unless($affiliateCampaign->tenant_id === TenantContext::id(), 404);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'key'               => ['required', 'string', 'max:1024'],
            'mime_type'         => ['required', 'string'],
            'asset_type'        => ['required', 'in:image,video,caption'],
            'compliance_notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = CampaignAsset::create([
            'tenant_id'            => $affiliateCampaign->tenant_id,
            'affiliate_campaign_id' => $affiliateCampaign->id,
            'name'                 => $validated['name'],
            'asset_type'           => $validated['asset_type'],
            'storage_path'         => $validated['key'],
            'status'               => CampaignAsset::STATUS_PENDING,
            'compliance_notes'     => $validated['compliance_notes'] ?? null,
            'approved_by_user_id'  => null,
            'approved_at'          => null,
        ]);

        $this->auditLog->record('affiliate.asset.uploaded', $affiliateCampaign, [
            'asset_id'   => $asset->id,
            'asset_type' => $asset->asset_type,
        ]);

        return response()->json([
            'asset' => [
                'id'         => $asset->id,
                'name'       => $asset->name,
                'asset_type' => $asset->asset_type,
                'status'     => $asset->status,
            ],
        ], 201);
    }
}
