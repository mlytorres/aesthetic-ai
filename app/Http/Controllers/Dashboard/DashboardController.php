<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenant = TenantContext::get();

        // ── Stats ──────────────────────────────────────────────────────────────
        $todayStart     = Carbon::today();
        $weekStart      = Carbon::now()->startOfWeek();

        $stats = [
            'urgent'           => Evaluation::where('priority', 'urgent')
                ->whereNotIn('status', [
                    Evaluation::STATUS_BOOKED,
                    Evaluation::STATUS_NO_SHOW,
                    Evaluation::STATUS_NOT_A_FIT,
                    Evaluation::STATUS_FAILED,
                    Evaluation::STATUS_DRAFT,
                ])
                ->count(),

            'new_today'        => Evaluation::where('created_at', '>=', $todayStart)
                ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
                ->count(),

            'pending_review'   => Evaluation::whereIn('status', [
                Evaluation::STATUS_COMPLETE,
                Evaluation::STATUS_SUBMITTED,
            ])->count(),

            'booked_this_week' => Evaluation::where('status', Evaluation::STATUS_BOOKED)
                ->where('updated_at', '>=', $weekStart)
                ->count(),
        ];

        // ── Recent evaluations (10 most recent, with patient name) ─────────────
        $recent = Evaluation::with('patient')
            ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Evaluation $ev) => [
                'id'             => $ev->id,
                'procedure_slug' => $ev->procedure_slug,
                'status'         => $ev->status,
                'priority'       => $ev->priority,
                'lead_score'     => $ev->lead_score,
                'created_at'     => $ev->created_at->toIso8601String(),
                // Decrypt patient name for display — null-safe for stub patients
                'patient_name'   => $ev->patient?->name_encrypted ?? null,
            ]);

        return Inertia::render('dashboard', [
            'clinic_name'        => $tenant->name,
            'stats'              => $stats,
            'recent_evaluations' => $recent,
        ]);
    }
}
