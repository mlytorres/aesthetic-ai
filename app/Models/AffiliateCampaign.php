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

class AffiliateCampaign extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'description',
        'starts_at',
        'ends_at',
        'default_payout_cents',
        'currency',
        'monthly_cap_cents',
        'hold_days',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'settings' => 'array',
            'default_payout_cents' => 'integer',
            'monthly_cap_cents' => 'integer',
            'hold_days' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CampaignAsset::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }
}
