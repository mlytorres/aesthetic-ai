<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AffiliatePartner;
use App\Models\User;

class AffiliatePartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageClinic();
    }

    public function view(User $user, AffiliatePartner $affiliatePartner): bool
    {
        return $user->canManageClinic()
            && $user->tenant_id === $affiliatePartner->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->canManageClinic();
    }

    public function update(User $user, AffiliatePartner $affiliatePartner): bool
    {
        return $user->canManageClinic()
            && $user->tenant_id === $affiliatePartner->tenant_id;
    }

    public function delete(User $user, AffiliatePartner $affiliatePartner): bool
    {
        return $user->canManageClinic()
            && $user->tenant_id === $affiliatePartner->tenant_id;
    }

    public function restore(User $user, AffiliatePartner $affiliatePartner): bool
    {
        return $this->delete($user, $affiliatePartner);
    }

    public function forceDelete(User $user, AffiliatePartner $affiliatePartner): bool
    {
        return $this->delete($user, $affiliatePartner);
    }
}
