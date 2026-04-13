<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bearer token for the External REST API v1.
 *
 * Raw tokens are shown once at creation and never stored — only the
 * SHA-256 hash is persisted. To verify an incoming Bearer token, hash
 * the raw value and compare to token_hash.
 *
 * Format: aai_live_{40 random chars}
 */
class ApiToken extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'token_hash',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Generate a new raw token and return both the raw value (shown once)
     * and the hashed value for DB storage.
     *
     * @return array{raw: string, hash: string}
     */
    public static function generateRaw(): array
    {
        $raw = 'aai_live_'.Str::random(40);

        return [
            'raw' => $raw,
            'hash' => hash('sha256', $raw),
        ];
    }

    /**
     * Look up a token by its raw Bearer value.
     */
    public static function findByRaw(string $rawToken): ?self
    {
        $hash = hash('sha256', $rawToken);

        return static::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array($scope, $scopes, true);
    }

    public function touchLastUsed(): void
    {
        $this->updateQuietly(['last_used_at' => now()]);
    }
}
