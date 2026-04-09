<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Facades\TenantContext;
use App\Models\Evaluation;
use App\Models\Tenant;
use RuntimeException;

/**
 * Trait for queue jobs that operate on tenant-owned data.
 *
 * Queue workers run outside of the HTTP request lifecycle, so TenantMiddleware
 * never fires. Any Eloquent model with HasTenantScope will have its TenantScope
 * call TenantContext::id() — which throws RuntimeException if the context is empty.
 *
 * Solution: call $this->setTenantFromEvaluation($evaluationId) at the very start
 * of every job's handle() method. This resolves the tenant directly from the DB
 * (bypassing the scope) and sets TenantContext so all subsequent queries work normally.
 *
 * Usage:
 *   class MyJob implements ShouldQueue
 *   {
 *       use ResolvesJobTenant;
 *
 *       public function handle(): void
 *       {
 *           $this->setTenantFromEvaluation($this->evaluationId);
 *           // Now TenantContext is set — all scoped queries work
 *           $evaluation = Evaluation::with('patient')->findOrFail($this->evaluationId);
 *       }
 *   }
 */
trait ResolvesJobTenant
{
    /**
     * Resolve and set TenantContext from an evaluation's tenant_id.
     * Uses withoutGlobalScopes() to bypass the scope (context isn't set yet).
     *
     * @throws RuntimeException if tenant cannot be found
     */
    protected function setTenantFromEvaluation(string $evaluationId): void
    {
        $tenantId = Evaluation::withoutGlobalScopes()
            ->where('id', $evaluationId)
            ->value('tenant_id');

        if (!$tenantId) {
            throw new RuntimeException(
                "ResolvesJobTenant: evaluation [{$evaluationId}] not found or has no tenant_id."
            );
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new RuntimeException(
                "ResolvesJobTenant: tenant [{$tenantId}] not found for evaluation [{$evaluationId}]."
            );
        }

        TenantContext::set($tenant);
    }
}
