<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Events\EvaluationReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\CreateEvaluationRequest;
use App\Http\Requests\Intake\SaveQuizRequest;
use App\Http\Requests\Intake\SubmitEvaluationRequest;
use App\Http\Requests\Intake\UpdateLeadRequest;
use App\Jobs\AI\CalculateBodyProportionsJob;
use App\Jobs\AI\CalculateProportionsJob;
use App\Jobs\AI\ExtractBodyLandmarksJob;
use App\Jobs\AI\ExtractFacialLandmarksJob;
use App\Jobs\AI\GenerateBasicRecommendationsJob;
use App\Jobs\AI\GenerateSimulationJob;
use App\Jobs\AI\SendPatientConfirmationJob;
use App\Jobs\AI\SendPatientSmsConfirmationJob;
use App\Jobs\AI\ValidatePhotoQualityJob;
use App\Jobs\SendUsageOverageAlertJob;
use App\Models\Evaluation;
use App\Models\Patient;
use App\Services\AuditLog;
use App\Services\ProcedureRegistry;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Facades\TenantContext;

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
            'email_encrypted' => 'pending_'.Str::uuid(), // placeholder until submit
            'email_hash' => hash('sha256', 'pending_'.Str::uuid()),
            'created_via' => 'widget',
        ]);

        $evaluation = Evaluation::create([
            'patient_id' => $patient->id,
            'procedure_slug' => $request->validated('procedure_slug'),
            'status' => Evaluation::STATUS_DRAFT,
            'funnel_step' => Evaluation::FUNNEL_PROCEDURE,
        ]);

        $this->auditLog->record('evaluation.created', $evaluation);

        // Check if usage has crossed the 80% threshold and alert the clinic Owner.
        SendUsageOverageAlertJob::dispatch($evaluation->tenant_id)->onQueue('notifications');

        return response()->json([
            'token' => $evaluation->secure_token,
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
            'status' => Evaluation::STATUS_SUBMITTED,
            'funnel_step' => max($evaluation->funnel_step, Evaluation::FUNNEL_QUIZ),
        ]);

        return response()->json(['status' => 'saved']);
    }
    
    /**
     * Step 2 (Optional/Early) — update patient contact info as soon as it's entered.
     * Use this when lead_capture_position is 'beginning'.
     */
    public function lead(UpdateLeadRequest $request, string $token): JsonResponse
    {
        $evaluation = $this->findByToken($token);
        $validated = $request->validated();

        $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => config('services.turnstile.secret_key'),
            'response' => $validated['turnstile_token'],
            'remoteip' => $request->ip(),
        ]);

        if (!$turnstileResponse->json('success')) {
            abort(403, 'Security validation failed.');
        }

        $emailHash = Patient::hashEmail($validated['patient']['email']);
        $tenantId = TenantContext::get()->id;

        // Check for cooldown duplication now (early prevention)
        $recentDuplicate = Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('procedure_slug', $evaluation->procedure_slug)
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT, Evaluation::STATUS_FAILED])
            ->whereHas('patient', fn($q) => $q->where('email_hash', $emailHash))
            ->where('created_at', '>=', now()->subHours(24))
            ->where('id', '!=', $evaluation->id)
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'message' => 'You have already submitted an evaluation for this procedure recently.',
            ], 429);
        }

        // Update the patient record with real PHI
        $patient = Patient::find($evaluation->patient_id);
        $patient->update([
            'name_encrypted'  => $validated['patient']['name'],
            'email_encrypted' => $validated['patient']['email'],
            'phone_encrypted' => $validated['patient']['phone'] ?? null,
            'email_hash'      => $emailHash,
            'name_hash'       => hash_hmac('sha256', strtolower($validated['patient']['name']), config('app.key')),
        ]);

        $evaluation->update([
            'funnel_step' => max($evaluation->funnel_step, Evaluation::FUNNEL_PROCEDURE),
        ]);

        return response()->json(['status' => 'lead_captured']);
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
        $validated = $request->validated();

        $turnstileToken = $validated['turnstile_token'] ?? null;

        if ($turnstileToken) {
            $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $turnstileToken,
                'remoteip' => $request->ip(),
            ]);

            if (! $turnstileResponse->json('success')) {
                abort(403, 'Security validation failed. Please refresh the page and try again.');
            }
        }

        // ── Per-email+procedure+tenant cooldown (24 h) ────────────────────────
        // Prevent the same email address from submitting the same procedure at
        // the same clinic more than once per day — bot loops and accidental
        // double-submits included.
        $candidateEmail = $validated['patient']['email'] ?? Patient::find($evaluation->patient_id)->email; // if already set
        $emailHash = Patient::hashEmail($candidateEmail);
        $tenantId = TenantContext::get()->id;

        $recentDuplicate = Evaluation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('procedure_slug', $evaluation->procedure_slug)
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT, Evaluation::STATUS_FAILED])
            ->whereHas('patient', fn ($q) => $q->where('email_hash', $emailHash))
            ->where('created_at', '>=', now()->subHours(24))
            ->where('id', '!=', $evaluation->id)
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'message' => 'You have already submitted an evaluation for this procedure recently. Please wait 24 hours before submitting again.',
            ], 429);
        }

        // Create or find patient by email hash (deduplication)
        // If we collected it early, we already have a real email_hash.
        $email = $validated['patient']['email'] ?? null;
        $patient = null;

        if ($email) {
            $emailHash = Patient::hashEmail($email);
            $patient = Patient::findByEmail($email) ?? Patient::find($evaluation->patient_id);
            
            $patient->update([
                'name_encrypted' => $validated['patient']['name'],
                'email_encrypted' => $validated['patient']['email'],
                'phone_encrypted' => $validated['patient']['phone'] ?? null,
                'email_hash' => $emailHash,
                'name_hash' => hash_hmac('sha256', strtolower($validated['patient']['name']), config('app.key')),
            ]);
        } else {
            // Already collected early — just get the current patient
            $patient = Patient::find($evaluation->patient_id);
        }

        // Finalize evaluation — attach consent metadata
        $evaluation->update([
            'patient_id' => $patient->id,
            'status' => Evaluation::STATUS_ANALYZING,
            'completed_at' => now(),
            'funnel_step' => Evaluation::FUNNEL_SUBMITTED,
            'quiz_answers' => array_merge($evaluation->quiz_answers ?? [], [
                '_consent' => [
                    'hipaa_acknowledged' => $validated['consent']['hipaa_acknowledged'],
                    'terms_accepted' => $validated['consent']['terms_accepted'],
                    'photo_use_consent' => $validated['consent']['photo_use_consent'],
                    'opt_in_sms' => (bool) ($validated['consent']['opt_in_sms'] ?? false),
                    'consented_at' => $validated['consent']['consented_at'],
                ],
            ]),
        ]);

        $this->auditLog->record('evaluation.submitted', $evaluation);

        // Notify clinic staff in real time via Reverb WebSocket.
        EvaluationReceived::dispatch($evaluation);

        // Send immediate confirmation email to patient (before AI pipeline runs).
        SendPatientConfirmationJob::dispatch($evaluation->id)->onQueue('notifications');

        // Optionally send SMS confirmation if patient opted in.
        if ((bool) ($validated['consent']['opt_in_sms'] ?? false)) {
            SendPatientSmsConfirmationJob::dispatch($evaluation->id)->onQueue('notifications');
        }

        // Check if the tenant just hit 80% usage boundary.
        \App\Jobs\Billing\CheckTenantUsageCapJob::dispatch($evaluation->tenant_id)->onQueue('notifications');

        // ── Dispatch AI pipeline as a cancellable batch ───────────────────────
        $evaluationId = $evaluation->id;
        $procedureSlug = $evaluation->procedure_slug;

        // ProcedureRegistry determines whether to use the body or face AI pipeline.
        // Body: ExtractBodyLandmarksJob → CalculateBodyProportionsJob
        // Face: ExtractFacialLandmarksJob → CalculateProportionsJob
        $isBodyProcedure = ProcedureRegistry::isBodyProcedure($procedureSlug);

        $landmarkJob = $isBodyProcedure
            ? new ExtractBodyLandmarksJob($evaluationId)
            : new ExtractFacialLandmarksJob($evaluationId);

        $proportionsJob = $isBodyProcedure
            ? new CalculateBodyProportionsJob($evaluationId)
            : new CalculateProportionsJob($evaluationId);

        Bus::chain([
            new ValidatePhotoQualityJob($evaluationId),
            $landmarkJob,
            $proportionsJob,
            new GenerateBasicRecommendationsJob($evaluationId),
            new GenerateSimulationJob($evaluationId),
        ])
            ->onQueue('ai')
            ->catch(function (Throwable $e) use ($evaluationId): void {
                Log::error('AI pipeline chain failed', [
                    'evaluation_id' => $evaluationId,
                    'error' => $e->getMessage(),
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
            'status' => 'submitted',
            'portal_url' => route('intake.success', [
                'token' => $evaluation->secure_token,
                'name' => $patient->name,
                'email' => $patient->email,
            ], absolute: true),
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
