<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use RuntimeException;

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
 * @see \App\Http\Middleware\TenantMiddleware
 */
class TenantContext
{
    private ?Tenant $current = null;

    public function set(Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    /**
     * @throws RuntimeException if no tenant has been resolved for this request
     */
    public function get(): Tenant
    {
        return $this->current
            ?? throw new RuntimeException(
                'No tenant resolved for this request. ' .
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
    }
}
