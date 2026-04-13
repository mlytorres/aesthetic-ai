<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize clinic staff to subscribe to their tenant's private notification channel.
 *
 * Only users who belong to the tenant (tenant_id matches) may subscribe.
 * The channel is used for real-time evaluation notifications via Reverb.
 */
Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId): bool {
    return $user->tenant_id === $tenantId;
});
