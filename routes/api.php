<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| External REST API v1 — used by clinic CRM integrations and Zapier.
| All routes here require Bearer token auth + X-Clinic-ID header.
| Tenant resolution is handled by TenantMiddleware (via X-Clinic-ID).
|
| Widget / patient intake routes live in routes/web.php under the
| 'tenant' middleware group (resolved via embed token or subdomain).
|
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    // Sprint 1 — placeholder, controllers built in Sprint 3+
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'version' => 'v1']));
});
