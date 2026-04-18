<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributionEvent extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const TYPE_CLICK = 'click';

    public const TYPE_INTAKE_STARTED = 'intake_started';

    public const TYPE_EVALUATION_COMPLETED = 'evaluation_completed';

    protected $fillable = [
        'tenant_id',
        'affiliate_link_id',
        'affiliate_partner_id',
        'affiliate_campaign_id',
        'evaluation_id',
        'event_type',
        'idempotency_key',
        'ip_hash',
        'user_agent_hash',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class, 'affiliate_partner_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'affiliate_campaign_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
