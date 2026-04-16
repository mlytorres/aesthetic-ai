import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { exportMethod, index, show } from '@/routes/evaluations';

// ── Types ────────────────────────────────────────────────────────────────────

interface Patient {
    id: string;
    name: string | null;
    email: string | null;
    phone: string | null;
}

interface ScoreBreakdownFactor {
    label: string;
    earned: number;
    max: number;
}

interface ScoreBreakdown {
    timeline: ScoreBreakdownFactor;
    budget: ScoreBreakdownFactor;
    ai_harmony: ScoreBreakdownFactor;
    photo_quality: ScoreBreakdownFactor;
    concerns: ScoreBreakdownFactor;
    referral: ScoreBreakdownFactor;
}

interface Evaluation {
    id: string;
    procedure_slug: string;
    status: string;
    lead_score: number | null;
    priority: string;
    score_breakdown: ScoreBreakdown | null;
    photos_count: number;
    created_at: string;
    completed_at: string | null;
    patient: Patient | null;
}

interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface EvaluationCollection {
    data: Evaluation[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

interface StatusCounts {
    analyzing: number;
    complete: number;
    contacted: number;
    booked: number;
    abandoned: number;
}

interface Props {
    evaluations: EvaluationCollection;
    filters: {
        status: string;
        search?: string;
        priority?: string;
        min_score?: string;
    };
    statusCounts: StatusCounts;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const PRIORITY_COLORS: Record<string, string> = {
    urgent: 'bg-red-500/15 text-red-400 border-red-500/30',
    high: 'bg-orange-500/15 text-orange-400 border-orange-500/30',
    medium: 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30',
    standard: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
};

const STATUS_COLORS: Record<string, string> = {
    analyzing: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    complete: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
    contacted: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
    booked: 'bg-[#0E9E8E]/15 text-[#0E9E8E] border-[#0E9E8E]/30',
    no_show: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
    not_a_fit: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
    submitted: 'bg-sky-500/15 text-sky-400 border-sky-500/30',
    draft: 'bg-zinc-700/15 text-zinc-500 border-zinc-700/30',
};

function formatProcedure(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(iso));
}

const BREAKDOWN_KEYS: (keyof ScoreBreakdown)[] = [
    'timeline',
    'budget',
    'ai_harmony',
    'photo_quality',
    'concerns',
    'referral',
];

function LeadScoreBar({
    score,
    breakdown,
}: {
    score: number | null;
    breakdown?: ScoreBreakdown | null;
}) {
    const [visible, setVisible] = useState(false);

    if (score === null) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    const pct = Math.min(100, Math.max(0, score));
    const color = pct >= 75 ? '#0E9E8E' : pct >= 50 ? '#60a5fa' : '#9B9B8E';

    return (
        <div
            className="relative inline-flex items-center gap-2"
            onMouseEnter={() => setVisible(true)}
            onMouseLeave={() => setVisible(false)}
        >
            <div className="h-1.5 w-16 overflow-hidden rounded-full bg-white/10">
                <div
                    className="h-full rounded-full transition-all"
                    style={{ width: `${pct}%`, backgroundColor: color }}
                />
            </div>
            <span className="text-xs tabular-nums" style={{ color }}>
                {score}
            </span>

            {breakdown && visible && (
                <div className="absolute bottom-full left-0 z-50 mb-2 w-52 rounded-lg border border-sidebar-border/70 bg-card p-3 shadow-xl">
                    <p className="mb-2 text-xs font-semibold text-foreground">
                        Score Breakdown
                    </p>
                    <div className="space-y-2">
                        {BREAKDOWN_KEYS.map((key) => {
                            const f = breakdown[key];
                            const fpct = Math.round((f.earned / f.max) * 100);
                            const barColor =
                                fpct >= 75
                                    ? '#0E9E8E'
                                    : fpct >= 50
                                      ? '#60a5fa'
                                      : '#9B9B8E';

                            return (
                                <div key={key}>
                                    <div className="mb-0.5 flex items-center justify-between">
                                        <span className="text-[10px] text-muted-foreground">
                                            {f.label}
                                        </span>
                                        <span className="text-[10px] text-foreground tabular-nums">
                                            {f.earned}
                                            <span className="text-muted-foreground">
                                                /{f.max}
                                            </span>
                                        </span>
                                    </div>
                                    <div className="h-0.5 w-full overflow-hidden rounded-full bg-white/10">
                                        <div
                                            className="h-full rounded-full"
                                            style={{
                                                width: `${fpct}%`,
                                                backgroundColor: barColor,
                                            }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <div className="mt-2 flex items-center justify-between border-t border-sidebar-border/50 pt-2">
                        <span className="text-[10px] font-medium tracking-wider text-muted-foreground uppercase">
                            Total
                        </span>
                        <span
                            className="text-xs font-semibold tabular-nums"
                            style={{ color }}
                        >
                            {score}/100
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Status filter tabs ────────────────────────────────────────────────────────

const STATUS_TABS = [
    { key: 'active', label: 'Active' },
    { key: 'abandoned', label: 'Abandoned' },
    { key: 'analyzing', label: 'Analyzing' },
    { key: 'complete', label: 'Complete' },
    { key: 'contacted', label: 'Contacted' },
    { key: 'booked', label: 'Booked' },
] as const;

// ── Page ─────────────────────────────────────────────────────────────────────

const EXPORT_ROLES = new Set(['owner', 'admin', 'coordinator']);

export default function EvaluationsIndex({
    evaluations,
    filters,
    statusCounts,
}: Props) {
    const activeTab = filters.status;
    const { auth } = usePage().props as unknown as {
        auth: { user: { role: string } };
    };
    const canExport = EXPORT_ROLES.has(auth.user.role);

    const updateFilter = useCallback(
        (key: string, value: string | undefined) => {
            router.get(
                index.url({
                    query: { ...filters, [key]: value, page: undefined },
                }),
                {},
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search || '')) {
                updateFilter('search', search || undefined);
            }
        }, 500);

        return () => clearTimeout(timeout);
    }, [search, filters.search, updateFilter]);

    const navigateTab = (status: string) => {
        router.get(
            index.url({ query: { ...filters, status, page: undefined } }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Evaluations" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">
                            Evaluations
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Priority queue · {evaluations.meta.total} total
                        </p>
                    </div>

                    {canExport && (
                        <a
                            href={exportMethod.url({
                                query: { ...filters } as any,
                            })}
                            className="flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition-all hover:border-border active:scale-95"
                        >
                            <Download className="h-4 w-4 text-muted-foreground" />
                            Export CSV
                        </a>
                    )}
                </div>

                {/* Stat pills */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        {
                            label: 'Abandoned',
                            count: statusCounts.abandoned,
                            color: 'text-zinc-500',
                        },
                        {
                            label: 'Analyzing',
                            count: statusCounts.analyzing,
                            color: 'text-blue-400',
                        },
                        {
                            label: 'Complete',
                            count: statusCounts.complete,
                            color: 'text-emerald-400',
                        },
                        {
                            label: 'Contacted',
                            count: statusCounts.contacted,
                            color: 'text-purple-400',
                        },
                        {
                            label: 'Booked',
                            count: statusCounts.booked,
                            color: 'text-[#0E9E8E]',
                        },
                    ].map(({ label, count, color }) => (
                        <div
                            key={label}
                            className="rounded-lg border border-sidebar-border/50 bg-card px-4 py-3"
                        >
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                            <p
                                className={`mt-1 text-2xl font-semibold tabular-nums ${color}`}
                            >
                                {count}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Tab bar */}
                <div className="flex w-fit gap-1 rounded-lg border border-sidebar-border/50 bg-card p-1">
                    {STATUS_TABS.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => navigateTab(key)}
                            className={[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                activeTab === key
                                    ? 'bg-[#0E9E8E] text-[#0A0A0F]'
                                    : 'text-muted-foreground hover:text-foreground',
                            ].join(' ')}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div className="mb-2 flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search patients..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="border-sidebar-border/50 bg-card pl-9"
                        />
                    </div>
                    <div className="flex w-full items-center gap-2 sm:w-auto">
                        <Select
                            value={filters.priority || 'all'}
                            onValueChange={(val) =>
                                updateFilter(
                                    'priority',
                                    val === 'all' ? undefined : val,
                                )
                            }
                        >
                            <SelectTrigger className="w-[140px] border-sidebar-border/50 bg-card">
                                <SelectValue placeholder="Priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All Priorities
                                </SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="standard">
                                    Standard
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.min_score || 'all'}
                            onValueChange={(val) =>
                                updateFilter(
                                    'min_score',
                                    val === 'all' ? undefined : val,
                                )
                            }
                        >
                            <SelectTrigger className="w-[140px] border-sidebar-border/50 bg-card">
                                <SelectValue placeholder="Lead Score" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Scores</SelectItem>
                                <SelectItem value="75">
                                    &ge; 75 (High)
                                </SelectItem>
                                <SelectItem value="50">
                                    &ge; 50 (Med)
                                </SelectItem>
                                <SelectItem value="25">
                                    &ge; 25 (Low)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-sidebar-border/50 bg-card">
                    {evaluations.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-muted-foreground">
                                No evaluations matched your search.
                            </p>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/50">
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Patient
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Procedure
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Priority
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Score
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Photos
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                        Submitted
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/30">
                                {evaluations.data.map((ev) => (
                                    <tr
                                        key={ev.id}
                                        className="group transition-colors hover:bg-muted/20"
                                    >
                                        {/* Patient */}
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-foreground">
                                                {ev.patient?.name ?? (
                                                    <span className="text-muted-foreground italic">
                                                        Unknown
                                                    </span>
                                                )}
                                            </p>
                                            {ev.patient?.email && (
                                                <p className="max-w-[180px] truncate text-xs text-muted-foreground">
                                                    {ev.patient.email}
                                                </p>
                                            )}
                                        </td>

                                        {/* Procedure */}
                                        <td className="px-4 py-3 text-foreground">
                                            {formatProcedure(ev.procedure_slug)}
                                        </td>

                                        {/* Priority */}
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${PRIORITY_COLORS[ev.priority] ?? PRIORITY_COLORS.standard}`}
                                            >
                                                {ev.priority}
                                            </span>
                                        </td>

                                        {/* Lead score */}
                                        <td className="px-4 py-3">
                                            <LeadScoreBar
                                                score={ev.lead_score}
                                                breakdown={ev.score_breakdown}
                                            />
                                        </td>

                                        {/* Status */}
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${STATUS_COLORS[ev.status] ?? ''}`}
                                            >
                                                {ev.status.replace('_', ' ')}
                                            </span>
                                        </td>

                                        {/* Photos */}
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {ev.photos_count ?? 0}
                                        </td>

                                        {/* Date */}
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {ev.completed_at
                                                ? formatDate(ev.completed_at)
                                                : formatDate(ev.created_at)}
                                        </td>

                                        {/* Action */}
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={show.url(ev.id)}
                                                className="text-xs font-medium text-[#0E9E8E] opacity-0 transition-opacity group-hover:opacity-100 hover:underline"
                                            >
                                                Review →
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Pagination */}
                {(evaluations.links.prev || evaluations.links.next) && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            {evaluations.meta.from}–{evaluations.meta.to} of{' '}
                            {evaluations.meta.total}
                        </span>
                        <div className="flex gap-2">
                            {evaluations.links.prev && (
                                <Link
                                    href={evaluations.links.prev}
                                    className="rounded-md border border-sidebar-border/50 px-3 py-1.5 text-foreground transition-colors hover:border-[#0E9E8E]/50"
                                >
                                    ← Previous
                                </Link>
                            )}
                            {evaluations.links.next && (
                                <Link
                                    href={evaluations.links.next}
                                    className="rounded-md border border-sidebar-border/50 px-3 py-1.5 text-foreground transition-colors hover:border-[#0E9E8E]/50"
                                >
                                    Next →
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

EvaluationsIndex.layout = {
    breadcrumbs: [{ title: 'Evaluations', href: index.url() }],
};
