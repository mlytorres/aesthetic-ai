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
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.tenant_id', TenantContext::id());
    }
}
