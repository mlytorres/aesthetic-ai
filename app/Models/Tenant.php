<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'plan_id',
        'webhook_url',
        'webhook_secret',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings'       => 'array',
            'webhook_secret' => 'encrypted',  // stored encrypted at rest
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Returns the list of procedure slugs enabled for this tenant.
     * Falls back to the MVP default if not configured.
     *
     * @return array<string>
     */
    public function enabledProcedures(): array
    {
        return $this->settings['procedures_enabled'] ?? ['rhinoplasty'];
    }
}
