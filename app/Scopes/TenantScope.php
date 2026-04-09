<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Facades\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically restricts every query to the current tenant.
 * Applied via the HasTenantScope trait — never add directly in models.
 *
 * HIPAA: This is the first of two isolation layers (app-layer).
 * The second layer is PostgreSQL Row-Level Security.
 *
 * IMPORTANT — SubstituteBindings ordering:
 * Laravel's SubstituteBindings middleware (part of the web group) runs BEFORE
 * route-specific middleware such as 'tenant'. This means if a route uses
 * implicit Eloquent route model binding (e.g. `Evaluation $evaluation`),
 * this scope will fire before TenantContext is set and will throw.
 *
 * Safe fallback: when TenantContext is not set, apply WHERE false so the
 * binding returns a 404 instead of a 500 or a data leak.
 * Controllers that need tenant-scoped models should look them up manually
 * (i.e. use `string $id` + `Evaluation::findOrFail($id)` inside the method
 * body, not in the method signature) so the scope fires after middleware.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Guard: context not set = SubstituteBindings running before tenant
        // middleware. Return no rows (safe) rather than throwing a 500.
        if (! app(\App\Services\TenantContext::class)->isSet()) {
            $builder->whereRaw('false');
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', TenantContext::id());
    }
}
