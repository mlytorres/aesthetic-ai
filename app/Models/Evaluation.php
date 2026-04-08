<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    // Status constants — use these everywhere, never raw strings
    public const STATUS_DRAFT       = 'draft';
    public const STATUS_SUBMITTED   = 'submitted';
    public const STATUS_ANALYZING   = 'analyzing';
    public const STATUS_COMPLETE    = 'complete';
    public const STATUS_CONTACTED   = 'contacted';
    public const STATUS_BOOKED      = 'booked';
    public const STATUS_NO_SHOW     = 'no_show';
    public const STATUS_NOT_A_FIT   = 'not_a_fit';
    public const STATUS_FAILED      = 'failed';

    // Priority constants
    public const PRIORITY_URGENT   = 'urgent';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_MEDIUM   = 'medium';
    public const PRIORITY_STANDARD = 'standard';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'procedure_slug',
        'status',
        'quiz_answers',
        'analysis_data',
        'lead_score',
        'priority',
        'secure_token',
        'coordinator_notes',
        'follow_up_at',
        'external_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quiz_answers'  => 'array',
            'analysis_data' => 'array',
            'lead_score'    => 'integer',
            'follow_up_at'  => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    // ─── Boot ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Evaluation $evaluation): void {
            if (empty($evaluation->secure_token)) {
                $evaluation->secure_token = hash('sha256', \Illuminate\Support\Str::random(64));
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_slug', 'slug');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLogEntry::class, 'subject_id')
            ->where('subject_type', 'Evaluation');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isAiComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE
            && $this->lead_score !== null;
    }

    public function isReadyForCall(): bool
    {
        return $this->isAiComplete()
            && in_array($this->priority, [self::PRIORITY_URGENT, self::PRIORITY_HIGH]);
    }
}
