<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One-time coordinator portal access token.
 * Raw token never stored — only SHA-256 hash.
 * Expires after 15 minutes and can only be used once.
 */
class MagicLink extends Model
{
    use HasTenantScope, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'evaluation_id',
        'token_hash',
        'used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at'    => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (MagicLink $link) => $link->created_at = now());
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

    // ─── Static factory helpers ───────────────────────────────────────────────

    /**
     * Generate a new magic link for an evaluation.
     * Returns [MagicLink $model, string $rawToken].
     * The raw token is shown ONCE — it is not stored.
     *
     * @return array{0: MagicLink, 1: string}
     */
    public static function generate(Evaluation $evaluation): array
    {
        $rawToken = Str::random(64);
        $hash     = hash('sha256', $rawToken);

        $link = static::create([
            'tenant_id'     => $evaluation->tenant_id,
            'evaluation_id' => $evaluation->id,
            'token_hash'    => $hash,
            'expires_at'    => now()->addMinutes(15),
        ]);

        return [$link, $rawToken];
    }

    /**
     * Validate a raw token. Returns the MagicLink if valid, null if expired/used/not found.
     */
    public static function validate(string $rawToken): ?self
    {
        $hash = hash('sha256', $rawToken);

        return static::where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
