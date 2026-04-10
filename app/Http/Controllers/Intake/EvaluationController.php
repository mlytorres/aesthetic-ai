<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\CreateEvaluationRequest;
use App\Http\Requests\Intake\SaveQuizRequest;
use App\Http\Requests\Intake\SubmitEvaluationRequest;
use App\Jobs\AI\CalculateProportionsJob;
use App\Jobs\AI\ExtractFacialLandmarksJob;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Jobs\AI\ValidatePhotoQualityJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Services\AuditLog;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles the patient evaluation lifecycle during intake.
 * Returns JSON (not Inertia) — these are called by the wizard's React state machine.
 */
class EvaluationController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    /**
     * Step 1 — create a draft evaluation when the patient selects a procedure.
     * Returns the evaluation_token which gates all subsequent calls.
     */
    public function store(CreateEvaluationRequest $request): JsonResponse
    {
        // Create a stub patient record (no PHI yet — just a placeholder)
        // PHI is collected in the submit step only.
        $patient = Patient::create([
            'email_encrypted' => 'pending_' . Str::uuid(), // placeholder until submit
            'email_hash'      => hash('sha256', 'pending_' . Str::uuid()),
            'created_via'     => 'widget',
        ]);

        $evaluation = Evaluation::create([
            'patient_id'     => $patient->id,
            'procedure_slug' => $request->validated('procedure_slug'),
            'status'         => Evaluation::STATUS_DRAFT,
            'funnel_step'    => Evaluation::FUNNEL_PROCEDURE,
        ]);

        $this->auditLog->record('evaluation.created', $evaluation);

        return response()->json([
            'token'  => $evaluation->secure_token,
            'status' => $evaluation->status,
        ], 201);
    }

    /**
     * Step 3 — save the completed quiz answers.
     */
    public function quiz(SaveQuizRequest $request, string $token): JsonResponse
    {
        $evaluation = $this->findByToken($token);

        $evaluation->update([
            'quiz_answers' => $request->validated('answers'),
            'status'       => Evaluation::STATUS_SUBMITTED,
            'funnel_step'  => max($evaluation->funnel_step, Evaluation::FUNNEL_QUIZ),
        ]);

        return response()->json(['status' => 'saved']);
    }

    /**
     * Final step — attach patient contact info, record consent, and dispatch AI pipeline.
     *
     * The AI pipeline is a chained job batch:
     *   ValidatePhotoQualityJob
     *     → ExtractFacialLandmarksJob
     *       → CalculateProportionsJob
     *         → GenerateBasicRecommendationsJob (scores lead, sends notification)
     *
     * The batch is cancellable — if photo validation fails, the whole chain is
     * cancelled and the evaluation is marked 'failed'.
     */
    public function submit(SubmitEvaluationRequest $request, string $token): JsonResponse
    {
        $evaluation = $this->findByToken($token);
        $validated  = $request->validated();

        // Create or find patient by email hash (deduplication)
        $emailHash = Patient::hashEmail($validated['patient']['email']);

        $patient = Patient::findByEmail($validated['patient']['email'])
            ?? Patient::find($evaluation->patient_id);

        // Update the patient record with real PHI (encrypted by model casts)
        $patient->update([
            'name_encrypted'  => $validated['patient']['name'],
            'email_encrypted' => $validated['patient']['email'],
            'phone_encrypted' => $validated['patient']['phone'] ?? null,
            'email_hash'      => $emailHash,
            'name_hash'       => hash_hmac('sha256', strtolower($validated['patient']['name']), config('app.key')),
        ]);

        // Finalize evaluation — attach consent metadata
        $evaluation->update([
            'patient_id'   => $patient->id,
            'status'       => Evaluation::STATUS_ANALYZING,
            'completed_at' => now(),
            'funnel_step'  => Evaluation::FUNNEL_SUBMITTED,
            'quiz_answers' => array_merge($evaluation->quiz_answers ?? [], [
                '_consent' => [
                    'hipaa_acknowledged' => $validated['consent']['hipaa_acknowledged'],
                    'terms_accepted'     => $validated['consent']['terms_accepted'],
                    'photo_use_consent'  => $validated['consent']['photo_use_consent'],
                    'consented_at'       => $validated['consent']['consented_at'],
                ],
            ]),
        ]);

        $this->auditLog->record('evaluation.submitted', $evaluation);

        // ── Dispatch AI pipeline as a cancellable batch ───────────────────────
        $evaluationId = $evaluation->id;

        Bus::chain([
            new ValidatePhotoQualityJob($evaluationId),
            new ExtractFacialLandmarksJob($evaluationId),
            new CalculateProportionsJob($evaluationId),
            new GenerateBasicRecommendationsJob($evaluationId),
        ])
        ->onQueue('ai')
        ->catch(function (Throwable $e) use ($evaluationId): void {
            Log::error('AI pipeline chain failed', [
                'evaluation_id' => $evaluationId,
                'error'         => $e->getMessage(),
            ]);

            // Mark evaluation failed if the chain breaks mid-way
            Evaluation::withoutGlobalScopes()
                ->where('id', $evaluationId)
                ->whereNotIn('status', [
                    Evaluation::STATUS_COMPLETE,
                    Evaluation::STATUS_FAILED,
                ])
                ->update(['status' => Evaluation::STATUS_FAILED]);
        })
        ->dispatch();

        return response()->json([
            'status'     => 'submitted',
            'portal_url' => route('intake.success', [], absolute: true),
        ]);
    }

    /**
     * Resolve an evaluation by its public token.
     * Aborts 404 if the token is invalid or belongs to another tenant.
     */
    private function findByToken(string $token): Evaluation
    {
        return Evaluation::where('secure_token', $token)
            ->whereIn('status', [
                Evaluation::STATUS_DRAFT,
                Evaluation::STATUS_SUBMITTED,
                Evaluation::STATUS_ANALYZING,
            ])
            ->firstOrFail();
    }
}
