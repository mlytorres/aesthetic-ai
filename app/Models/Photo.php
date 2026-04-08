<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Photo metadata record.
 *
 * HIPAA: The s3_key column is encrypted using Laravel's 'encrypted' cast.
 * Actual photo bytes live only in S3 (KMS encrypted).
 * Never return raw s3_key values in API responses — use SecureFileService::getSignedUrl().
 */
class Photo extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const TYPE_FRONT         = 'front';
    public const TYPE_LEFT_PROFILE  = 'left_profile';
    public const TYPE_RIGHT_PROFILE = 'right_profile';
    public const TYPE_ADDITIONAL    = 'additional';

    public const ANALYSIS_PENDING    = 'pending';
    public const ANALYSIS_PROCESSING = 'processing';
    public const ANALYSIS_COMPLETE   = 'complete';
    public const ANALYSIS_FAILED     = 'failed';
    public const ANALYSIS_SKIPPED    = 'skipped';

    protected $fillable = [
        'tenant_id',
        'evaluation_id',
        'type',
        's3_key',
        's3_key_hash',
        'quality_score',
        'analysis_status',
        'capture_metadata',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            's3_key'           => 'encrypted',  // 🔒 PHI: encrypted S3 path
            'capture_metadata' => 'array',
            'taken_at'         => 'datetime',
            'quality_score'    => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
