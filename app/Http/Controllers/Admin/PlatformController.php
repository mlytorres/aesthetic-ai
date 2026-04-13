<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLogEntry;
use App\Models\Evaluation;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    /**
     * Platform-wide metrics dashboard.
     *
     * All queries intentionally bypass TenantScope so they aggregate across all tenants.
     */
    public function dashboard(): Response
    {
        $now = Carbon::now();
        $weekAgo = $now->copy()->subDays(7);
        $monthAgo = $now->copy()->subDays(30);

        // ── Tenant stats ──────────────────────────────────────────────────────
        $allTenants = Tenant::withoutGlobalScopes()->withTrashed();

        $totalClinics = (clone $allTenants)->count();
        $activeClinics = (clone $allTenants)->whereNull('deleted_at')->count();
        $onTrial = (clone $allTenants)
            ->whereNull('deleted_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now)
            ->whereNull('stripe_id')
            ->count();
        $trialExpiringThisWeek = (clone $allTenants)
            ->whereNull('deleted_at')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)])
            ->whereNull('stripe_id')
            ->count();
        $newThisWeek = (clone $allTenants)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $weekAgo)
            ->count();
        $newThisMonth = (clone $allTenants)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $monthAgo)
            ->count();

        // ── Plan distribution ─────────────────────────────────────────────────
        $planDistribution = Tenant::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->selectRaw('plan_id, COUNT(*) as count')
            ->groupBy('plan_id')
            ->with('plan:id,name,slug')
            ->get()
            ->map(fn ($row) => [
                'plan' => $row->plan?->name ?? 'No plan',
                'slug' => $row->plan?->slug ?? 'none',
                'count' => $row->count,
            ]);

        // ── Evaluation stats ──────────────────────────────────────────────────
        $evalsTotal = Evaluation::withoutGlobalScope(TenantScope::class)->count();
        $evalsToday = Evaluation::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', Carbon::today())
            ->count();
        $evalsThisWeek = Evaluation::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', $weekAgo)
            ->count();

        // ── Signups per day (last 14 days) for sparkline ──────────────────────
        $signupsByDay = Tenant::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $now->copy()->subDays(14))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => $row->count]);

        // ── Recent signups ────────────────────────────────────────────────────
        $recentSignups = Tenant::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->with('plan:id,name,slug')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'plan' => $t->plan?->name ?? '—',
                'on_trial' => $t->trial_ends_at !== null && $t->trial_ends_at->isFuture() && $t->stripe_id === null,
                'created_at' => $t->created_at->toDateString(),
            ]);

        // ── User count ────────────────────────────────────────────────────────
        $totalUsers = User::withoutGlobalScopes()->whereNotNull('tenant_id')->count();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_clinics' => $totalClinics,
                'active_clinics' => $activeClinics,
                'on_trial' => $onTrial,
                'trial_expiring_this_week' => $trialExpiringThisWeek,
                'new_this_week' => $newThisWeek,
                'new_this_month' => $newThisMonth,
                'evals_total' => $evalsTotal,
                'evals_today' => $evalsToday,
                'evals_this_week' => $evalsThisWeek,
                'total_users' => $totalUsers,
            ],
            'planDistribution' => $planDistribution,
            'signupsByDay' => $signupsByDay,
            'recentSignups' => $recentSignups,
        ]);
    }

    /**
     * Platform-wide HIPAA audit log — all tenants, all actions.
     */
    public function auditLog(Request $request): Response
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'string'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = AuditLogEntry::withoutGlobalScopes()
            ->with(['tenant:id,name,slug', 'user:id,name,role'])
            ->when($validated['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($validated['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "%{$v}%"))
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($validated['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLogEntry $e) => [
                'id' => $e->id,
                'tenant_name' => $e->tenant?->name ?? '(platform)',
                'tenant_slug' => $e->tenant?->slug,
                'user_name' => $e->user?->name ?? 'System',
                'user_role' => $e->user?->role,
                'action' => $e->action,
                'subject_type' => $e->subject_type,
                'subject_id' => $e->subject_id,
                'ip_address' => $e->ip_address,
                'created_at' => $e->created_at->toIso8601String(),
            ]);

        // Tenant list for the filter dropdown
        $tenants = Tenant::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('admin/audit-log', [
            'entries' => $entries,
            'tenants' => $tenants,
            'filters' => [
                'tenant_id' => $validated['tenant_id'] ?? null,
                'action' => $validated['action'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }
}
