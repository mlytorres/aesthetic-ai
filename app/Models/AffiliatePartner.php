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
use Illuminate\Support\Str;

class AffiliatePartner extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_BLOCKED = 'blocked';

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_TIKTOK = 'tiktok';

    public const PLATFORM_YOUTUBE = 'youtube';

    public const PLATFORM_OTHER = 'other';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'platform',
        'handle',
        'status',
        'portal_access_token',
        'payout_cents',
        'currency',
        'monthly_cap_cents',
        'hold_days',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'payout_cents' => 'integer',
            'monthly_cap_cents' => 'integer',
            'hold_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliatePartner $partner): void {
            if (empty($partner->portal_access_token)) {
                $partner->portal_access_token = Str::random(48);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function termsAcceptances(): HasMany
    {
        return $this->hasMany(AffiliateTermsAcceptance::class);
    }

    public function payoutLedgers(): HasMany
    {
        return $this->hasMany(AffiliatePayoutLedger::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
