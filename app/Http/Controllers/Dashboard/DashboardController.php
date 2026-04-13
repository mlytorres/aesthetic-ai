<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenant = TenantContext::get();

        // ── Stats ──────────────────────────────────────────────────────────────
        $todayStart = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        $stats = [
            'urgent' => Evaluation::where('priority', 'urgent')
                ->whereNotIn('status', [
                    Evaluation::STATUS_BOOKED,
                    Evaluation::STATUS_NO_SHOW,
                    Evaluation::STATUS_NOT_A_FIT,
                    Evaluation::STATUS_FAILED,
                    Evaluation::STATUS_DRAFT,
                ])
                ->count(),

            'new_today' => Evaluation::where('created_at', '>=', $todayStart)
                ->whereNotIn('status', [Evaluation::STATUS_DRAFT])
                ->count(),

            'pending_review' => Evaluation::whereIn('status', [
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
                'id' => $ev->id,
                'procedure_slug' => $ev->procedure_slug,
                'status' => $ev->status,
                'priority' => $ev->priority,
                'lead_score' => $ev->lead_score,
                'created_at' => $ev->created_at->toIso8601String(),
                // Decrypt patient name for display — null-safe for stub patients
                'patient_name' => $ev->patient?->name_encrypted ?? null,
            ]);

        // ── Trial banner data ──────────────────────────────────────────────────
        // null  → not on trial (free plan or active subscription)
        // 0     → trial has expired (show billing prompt)
        // 1-14  → days remaining (show countdown banner)
        $trialDaysRemaining = null;

        if ($tenant->plan?->slug !== 'free' && $tenant->trial_ends_at !== null) {
            $trialDaysRemaining = max(0, (int) ceil($tenant->trial_ends_at->diffInRealSeconds(now()) / 86400));

            // Once subscribed, suppress the trial banner.
            if ($tenant->stripe_id !== null && $tenant->subscribed('default')) {
                $trialDaysRemaining = null;
            }
        }

        // ── Onboarding checklist ───────────────────────────────────────────────
        // Shown to new clinics until all steps are complete or they dismiss it.
        $onboardingDismissed = (bool) ($tenant->settings['onboarding_dismissed'] ?? false);

        $hasTeamMember = $tenant->users()
            ->where('role', '!=', User::ROLE_OWNER)
            ->exists();

        $hasEvaluation = Evaluation::exists();

        $onboarding = $onboardingDismissed ? null : [
            'dismissed' => false,
            'steps' => [
                [
                    'key' => 'verified',
                    'label' => 'Verify your email',
                    'done' => (bool) Auth::user()?->hasVerifiedEmail(),
                    'href' => null,
                ],
                [
                    'key' => 'settings',
                    'label' => 'Configure clinic settings',
                    'done' => (bool) ($tenant->settings['clinic_configured'] ?? false),
                    'href' => '/clinic/settings',
                ],
                [
                    'key' => 'team',
                    'label' => 'Invite a team member',
                    'done' => $hasTeamMember,
                    'href' => $hasTeamMember ? null : '/clinic/team',
                ],
                [
                    'key' => 'evaluation',
                    'label' => 'Receive your first evaluation',
                    'done' => $hasEvaluation,
                    'href' => $hasEvaluation ? null : null,
                ],
            ],
        ];

        // Hide the checklist if all steps are complete.
        if ($onboarding !== null) {
            $allDone = collect($onboarding['steps'])->every(fn ($s) => $s['done']);

            if ($allDone) {
                $onboarding = null;
            }
        }

        return Inertia::render('dashboard', [
            'clinic_name' => $tenant->name,
            'stats' => $stats,
            'recent_evaluations' => $recent,
            'trial_days_remaining' => $trialDaysRemaining,
            'onboarding' => $onboarding,
        ]);
    }
}
