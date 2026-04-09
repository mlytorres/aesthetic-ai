<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HIPAA-compliant file storage service for patient photos.
 *
 * All patient photos are stored in S3 with KMS server-side encryption.
 * S3 keys are encrypted at the application layer before being stored in the DB.
 * Access is via pre-signed URLs with a 15-minute expiry — never direct S3 paths.
 *
 * S3 key format: {tenant_id}/{patient_id}/{evaluation_id}/{type}_{timestamp}.jpg
 *
 * Local development: uses the 'local' disk when FEATURE_AI_VISION=false.
 * Production: uses the 's3' disk with KMS encryption.
 */
class SecureFileService
{
    private const SIGNED_URL_EXPIRY_MINUTES = 15;

    /**
     * Upload a patient photo to S3 and return the (plaintext) S3 key.
     * The caller is responsible for encrypting the key before storing in the DB.
     *
     * @param UploadedFile $file        The uploaded photo
     * @param string $patientId         UUID of the patient
     * @param string $evaluationId      UUID of the evaluation
     * @param string $type              front|left_profile|right_profile|additional
     * @return string                   The plaintext S3 key (store encrypted)
     */
    public function upload(
        UploadedFile $file,
        string $patientId,
        string $evaluationId,
        string $type,
    ): string {
        $tenantId  = TenantContext::id();
        $timestamp = now()->format('YmdHis');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $key = "{$tenantId}/{$patientId}/{$evaluationId}/{$type}_{$timestamp}.{$extension}";

        $disk = $this->disk();

        $disk->putFileAs(
            dirname($key),
            $file,
            basename($key),
            $this->storageOptions(),
        );

        return $key;
    }

    /**
     * Generate a signed URL for a photo. Expires in 15 minutes.
     * Never return raw S3 keys or public URLs.
     *
     * @param string $s3Key  Plaintext S3 key (already decrypted from DB)
     * @return string        Time-limited signed URL
     */
    public function getSignedUrl(string $s3Key): string
    {
        $disk = $this->disk();

        // Local disk (dev) does not support temporaryUrl — fall back to a plain URL.
        if (config('features.ai_vision', false) === false) {
            return $disk->url($s3Key);
        }

        return $disk->temporaryUrl(
            $s3Key,
            now()->addMinutes(self::SIGNED_URL_EXPIRY_MINUTES),
        );
    }

    /**
     * Delete a photo from S3. Called on soft-delete of a Photo model.
     */
    public function delete(string $s3Key): void
    {
        $this->disk()->delete($s3Key);
    }

    /**
     * Compute an HMAC hash for the S3 key — used for integrity verification.
     */
    public function hashKey(string $s3Key): string
    {
        return hash_hmac('sha256', $s3Key, config('app.key'));
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        // In local dev (FEATURE_AI_VISION=false) use local disk to avoid S3 costs
        $diskName = config('features.ai_vision', false) ? 's3' : 'local';

        return Storage::disk($diskName);
    }

    /**
     * S3 storage options — SSE-KMS encryption enforced in production.
     *
     * @return array<string, mixed>
     */
    private function storageOptions(): array
    {
        if (app()->environment('production')) {
            return [
                'ServerSideEncryption' => 'aws:kms',
                'ContentType'          => 'image/jpeg',
                'ACL'                  => 'private',
            ];
        }

        return [];
    }
}
