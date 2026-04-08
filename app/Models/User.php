<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Clinic staff account (coordinator, surgeon, admin, owner).
 *
 * NOTE: User intentionally does NOT use HasTenantScope.
 * Laravel's session guard calls User::find($id) on every request before any
 * middleware runs, so a global TenantScope would throw before TenantMiddleware
 * has a chance to set the context. Tenant isolation for staff queries is
 * enforced at the controller/policy layer using explicit where('tenant_id', …)
 * or a local scope — not a global scope on this model.
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    // Role constants — always use these, never raw strings
    public const ROLE_OWNER       = 'owner';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_SURGEON     = 'surgeon';
    public const ROLE_VIEWER      = 'viewer';

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isOwner(): bool      { return $this->role === self::ROLE_OWNER; }
    public function isAdmin(): bool      { return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN]); }
    public function isCoordinator(): bool { return $this->role === self::ROLE_COORDINATOR; }
    public function isSurgeon(): bool    { return $this->role === self::ROLE_SURGEON; }

    /**
     * Can this user view patient PII (name, email, phone)?
     * Surgeons see clinical data only — no contact info.
     */
    public function canViewPhi(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_COORDINATOR]);
    }
}
