<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HIPAA audit log entry.
 * Append-only: no soft deletes, no updated_at, never modified after creation.
 */
class AuditLogEntry extends Model
{
    use HasTenantScope;

    public $timestamps = false;           // manages created_at manually (no updated_at)

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLogEntry $entry): void {
            $entry->created_at = now();
        });

        // Hard-block updates and deletes — this table is immutable
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
