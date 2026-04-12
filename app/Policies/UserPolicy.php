<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for team management actions.
 *
 * Role matrix:
 * ┌──────────────────────────┬───────┬───────┬─────────────┬─────────┬────────┐
 * │ Action                   │ Owner │ Admin │ Coordinator │ Surgeon │ Viewer │
 * ├──────────────────────────┼───────┼───────┼─────────────┼─────────┼────────┤
 * │ invite (any role)        │  ✅   │  ✅   │     ❌      │   ❌    │  ❌    │
 * │ invite (owner role)      │  ✅   │  ❌   │     ❌      │   ❌    │  ❌    │
 * │ remove team member       │  ✅   │  ✅   │     ❌      │   ❌    │  ❌    │
 * │ remove owner             │  ✅   │  ❌   │     ❌      │   ❌    │  ❌    │
 * └──────────────────────────┴───────┴───────┴─────────────┴─────────┴────────┘
 */
class UserPolicy
{
    /**
     * Owner and Admin can invite team members.
     * The specific role being assigned is further checked in assignRole().
     */
    public function invite(User $actor): bool
    {
        return $actor->canManageClinic();
    }

    /**
     * Only the Owner can assign the Owner role to another user.
     * Admins can invite any role except Owner.
     */
    public function assignRole(User $actor, string $targetRole): bool
    {
        if ($targetRole === User::ROLE_OWNER) {
            return $actor->isOwner();
        }

        return $actor->canManageClinic();
    }

    /**
     * Owner and Admin can remove team members.
     * Only the Owner can remove another Owner.
     */
    public function remove(User $actor, User $target): bool
    {
        // Can't remove yourself
        if ($actor->id === $target->id) {
            return false;
        }

        // Must belong to same tenant
        if ($actor->tenant_id !== $target->tenant_id) {
            return false;
        }

        // Removing an owner requires owner-level access
        if ($target->isOwner()) {
            return $actor->isOwner();
        }

        return $actor->canManageClinic();
    }
}
