<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliatePayoutLedger extends Model
{
    use HasFactory, HasTenantScope, HasUuids, SoftDeletes;

    public const STATUS_PENDING_HOLD = 'pending_hold';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'tenant_id',
        'affiliate_partner_id',
        'affiliate_campaign_id',
        'affiliate_link_id',
        'attribution_event_id',
        'evaluation_id',
        'reviewed_by_user_id',
        'status',
        'amount_cents',
        'currency',
        'hold_until',
        'released_at',
        'rejection_reason',
        'metadata',
        'fraud_review_required',
        'fraud_reviewed_at',
        'fraud_reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'hold_until' => 'datetime',
            'released_at' => 'datetime',
            'fraud_reviewed_at' => 'datetime',
            'fraud_review_required' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function isFraudReviewPending(): bool
    {
        return $this->fraud_review_required && $this->fraud_reviewed_at === null;
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

    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function attributionEvent(): BelongsTo
    {
        return $this->belongsTo(AttributionEvent::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function fraudReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fraud_reviewed_by_user_id');
    }
}
