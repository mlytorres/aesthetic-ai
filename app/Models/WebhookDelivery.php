<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory, HasTenantScope, HasUuids;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RETRYING  = 'retrying';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'tenant_id',
        'evaluation_id',
        'event',
        'payload',
        'status',
        'attempt_count',
        'next_retry_at',
        'last_response',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'       => 'array',
            'last_response' => 'array',
            'next_retry_at' => 'datetime',
            'delivered_at'  => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
