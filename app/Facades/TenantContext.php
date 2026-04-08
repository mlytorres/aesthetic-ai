<?php

declare(strict_types=1);

namespace App\Facades;

use App\Models\Tenant;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(Tenant $tenant)
 * @method static Tenant get()
 * @method static string id()
 * @method static bool isSet()
 * @method static void clear()
 *
 * @see \App\Services\TenantContext
 */
class TenantContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\TenantContext::class;
    }
}
