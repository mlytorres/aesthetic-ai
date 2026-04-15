<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Models\AuditLogEntry;
use App\Models\Evaluation;
use App\Services\AuditLog;
use App\Services\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly AuditLog $auditLog,
        private readonly WebhookService $webhooks,
    ) {}

    /**
     * Evaluation priority queue — the main coordinator view.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');
        $priority = $request->query('priority');
        $minScore = $request->query('min_score');

        $evaluations = Evaluation::withCount('photos')
            ->when($status === 'active', fn ($q) => $q->whereNotIn('status', [
                Evaluation::STATUS_BOOKED,
                Evaluation::STATUS_NO_SHOW,
                Evaluation::STATUS_NOT_A_FIT,
                Evaluation::STATUS_DRAFT,
                Evaluation::STATUS_FAILED,
            ]))
            ->when($status !== 'active', fn ($q) => $q->where('status', $status))
            ->when($search, function ($q, $search) {
                $q->whereHas('patient', function ($pq) use ($search) {
                    $pq->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%');
                });
            })
            ->when($priority, fn ($q, $priority) => $q->where('priority', $priority))
            ->when(is_numeric($minScore), fn ($q) => $q->where('lead_score', '>=', (int) $minScore))
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent'   THEN 1
                    WHEN 'high'     THEN 2
                    WHEN 'medium'   THEN 3
                    WHEN 'standard' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc('lead_score')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $evaluations->load('patient');

        return Inertia::render('evaluations/index', [
            'evaluations' => EvaluationResource::collection($evaluations),
            'filters' => [
                'status' => $status,
                'search' => $search,
                'priority' => $priority,
                'min_score' => $minScore,
            ],
            'statusCounts' => [
                'analyzing' => Evaluation::where('status', Evaluation::STATUS_ANALYZING)->count(),
                'complete' => Evaluation::where('status', Evaluation::STATUS_COMPLETE)->count(),
                'contacted' => Evaluation::where('status', Evaluation::STATUS_CONTACTED)->count(),
                'booked' => Evaluation::where('status', Evaluation::STATUS_BOOKED)->count(),
            ],
        ]);
    }

    /**
     * Full evaluation detail — PHI access is audit-logged.
     *
     * NOTE: Uses string $evaluationId instead of Evaluation $evaluation to avoid
     * triggering TenantScope during SubstituteBindings (which runs before the
     * 'tenant' route middleware). The manual findOrFail() here fires after
     * TenantContext is set, so the scope applies correctly.
     */
    public function show(string $evaluationId): Response
    {
        $evaluation = Evaluation::with(['patient', 'photos'])->findOrFail($evaluationId);

        $this->auditLog->record('evaluation.photos.viewed', $evaluation);

        return Inertia::render('evaluations/show', [
            'evaluation' => (new EvaluationResource($evaluation))->resolve(),
            'portal_url' => route('intake.patient.portal', ['token' => $evaluation->secure_token]),
            'video_consultations_enabled' => TenantContext::get()->hasVideoConsultations(),

            // Deferred: audit timeline loads after the main page renders.
            'auditEntries' => Inertia::defer(fn () => AuditLogEntry::where('subject_type', 'Evaluation')
                ->where('subject_id', $evaluationId)
                ->with('user:id,name,role')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (AuditLogEntry $e) => [
                    'id' => $e->id,
                    'action' => $e->action,
                    'user_name' => $e->user?->name ?? 'System',
                    'user_role' => $e->user?->role,
                    'ip_address' => $e->ip_address,
                    'metadata' => $e->metadata,
                    'created_at' => $e->created_at->toIso8601String(),
                ])
                ->all()
            ),
        ]);
    }

    /**
     * Update coordinator-facing status (Contacted / Booked / No-Show / Not a Fit).
     * Fires an evaluation.status_changed webhook so connected CRMs stay in sync.
     */
    public function updateStatus(Request $request, string $evaluationId): RedirectResponse
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        Gate::authorize('updateStatus', $evaluation);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Evaluation::STATUS_CONTACTED,
                Evaluation::STATUS_BOOKED,
                Evaluation::STATUS_NO_SHOW,
                Evaluation::STATUS_NOT_A_FIT,
            ])],
        ]);

        $previousStatus = $evaluation->status;

        $evaluation->update(['status' => $validated['status']]);

        $this->auditLog->record('evaluation.status.updated', $evaluation, [
            'previous_status' => $previousStatus,
            'new_status' => $validated['status'],
        ]);

        $this->webhooks->dispatch($evaluation, 'evaluation.status_changed', [
            'previous_status' => $previousStatus,
            'new_status' => $validated['status'],
        ]);

        return back()->with('flash.success', 'Status updated.');
    }

    /**
     * Save coordinator notes and optional follow-up date.
     */
    public function updateNotes(Request $request, string $evaluationId): RedirectResponse
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        Gate::authorize('updateNotes', $evaluation);

        $validated = $request->validate([
            'coordinator_notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $evaluation->update([
            'coordinator_notes' => $validated['coordinator_notes'],
            'follow_up_at' => $validated['follow_up_at'],
        ]);

        $this->auditLog->record('evaluation.notes.updated', $evaluation);

        return back()->with('flash.success', 'Notes saved.');
    }
}
