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
 * Auth: Bearer token or matching `X-Api-Key` (same raw `aai_live_…` value) + `X-Clinic-ID` header (`AuthenticateApiToken`).
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
        if (str_starts_with($evaluationToken, 'test_')) {
            $tenant = \App\Facades\TenantContext::get();

            return response()->json([
                'data' => [
                    'evaluation_token' => $evaluationToken,
                    'clinic' => $tenant?->name ?? 'Miami Life Cosmetic Center',
                    'procedure_interest' => 'rhinoplasty',
                    'lead_score' => 87,
                    'priority' => 'high',
                    'ready_for_call' => true,
                    'timeline' => 'within_3_months',
                    'budget_range' => '15000_25000',
                    'photos_available' => true,
                    'ai_analysis_complete' => true,
                    'message' => 'This is a test webhook from SymetriHealth. Your endpoint is correctly configured.',
                    'patient' => [
                        'name' => 'Jane Doe (Test)',
                        'email' => 'jane.test@example.com',
                        'phone' => '+15550198765',
                    ],
                    'quiz_summary' => [
                        'timeline' => 'within_3_months',
                        'budget_range' => '15000_25000',
                    ],
                    'ai_analysis' => [
                        'clinical_summary' => 'This is a test payload for webhook integration.',
                        'contraindications' => [],
                        'recommended_procedures' => ['rhinoplasty'],
                    ],
                    'photos' => [],
                ]
            ]);
        }

        $evaluation = Evaluation::with(['patient', 'photos'])
            ->where('secure_token', $evaluationToken)
            ->firstOrFail();

        return (new EvaluationResource($evaluation))
            ->response()
            ->setStatusCode(200);
    }
}
