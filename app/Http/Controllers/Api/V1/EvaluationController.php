<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EvaluationResource;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;

/**
 * External REST API v1 — Evaluation endpoints.
 *
 * Used by Zapier, CRM integrations, and clinic backend servers.
 * Auth: Bearer token (Sanctum) + X-Clinic-ID header.
 * Tenant scoping is handled by TenantMiddleware (already runs before this).
 */
class EvaluationController extends Controller
{
    /**
     * GET /api/v1/evaluations/{evaluation_token}
     *
     * Fetch a single evaluation by its secure token.
     * Called by Zapier after receiving the evaluation.completed webhook to
     * enrich the payload with patient PHI and full AI analysis data.
     *
     * PHI (name, email, phone) is returned only when the token has phi:read scope.
     * The scope check is enforced by the route middleware; this controller
     * always loads the patient relationship and lets the resource gate the fields.
     */
    public function show(string $evaluationToken): JsonResponse
    {
        $evaluation = Evaluation::with(['patient', 'photos'])
            ->where('secure_token', $evaluationToken)
            ->firstOrFail();

        return (new EvaluationResource($evaluation))
            ->response()
            ->setStatusCode(200);
    }
}
