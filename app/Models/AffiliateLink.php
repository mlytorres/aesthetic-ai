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

class AffiliateLink extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'affiliate_partner_id',
        'affiliate_campaign_id',
        'campaign_asset_id',
        'token',
        'short_code',
        'status',
        'click_count',
        'last_clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_clicked_at' => 'datetime',
            'click_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateLink $link): void {
            if (empty($link->token)) {
                $link->token = Str::random(40);
            }

            if (empty($link->short_code)) {
                $link->short_code = Str::random(8);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class, 'affiliate_partner_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'affiliate_campaign_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CampaignAsset::class, 'campaign_asset_id');
    }

    public function attributionEvents(): HasMany
    {
        return $this->hasMany(AttributionEvent::class);
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
