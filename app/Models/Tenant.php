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
        'baa_signed_at',
        'baa_document_path',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'webhook_secret' => 'encrypted',  // stored encrypted at rest
            'trial_ends_at' => 'datetime',
            'baa_signed_at' => 'datetime',
            'baa_document_path' => 'encrypted',
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
     * Whether a Business Associate Agreement execution date has been recorded for this clinic.
     */
    public function hasExecutedBaa(): bool
    {
        return $this->baa_signed_at !== null;
    }

    // ─── Billing helpers ──────────────────────────────────────────────────────

    /**
     * Whether the tenant has active access to the platform.
     *
     * Access is granted when any of the following is true:
     *   (a) Tenant is on the FREE plan (admin-assigned — no Stripe required).
     *   (b) Tenant is within their 14-day trial window.
     *   (c) Tenant has an active Stripe subscription.
     */
    public function hasBillingAccess(): bool
    {
        // FREE plan — admin-assigned, no Stripe required.
        if ($this->plan?->slug === 'free') {
            return true;
        }

        // Active trial (checks trial_ends_at column, no stripe_id required).
        if ($this->onTrial()) {
            return true;
        }

        // No Stripe customer yet → cannot have an active subscription.
        if ($this->stripe_id === null) {
            return false;
        }

        // Active Stripe subscription.
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

    /**
     * Whether video consultations are enabled for this tenant.
     * True when explicitly toggled on in settings, OR the tenant is on the Pro plan.
     */
    public function hasVideoConsultations(): bool
    {
        if ($this->plan?->slug === 'pro') {
            return true;
        }

        return (bool) ($this->settings['video_consultations_enabled'] ?? false);
    }

    /**
     * Whether the affiliate program is enabled for this tenant.
     * True when explicitly toggled on in settings, OR the tenant is on the Pro plan.
     */
    public function hasAffiliateProgram(): bool
    {
        if ($this->plan?->slug === 'pro') {
            return true;
        }

        return (bool) ($this->settings['affiliate_program_enabled'] ?? false);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function affiliatePartners(): HasMany
    {
        return $this->hasMany(AffiliatePartner::class);
    }

    public function affiliateCampaigns(): HasMany
    {
        return $this->hasMany(AffiliateCampaign::class);
    }

    public function affiliateLinks(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function affiliatePayoutLedgers(): HasMany
    {
        return $this->hasMany(AffiliatePayoutLedger::class);
    }
}
