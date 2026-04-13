import { Head, Link } from '@inertiajs/react';
import { Activity, Building2, FlaskConical, TrendingUp, Users } from 'lucide-react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Stats {
    total_clinics: number;
    active_clinics: number;
    on_trial: number;
    trial_expiring_this_week: number;
    new_this_week: number;
    new_this_month: number;
    evals_total: number;
    evals_today: number;
    evals_this_week: number;
    total_users: number;
}

interface PlanBucket {
    plan: string;
    slug: string;
    count: number;
}

interface DayCount {
    date: string;
    count: number;
}

interface RecentSignup {
    id: string;
    name: string;
    slug: string;
    plan: string;
    on_trial: boolean;
    created_at: string;
}

interface Props {
    stats: Stats;
    planDistribution: PlanBucket[];
    signupsByDay: DayCount[];
    recentSignups: RecentSignup[];
}

// ── Stat card ─────────────────────────────────────────────────────────────────

function StatCard({
    label,
    value,
    sub,
    icon: Icon,
    accent = false,
    warn = false,
}: {
    label: string;
    value: number | string;
    sub?: string;
    icon: React.ElementType;
    accent?: boolean;
    warn?: boolean;
}) {
    return (
        <div className={[
            'rounded-xl border p-5',
            warn ? 'border-amber-500/30 bg-amber-500/5' : 'border-border bg-card',
        ].join(' ')}>
            <div className="flex items-start justify-between">
                <p className="text-xs font-medium uppercase tracking-widest text-muted-foreground">{label}</p>
                <Icon className={['h-4 w-4', warn ? 'text-amber-400' : 'text-muted-foreground'].join(' ')} />
            </div>
            <p className={[
                'mt-3 text-3xl font-bold tabular-nums',
                warn ? 'text-amber-300' : accent ? 'text-[#0E9E8E]' : 'text-foreground',
            ].join(' ')}>
                {value}
            </p>
            {sub && <p className="mt-1 text-xs text-muted-foreground">{sub}</p>}
        </div>
    );
}

// ── Mini sparkline (pure SVG, no deps) ───────────────────────────────────────

function Sparkline({ data }: { data: DayCount[] }) {
    if (data.length < 2) {
        return <p className="text-xs text-muted-foreground">Not enough data yet</p>;
    }

    const max = Math.max(...data.map((d) => d.count), 1);
    const w = 320;
    const h = 48;
    const step = w / (data.length - 1);

    const points = data
        .map((d, i) => `${i * step},${h - (d.count / max) * h}`)
        .join(' ');

    return (
        <svg viewBox={`0 0 ${w} ${h}`} className="w-full" preserveAspectRatio="none">
            <polyline
                points={points}
                fill="none"
                stroke="#0E9E8E"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function AdminDashboard({ stats, planDistribution, signupsByDay, recentSignups }: Props) {
    const totalPlanned = planDistribution.reduce((s, b) => s + b.count, 0) || 1;

    const PLAN_COLORS: Record<string, string> = {
        free: 'bg-zinc-500',
        starter: 'bg-blue-500',
        growth: 'bg-[#0E9E8E]',
        pro: 'bg-[#B8943D]',
        none: 'bg-zinc-700',
    };

    return (
        <>
            <Head title="Platform — Admin" />

            <div className="space-y-8 p-6">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Platform Overview</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date())}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href="/admin/audit-log"
                            className="rounded-lg border border-border px-3 py-1.5 text-sm text-muted-foreground hover:border-[#0E9E8E]/40 hover:text-foreground transition-colors"
                        >
                            Audit log →
                        </Link>
                        <Link
                            href="/admin/tenants"
                            className="rounded-lg border border-border px-3 py-1.5 text-sm text-muted-foreground hover:border-[#0E9E8E]/40 hover:text-foreground transition-colors"
                        >
                            All clinics →
                        </Link>
                    </div>
                </div>

                {/* Primary stats grid */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard label="Active Clinics" value={stats.active_clinics} sub={`${stats.total_clinics} total incl. deactivated`} icon={Building2} accent />
                    <StatCard label="On Free Trial" value={stats.on_trial} sub={`${stats.trial_expiring_this_week} expiring this week`} icon={FlaskConical} warn={stats.trial_expiring_this_week > 0} />
                    <StatCard label="New This Week" value={stats.new_this_week} sub={`${stats.new_this_month} this month`} icon={TrendingUp} />
                    <StatCard label="Total Users" value={stats.total_users} sub="across all clinics" icon={Users} />
                </div>

                {/* Evaluations row */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard label="Evaluations Today" value={stats.evals_today} icon={Activity} accent />
                    <StatCard label="Evaluations This Week" value={stats.evals_this_week} icon={Activity} />
                    <StatCard label="Evaluations All Time" value={stats.evals_total.toLocaleString()} icon={Activity} />
                </div>

                {/* Bottom row: plan dist + sparkline + recent signups */}
                <div className="grid gap-6 lg:grid-cols-3">

                    {/* Plan distribution */}
                    <div className="rounded-xl border border-border bg-card p-5">
                        <p className="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Plan Distribution</p>
                        <div className="space-y-3">
                            {planDistribution.map((b) => (
                                <div key={b.slug}>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="text-foreground">{b.plan}</span>
                                        <span className="tabular-nums text-muted-foreground">
                                            {b.count} ({Math.round((b.count / totalPlanned) * 100)}%)
                                        </span>
                                    </div>
                                    <div className="h-1.5 rounded-full bg-muted/40">
                                        <div
                                            className={['h-1.5 rounded-full', PLAN_COLORS[b.slug] ?? 'bg-zinc-500'].join(' ')}
                                            style={{ width: `${(b.count / totalPlanned) * 100}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                            {planDistribution.length === 0 && (
                                <p className="text-sm text-muted-foreground">No clinics yet.</p>
                            )}
                        </div>
                    </div>

                    {/* Signup sparkline */}
                    <div className="rounded-xl border border-border bg-card p-5">
                        <p className="mb-1 text-xs font-semibold uppercase tracking-widest text-muted-foreground">New Signups — Last 14 Days</p>
                        <p className="mb-4 text-2xl font-bold text-foreground">{stats.new_this_month}</p>
                        <Sparkline data={signupsByDay} />
                    </div>

                    {/* Recent signups */}
                    <div className="rounded-xl border border-border bg-card p-5">
                        <p className="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Recent Signups</p>
                        <ul className="space-y-3">
                            {recentSignups.map((t) => (
                                <li key={t.id} className="flex items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <Link
                                            href={`/admin/tenants/${t.id}`}
                                            className="truncate text-sm font-medium text-foreground hover:text-[#0E9E8E] transition-colors"
                                        >
                                            {t.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">{t.created_at}</p>
                                    </div>
                                    <span className={[
                                        'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                        t.on_trial
                                            ? 'bg-amber-500/15 text-amber-400'
                                            : 'bg-[#0E9E8E]/15 text-[#0E9E8E]',
                                    ].join(' ')}>
                                        {t.on_trial ? 'Trial' : t.plan}
                                    </span>
                                </li>
                            ))}
                            {recentSignups.length === 0 && (
                                <p className="text-sm text-muted-foreground">No signups yet.</p>
                            )}
                        </ul>
                    </div>
                </div>

            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Platform', href: '/admin' }],
};
