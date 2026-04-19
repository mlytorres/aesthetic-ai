<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Middleware\TenantMiddleware;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Holds the currently resolved tenant for the duration of a request.
 * Bound as a singleton in AppServiceProvider.
 *
 * Resolution sources (in order of priority):
 *  1. Subdomain  → {slug}.aesthetic-ai.test
 *  2. Embed JWT  → widget token contains tenant_id claim
 *  3. Magic link → evaluation token resolves to tenant
 *  4. API Bearer → X-Clinic-ID header validated against token
 *
 * @see TenantMiddleware
 */
class TenantContext
{
    private ?Tenant $current = null;

    public function set(Tenant $tenant): void
    {
        $this->current = $tenant;

        // Propagate to PostgreSQL session so RLS policies can read the current tenant
        // (see migration 2026_04_18_060000_add_rls_policies_to_affiliate_tables).
        $this->syncToDatabase($tenant->id);
    }

    /**
     * Run code with cross-tenant database visibility (bypasses RLS policies on affiliate tables).
     * Use sparingly — only for super-admin audit screens and platform-level aggregates.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withAllTenants(callable $callback): mixed
    {
        $previous = $this->current;
        $previousGuc = $this->currentDatabaseTenantId();

        $this->syncToDatabase('all');

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                $this->syncToDatabase($previous->id);
            } else {
                $this->syncToDatabase($previousGuc);
            }
        }
    }

    /**
     * @throws RuntimeException if no tenant has been resolved for this request
     */
    public function get(): Tenant
    {
        return $this->current
            ?? throw new RuntimeException(
                'No tenant resolved for this request. '.
                'Ensure TenantMiddleware is applied to this route.'
            );
    }

    public function id(): string
    {
        return $this->get()->id;
    }

    public function isSet(): bool
    {
        return $this->current !== null;
    }

    public function clear(): void
    {
        $this->current = null;

        $this->syncToDatabase(null);
    }

    /**
     * Sets the `app.current_tenant_id` GUC on the default DB connection so Postgres RLS
     * policies can match rows against it. No-op on non-pgsql drivers (sqlite/mysql in tests).
     */
    private function syncToDatabase(?string $value): void
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                return;
            }

            if ($value === null) {
                // Reset to default; RLS policy falls back to empty result set.
                $connection->statement("SELECT set_config('app.current_tenant_id', '', true)");

                return;
            }

            // Bind parameter so uuids and the 'all' sentinel are safely quoted.
            $connection->statement("SELECT set_config('app.current_tenant_id', ?, true)", [$value]);
        } catch (Throwable) {
            // DB may not be connected yet in some unit tests — fail-open is acceptable
            // because RLS is a safety net, not the primary isolation layer.
        }
    }

    private function currentDatabaseTenantId(): ?string
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                return null;
            }

            $value = $connection->selectOne("SELECT current_setting('app.current_tenant_id', true) AS v")?->v;

            return $value === '' ? null : $value;
        } catch (Throwable) {
            return null;
        }
    }
}
