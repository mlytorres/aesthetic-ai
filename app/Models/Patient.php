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
use Illuminate\Support\Facades\Hash;

/**
 * Patient identity record.
 *
 * HIPAA: All PII columns are encrypted at the application layer using Laravel's
 * built-in 'encrypted' cast (AES-256-GCM via the app key).
 * Raw values are never stored. Hashed columns (email_hash) use HMAC-SHA256
 * for deduplication without requiring decryption.
 */
class Patient extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'dob_encrypted',
        'name_hash',
        'email_hash',
        'external_crm_id',
        'created_via',
    ];

    protected function casts(): array
    {
        return [
            // 🔒 PHI: Laravel 'encrypted' cast uses AES-256-GCM
            'name_encrypted'  => 'encrypted',
            'email_encrypted' => 'encrypted',
            'phone_encrypted' => 'encrypted',
            'dob_encrypted'   => 'encrypted',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Hash an email for deduplication lookup without storing plaintext.
     */
    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), config('app.key'));
    }

    /**
     * Find an existing patient by email within the current tenant.
     * Uses hash comparison — never decrypts all records.
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where('email_hash', static::hashEmail($email))->first();
    }
}
