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
            'weeklyVolume'      => Inertia::defer(fn () => $this->weeklyVolume()),
            'statusFunnel'      => Inertia::defer(fn () => $this->statusFunnel()),
            'scoreDistrib'      => Inertia::defer(fn () => $this->scoreDistribution()),
            'priorityBreakdown' => Inertia::defer(fn () => $this->priorityBreakdown()),
            'avgTimeToContact'  => Inertia::defer(fn () => $this->avgTimeToContact()),
            'intakeFunnel'      => Inertia::defer(fn () => $this->intakeFunnel()),
            'monthOverMonth'    => Inertia::defer(fn () => $this->monthOverMonth()),
            'procedureMix'      => Inertia::defer(fn () => $this->procedureMix()),
            'scoreVsBooking'    => Inertia::defer(fn () => $this->scoreVsBooking()),
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
     * Month-over-month comparison: evaluations count, avg lead score, and bookings.
     * Compares the current calendar month against the previous calendar month.
     *
     * @return array{
     *   current_month: string,
     *   previous_month: string,
     *   evaluations: array{current: int, previous: int, delta: int},
     *   avg_score: array{current: float|null, previous: float|null, delta: float|null},
     *   booked: array{current: int, previous: int, delta: int}
     * }
     */
    private function monthOverMonth(): array
    {
        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd   = Carbon::now()->endOfMonth();
        $prevStart    = Carbon::now()->subMonth()->startOfMonth();
        $prevEnd      = Carbon::now()->subMonth()->endOfMonth();

        $curr = Evaluation::whereBetween('created_at', [$currentStart, $currentEnd])
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
            ->selectRaw('count(*) as total, avg(lead_score) as avg_score')
            ->first();

        $prev = Evaluation::whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
            ->selectRaw('count(*) as total, avg(lead_score) as avg_score')
            ->first();

        $currBooked = Evaluation::whereBetween('created_at', [$currentStart, $currentEnd])
            ->where('status', Evaluation::STATUS_BOOKED)
            ->count();

        $prevBooked = Evaluation::whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('status', Evaluation::STATUS_BOOKED)
            ->count();

        $currTotal = (int) ($curr?->total ?? 0);
        $prevTotal = (int) ($prev?->total ?? 0);
        $currScore = $curr?->avg_score !== null ? round((float) $curr->avg_score, 1) : null;
        $prevScore = $prev?->avg_score !== null ? round((float) $prev->avg_score, 1) : null;

        return [
            'current_month'  => Carbon::now()->format('F Y'),
            'previous_month' => Carbon::now()->subMonth()->format('F Y'),
            'evaluations'    => [
                'current'  => $currTotal,
                'previous' => $prevTotal,
                'delta'    => $currTotal - $prevTotal,
            ],
            'avg_score'      => [
                'current'  => $currScore,
                'previous' => $prevScore,
                'delta'    => ($currScore !== null && $prevScore !== null) ? round($currScore - $prevScore, 1) : null,
            ],
            'booked'         => [
                'current'  => $currBooked,
                'previous' => $prevBooked,
                'delta'    => $currBooked - $prevBooked,
            ],
        ];
    }

    /**
     * Evaluation count and booking rate per procedure slug.
     * Ordered by volume descending.
     *
     * @return array<int, array{procedure: string, label: string, count: int, booked: int, booking_rate: float}>
     */
    private function procedureMix(): array
    {
        $rows = Evaluation::withoutGlobalScopes()
            ->where('tenant_id', TenantContext::id())
            ->whereNotNull('procedure_slug')
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
            ->selectRaw('procedure_slug, count(*) as total, sum(case when status = ? then 1 else 0 end) as booked_count', [
                Evaluation::STATUS_BOOKED,
            ])
            ->groupBy('procedure_slug')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'procedure'    => $row->procedure_slug,
            'label'        => $this->procedureLabel($row->procedure_slug),
            'count'        => (int) $row->total,
            'booked'       => (int) $row->booked_count,
            'booking_rate' => $row->total > 0 ? round((int) $row->booked_count / (int) $row->total * 100, 1) : 0.0,
        ])->values()->all();
    }

    /**
     * Booking conversion rate per lead score bucket (0–19, 20–39, … 80–100).
     * Shows which score bands convert best to booked appointments.
     *
     * @return array<int, array{bucket: string, total: int, booked: int, booking_rate: float}>
     */
    private function scoreVsBooking(): array
    {
        $buckets = [
            '0–19'   => [0, 19],
            '20–39'  => [20, 39],
            '40–59'  => [40, 59],
            '60–79'  => [60, 79],
            '80–100' => [80, 100],
        ];

        return collect($buckets)
            ->map(function (array $range, string $label): array {
                $total  = Evaluation::whereNotNull('lead_score')->whereBetween('lead_score', $range)->count();
                $booked = Evaluation::whereNotNull('lead_score')
                    ->whereBetween('lead_score', $range)
                    ->where('status', Evaluation::STATUS_BOOKED)
                    ->count();

                return [
                    'bucket'       => $label,
                    'total'        => $total,
                    'booked'       => $booked,
                    'booking_rate' => $total > 0 ? round($booked / $total * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Human-readable label for a procedure slug.
     */
    private function procedureLabel(string $slug): string
    {
        return match ($slug) {
            'rhinoplasty'         => 'Rhinoplasty',
            'bbl'                 => 'BBL',
            'lipo_360'            => 'Lipo 360°',
            'breast_augmentation' => 'Breast Aug.',
            'facelift'            => 'Facelift',
            default               => ucwords(str_replace('_', ' ', $slug)),
        };
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
