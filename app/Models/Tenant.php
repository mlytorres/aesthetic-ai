<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'plan_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'webhook_url',
        'webhook_secret',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
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

    // ─── Billing helpers ──────────────────────────────────────────────────────

    /**
     * Whether the tenant has an active Stripe subscription or a valid trial.
     *
     * Super-admin-created tenants with no stripe_id bypass billing (internal use).
     */
    public function hasBillingAccess(): bool
    {
        // No Stripe customer yet → grace period (super admin just created them).
        if ($this->stripe_id === null) {
            return true;
        }

        // Trial not yet expired.
        if ($this->onTrial()) {
            return true;
        }

        return $this->subscribed('default');
    }

    /**
     * Whether the tenant is within their monthly evaluation limit.
     * Returns true when the plan has no limit (Pro — unlimited).
     */
    public function withinEvalLimit(): bool
    {
        $limit = $this->plan?->max_evaluations_mo;

        if ($limit === null) {
            return true; // unlimited
        }

        $count = $this->evaluations()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return $count < $limit;
    }

    /**
     * Whether the given procedure slug is allowed under the tenant's plan.
     * Checks both the plan's max_procedures limit and the enabled list in settings.
     */
    public function canUseProcedure(string $procedureSlug): bool
    {
        return in_array($procedureSlug, $this->enabledProcedures(), true);
    }

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

    /**
     * Monthly evaluation count for the current billing period.
     */
    public function currentMonthEvalCount(): int
    {
        return $this->evaluations()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }
}
