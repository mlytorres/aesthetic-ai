<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLogEntry;
use App\Models\Evaluation;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('analytics/index', [
            'weeklyVolume'    => Inertia::defer(fn () => $this->weeklyVolume()),
            'statusFunnel'    => Inertia::defer(fn () => $this->statusFunnel()),
            'scoreDistrib'    => Inertia::defer(fn () => $this->scoreDistribution()),
            'priorityBreakdown' => Inertia::defer(fn () => $this->priorityBreakdown()),
            'avgTimeToContact'  => Inertia::defer(fn () => $this->avgTimeToContact()),
            'intakeFunnel'    => Inertia::defer(fn () => $this->intakeFunnel()),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Evaluation counts grouped by ISO week for the last 8 weeks.
     *
     * @return array<int, array{week: string, count: int}>
     */
    private function weeklyVolume(): array
    {
        $weeks = [];

        for ($i = 7; $i >= 0; $i--) {
            $start = Carbon::now()->startOfWeek()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            $weeks[] = [
                'week' => $start->format('M d'),
                'count' => Evaluation::whereBetween('created_at', [$start, $end])
                    ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
                    ->count(),
            ];
        }

        return $weeks;
    }

    /**
     * Count of evaluations per status, ordered by workflow progression.
     *
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function statusFunnel(): array
    {
        $statuses = [
            Evaluation::STATUS_SUBMITTED => 'Submitted',
            Evaluation::STATUS_ANALYZING => 'Analyzing',
            Evaluation::STATUS_COMPLETE => 'Complete',
            Evaluation::STATUS_CONTACTED => 'Contacted',
            Evaluation::STATUS_BOOKED => 'Booked',
            Evaluation::STATUS_NO_SHOW => 'No Show',
            Evaluation::STATUS_NOT_A_FIT => 'Not a Fit',
            Evaluation::STATUS_FAILED => 'Failed',
        ];

        $counts = Evaluation::withoutGlobalScopes()
            ->selectRaw('status, count(*) as total')
            ->whereIn('status', array_keys($statuses))
            ->where('tenant_id', TenantContext::id())
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect($statuses)
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Lead score distribution in 20-point buckets.
     *
     * @return array<int, array{bucket: string, count: int}>
     */
    private function scoreDistribution(): array
    {
        $buckets = [
            '0–19' => [0, 19],
            '20–39' => [20, 39],
            '40–59' => [40, 59],
            '60–79' => [60, 79],
            '80–100' => [80, 100],
        ];

        return collect($buckets)
            ->map(fn (array $range, string $label) => [
                'bucket' => $label,
                'count' => Evaluation::whereNotNull('lead_score')
                    ->whereBetween('lead_score', $range)
                    ->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Count of evaluations per priority level.
     *
     * @return array<int, array{priority: string, label: string, count: int}>
     */
    private function priorityBreakdown(): array
    {
        $priorities = [
            Evaluation::PRIORITY_URGENT => 'Urgent',
            Evaluation::PRIORITY_HIGH => 'High',
            Evaluation::PRIORITY_MEDIUM => 'Medium',
            Evaluation::PRIORITY_STANDARD => 'Standard',
        ];

        $counts = Evaluation::withoutGlobalScopes()
            ->selectRaw('priority, count(*) as total')
            ->whereIn('priority', array_keys($priorities))
            ->where('tenant_id', TenantContext::id())
            ->groupBy('priority')
            ->pluck('total', 'priority');

        return collect($priorities)
            ->map(fn (string $label, string $priority) => [
                'priority' => $priority,
                'label' => $label,
                'count' => (int) ($counts[$priority] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Intake wizard drop-off funnel.
     *
     * Returns count of evaluations that reached each step, showing where
     * patients abandoned. Each step count is the number who got AT LEAST that far.
     *
     * @return array<int, array{step: int, label: string, count: int, rate: float}>
     */
    private function intakeFunnel(): array
    {
        $steps = [
            Evaluation::FUNNEL_PROCEDURE => 'Procedure Selected',
            Evaluation::FUNNEL_QUIZ      => 'Quiz Completed',
            Evaluation::FUNNEL_PHOTOS    => 'Photos Uploaded',
            Evaluation::FUNNEL_SUBMITTED => 'Submitted',
        ];

        // Count evaluations that reached at least each step.
        $counts = collect($steps)->mapWithKeys(
            fn (string $label, int $step) => [
                $step => Evaluation::withoutGlobalScopes()
                    ->where('tenant_id', TenantContext::id())
                    ->where('funnel_step', '>=', $step)
                    ->count(),
            ]
        );

        $total = $counts->get(Evaluation::FUNNEL_PROCEDURE, 0);

        return collect($steps)
            ->map(fn (string $label, int $step) => [
                'step'  => $step,
                'label' => $label,
                'count' => $counts->get($step, 0),
                'rate'  => $total > 0 ? round($counts->get($step, 0) / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Average hours from evaluation creation to first 'contacted' status change.
     * Uses audit_log_entries for accuracy (records the exact timestamp).
     */
    private function avgTimeToContact(): ?float
    {
        // Pull every evaluation that has ever been contacted, with its creation time.
        $evaluationCreatedAt = Evaluation::where('status', '!=', Evaluation::STATUS_DRAFT)
            ->pluck('created_at', 'id');

        if ($evaluationCreatedAt->isEmpty()) {
            return null;
        }

        // Find the earliest audit entry per evaluation where status changed to 'contacted'.
        $contactedAt = AuditLogEntry::where('action', 'evaluation.status.changed')
            ->whereIn('subject_id', $evaluationCreatedAt->keys())
            ->where('subject_type', 'Evaluation')
            ->whereRaw("JSON_EXTRACT(metadata, '$.new_status') = 'contacted'")
            ->selectRaw('subject_id, MIN(created_at) as first_contacted_at')
            ->groupBy('subject_id')
            ->pluck('first_contacted_at', 'subject_id');

        if ($contactedAt->isEmpty()) {
            return null;
        }

        $totalHours = 0.0;
        $count = 0;

        foreach ($contactedAt as $evaluationId => $firstContact) {
            $created = $evaluationCreatedAt->get($evaluationId);

            if ($created === null) {
                continue;
            }

            $hours = Carbon::parse($created)->diffInMinutes(Carbon::parse($firstContact)) / 60;
            $totalHours += $hours;
            $count++;
        }

        return $count > 0 ? round($totalHours / $count, 1) : null;
    }
}
