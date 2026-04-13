<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| External REST API v1 — used by clinic CRM integrations and Zapier.
|
| Auth: Bearer token (aai_live_xxx) via AuthenticateApiToken middleware.
| Tenant resolution is handled inside the middleware from X-Clinic-ID header
| or falls back to the token's own tenant_id.
|
| Session-authenticated users (clinic dashboard) are also accepted —
| AuthenticateApiToken passes through if a session user is already set.
|
| Widget / patient intake routes live in routes/web.php.
|
*/

Route::prefix('v1')->middleware([AuthenticateApiToken::class])->group(function (): void {
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'version' => 'v1']));

    // Evaluations — called by Zapier after receiving evaluation.completed webhook.
    Route::get('/evaluations/{evaluation_token}', [EvaluationController::class, 'show'])
        ->name('api.v1.evaluations.show');
});
