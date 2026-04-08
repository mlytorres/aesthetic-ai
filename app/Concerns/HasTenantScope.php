<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Facades\TenantContext;
use App\Scopes\TenantScope;

/**
 * Apply to every Eloquent model that is owned by a tenant.
 *
 * This trait does two things:
 *  1. Registers TenantScope as a global scope → all queries are automatically
 *     filtered to the current tenant (SELECT ... WHERE tenant_id = ?)
 *  2. Auto-fills tenant_id on model creation → no controller needs to set it
 *
 * Usage:
 *   class Patient extends Model
 *   {
 *       use HasTenantScope;
 *   }
 *
 * NEVER manually add ->where('tenant_id', ...) in controllers.
 * NEVER use withoutGlobalScope(TenantScope::class) outside admin contexts.
 */
trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (self $model): void {
            // Only auto-fill from context when the context is resolved (i.e. inside an HTTP
            // request that passed through TenantMiddleware). During CLI commands, seeders,
            // and tests, tenant_id must be set explicitly on the model — the guard below
            // leaves explicitly-provided values untouched regardless of context state.
            if (empty($model->tenant_id) && TenantContext::isSet()) {
                $model->tenant_id = TenantContext::id();
            }
        });
    }
}
