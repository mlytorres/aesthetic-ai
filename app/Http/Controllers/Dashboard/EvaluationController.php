<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    /**
     * Evaluation priority queue — the main coordinator view.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'active');

        $evaluations = Evaluation::withCount('photos')
            ->when($status === 'active', fn ($q) =>
                $q->whereNotIn('status', [
                    Evaluation::STATUS_BOOKED,
                    Evaluation::STATUS_NO_SHOW,
                    Evaluation::STATUS_NOT_A_FIT,
                    Evaluation::STATUS_DRAFT,
                    Evaluation::STATUS_FAILED,
                ])
            )
            ->when($status !== 'active', fn ($q) => $q->where('status', $status))
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

        // Load patient relation for list view (name/email only)
        $evaluations->load('patient');

        return Inertia::render('evaluations/index', [
            'evaluations' => EvaluationResource::collection($evaluations),
            'filters'     => ['status' => $status],
            'statusCounts' => [
                'analyzing'  => Evaluation::where('status', Evaluation::STATUS_ANALYZING)->count(),
                'complete'   => Evaluation::where('status', Evaluation::STATUS_COMPLETE)->count(),
                'contacted'  => Evaluation::where('status', Evaluation::STATUS_CONTACTED)->count(),
                'booked'     => Evaluation::where('status', Evaluation::STATUS_BOOKED)->count(),
            ],
        ]);
    }

    /**
     * Full evaluation detail — PHI access is audit-logged.
     */
    public function show(Evaluation $evaluation): Response
    {
        $evaluation->load(['patient', 'photos']);

        $this->auditLog->record('evaluation.photos.viewed', $evaluation);

        return Inertia::render('evaluations/show', [
            'evaluation' => (new EvaluationResource($evaluation))->resolve(),
        ]);
    }

    /**
     * Update coordinator-facing status (Contacted / Booked / No-Show / Not a Fit).
     */
    public function updateStatus(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Evaluation::STATUS_CONTACTED,
                Evaluation::STATUS_BOOKED,
                Evaluation::STATUS_NO_SHOW,
                Evaluation::STATUS_NOT_A_FIT,
            ])],
            'coordinator_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $evaluation->update($validated);

        $this->auditLog->record('evaluation.status.changed', $evaluation, [
            'new_status' => $validated['status'],
        ]);

        return back()->with('flash.success', 'Status updated.');
    }

    /**
     * Save coordinator notes without changing status.
     */
    public function updateNotes(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $validated = $request->validate([
            'coordinator_notes' => ['nullable', 'string', 'max:2000'],
            'follow_up_at'      => ['nullable', 'date'],
        ]);

        $evaluation->update($validated);

        return back()->with('flash.success', 'Notes saved.');
    }
}
