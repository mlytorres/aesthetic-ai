<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Clinic staff account (coordinator, surgeon, admin, owner) OR platform super-admin.
 *
 * Super-admins have tenant_id = null. They access /admin/* only and can never
 * be created by a tenant — only via Artisan or by another super-admin.
 *
 * NOTE: User intentionally does NOT use HasTenantScope.
 * Laravel's session guard calls User::find($id) on every request before any
 * middleware runs, so a global TenantScope would throw before TenantMiddleware
 * has a chance to set the context. Tenant isolation for staff queries is
 * enforced at the controller/policy layer using explicit where('tenant_id', …)
 * or a local scope — not a global scope on this model.
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role', 'google_id', 'google_token'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'google_id', 'google_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    // Tenant role constants — always use these, never raw strings
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COORDINATOR = 'coordinator';

    public const ROLE_SURGEON = 'surgeon';

    public const ROLE_VIEWER = 'viewer';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'google_token' => 'encrypted',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Platform-level helpers ───────────────────────────────────────────────

    /**
     * Super-admins are platform operators with no clinic affiliation.
     * Detected by tenant_id = null — there is no separate "super_admin" column.
     * Can only be created via Artisan or by another super-admin in /admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null;
    }

    // ─── Tenant role helpers ──────────────────────────────────────────────────

    /** Clinic owner — highest tenant role, sole ability to assign the Owner role. */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Owner OR Admin — can manage clinic settings, team, and integrations.
     * Use this for any check that applies equally to both management roles.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    /** Coordinator — primary clinical role; can update evaluation status and notes. */
    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    /** Surgeon — sees clinical analysis only; cannot view patient contact info or manage clinic. */
    public function isSurgeon(): bool
    {
        return $this->role === self::ROLE_SURGEON;
    }

    /** Viewer — read-only access to evaluations and analytics; cannot take any action. */
    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    /**
     * Clinical actors — can update evaluation state, notes, and request simulations.
     * Owner, Admin, Coordinator. NOT Surgeon or Viewer.
     */
    public function isClinicalActor(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_COORDINATOR]);
    }

    /**
     * Can this user view patient PII (name, email, phone)?
     * Surgeons and Viewers see clinical/aggregate data only — no contact info.
     */
    public function canViewPhi(): bool
    {
        return $this->isClinicalActor();
    }

    /**
     * Can this user manage clinic-level settings, team, and integrations?
     * Owner and Admin only.
     */
    public function canManageClinic(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Whether this account is subject to mandatory 2FA for tenant dashboard access.
     * Super-admins and read-only / surgeon roles are exempt.
     */
    public function requiresMandatoryTwoFactor(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return in_array($this->role, [
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
            self::ROLE_COORDINATOR,
        ], true);
    }
}
