<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Jobs\SendConsultationInviteJob;
use App\Models\Consultation;
use App\Models\Evaluation;
use App\Services\DailyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    public function __construct(private readonly DailyService $daily) {}

    /**
     * List consultations for an evaluation (JSON — used by the show page sidebar).
     */
    public function index(string $evaluationId): JsonResponse
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $this->authorizeEvaluation($evaluation);

        $consultations = $evaluation->consultations()
            ->with('coordinator:id,name')
            ->orderByDesc('scheduled_at')
            ->get(['id', 'scheduled_at', 'duration_minutes', 'status', 'daily_room_url', 'token', 'notes', 'coordinator_id', 'cancelled_at']);

        return response()->json($consultations);
    }

    /**
     * Schedule a new video consultation for an evaluation.
     */
    public function store(Request $request, string $evaluationId): JsonResponse
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $this->authorizeEvaluation($evaluation);

        $tenant = TenantContext::get();

        if (! $tenant->hasVideoConsultations()) {
            return response()->json(['message' => 'Video consultations are not enabled for your plan.'], 403);
        }

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'in:15,30,45,60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $roomName = 'consult-'.Str::uuid();
        $scheduledAt = (int) now()->parse($validated['scheduled_at'])->addMinutes((int) ($validated['duration_minutes'] ?? 30))->timestamp;

        $room = $this->daily->createRoom($roomName, $scheduledAt + 3600); // expire 1h after session end

        $consultation = Consultation::create([
            'tenant_id' => $tenant->id,
            'evaluation_id' => $evaluation->id,
            'coordinator_id' => $request->user()->id,
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? 30,
            'daily_room_name' => $room['name'],
            'daily_room_url' => $room['url'],
            'token' => (string) Str::uuid(),
            'status' => Consultation::STATUS_SCHEDULED,
            'notes' => $validated['notes'] ?? null,
        ]);

        SendConsultationInviteJob::dispatch($consultation->id)->onQueue('notifications');

        return response()->json([
            'id' => $consultation->id,
            'scheduled_at' => $consultation->scheduled_at->toIso8601String(),
            'duration_minutes' => $consultation->duration_minutes,
            'status' => $consultation->status,
            'daily_room_url' => $consultation->daily_room_url,
            'patient_join_url' => route('consult.join', $consultation->token),
        ], 201);
    }

    /**
     * Cancel a consultation. Deletes the Daily.co room.
     */
    public function cancel(Request $request, string $consultationId): JsonResponse
    {
        $consultation = Consultation::findOrFail($consultationId);
        $this->authorizeConsultation($consultation);

        if (! $consultation->isCancellable()) {
            return response()->json(['message' => 'This consultation cannot be cancelled.'], 422);
        }

        $this->daily->deleteRoom($consultation->daily_room_name);

        $consultation->update([
            'status' => Consultation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * Public join page — patient arrives here via the link in their invite email.
     * No auth. Gated by the consultation token (UUID).
     */
    public function join(string $token): Response
    {
        $consultation = Consultation::where('token', $token)
            ->with('evaluation.patient', 'evaluation.tenant')
            ->firstOrFail();

        if ($consultation->status === Consultation::STATUS_CANCELLED) {
            return Inertia::render('consult/cancelled');
        }

        $patient = $consultation->evaluation?->patient;
        $patientName = $patient?->name ?? 'Patient';

        // Generate a short-lived Daily meeting token so the patient can join the private room.
        $expiresAt = $consultation->scheduled_at->addMinutes($consultation->duration_minutes + 30)->timestamp;
        $meetingToken = $this->daily->createMeetingToken(
            $consultation->daily_room_name,
            $patientName,
            (int) $expiresAt,
        );

        return Inertia::render('consult/join', [
            'consultation' => [
                'id' => $consultation->id,
                'scheduled_at' => $consultation->scheduled_at->toIso8601String(),
                'duration_minutes' => $consultation->duration_minutes,
                'status' => $consultation->status,
                'daily_room_url' => $consultation->daily_room_url,
                'meeting_token' => $meetingToken,
                'clinic_name' => $consultation->evaluation?->tenant?->name ?? 'Your Clinic',
                'patient_name' => $patientName,
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function authorizeEvaluation(Evaluation $evaluation): void
    {
        $tenant = TenantContext::get();

        if ($evaluation->tenant_id !== $tenant->id) {
            abort(403);
        }
    }

    private function authorizeConsultation(Consultation $consultation): void
    {
        $tenant = TenantContext::get();

        if ($consultation->tenant_id !== $tenant->id) {
            abort(403);
        }
    }
}
