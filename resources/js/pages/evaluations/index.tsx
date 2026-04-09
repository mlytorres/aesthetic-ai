import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/evaluations';

// ── Types ────────────────────────────────────────────────────────────────────

interface Patient {
    id: string;
    name: string | null;
    email: string | null;
    phone: string | null;
}

interface Evaluation {
    id: string;
    procedure_slug: string;
    status: string;
    lead_score: number | null;
    priority: string;
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
}

interface Props {
    evaluations: EvaluationCollection;
    filters: { status: string };
    statusCounts: StatusCounts;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const PRIORITY_COLORS: Record<string, string> = {
    urgent:   'bg-red-500/15 text-red-400 border-red-500/30',
    high:     'bg-orange-500/15 text-orange-400 border-orange-500/30',
    medium:   'bg-yellow-500/15 text-yellow-400 border-yellow-500/30',
    standard: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
};

const STATUS_COLORS: Record<string, string> = {
    analyzing: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    complete:  'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
    contacted: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
    booked:    'bg-[#C9A84C]/15 text-[#C9A84C] border-[#C9A84C]/30',
    no_show:   'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
    not_a_fit: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
    submitted: 'bg-sky-500/15 text-sky-400 border-sky-500/30',
    draft:     'bg-zinc-700/15 text-zinc-500 border-zinc-700/30',
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

function LeadScoreBar({ score }: { score: number | null }) {
    if (score === null) return <span className="text-[#9B9B8E] text-xs">—</span>;
    const pct = Math.min(100, Math.max(0, score));
    const color = pct >= 75 ? '#C9A84C' : pct >= 50 ? '#60a5fa' : '#9B9B8E';
    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-16 rounded-full bg-white/10 overflow-hidden">
                <div
                    className="h-full rounded-full transition-all"
                    style={{ width: `${pct}%`, backgroundColor: color }}
                />
            </div>
            <span className="text-xs tabular-nums" style={{ color }}>{score}</span>
        </div>
    );
}

// ── Status filter tabs ────────────────────────────────────────────────────────

const STATUS_TABS = [
    { key: 'active',    label: 'Active' },
    { key: 'analyzing', label: 'Analyzing' },
    { key: 'complete',  label: 'Complete' },
    { key: 'contacted', label: 'Contacted' },
    { key: 'booked',    label: 'Booked' },
] as const;

// ── Page ─────────────────────────────────────────────────────────────────────

export default function EvaluationsIndex({ evaluations, filters, statusCounts }: Props) {
    const activeTab = filters.status;

    const navigateTab = (status: string) => {
        router.get(index.url({ status }), {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Evaluations" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-[#F5F0E8]">Evaluations</h1>
                        <p className="mt-0.5 text-sm text-[#9B9B8E]">
                            Priority queue · {evaluations.meta.total} total
                        </p>
                    </div>
                </div>

                {/* Stat pills */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        { label: 'Analyzing',  count: statusCounts.analyzing,  color: 'text-blue-400' },
                        { label: 'Complete',   count: statusCounts.complete,   color: 'text-emerald-400' },
                        { label: 'Contacted',  count: statusCounts.contacted,  color: 'text-purple-400' },
                        { label: 'Booked',     count: statusCounts.booked,     color: 'text-[#C9A84C]' },
                    ].map(({ label, count, color }) => (
                        <div
                            key={label}
                            className="rounded-lg border border-sidebar-border/50 bg-[#111118] px-4 py-3"
                        >
                            <p className="text-xs text-[#9B9B8E]">{label}</p>
                            <p className={`mt-1 text-2xl font-semibold tabular-nums ${color}`}>{count}</p>
                        </div>
                    ))}
                </div>

                {/* Tab bar */}
                <div className="flex gap-1 rounded-lg border border-sidebar-border/50 bg-[#111118] p-1 w-fit">
                    {STATUS_TABS.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => navigateTab(key)}
                            className={[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                activeTab === key
                                    ? 'bg-[#C9A84C] text-[#0A0A0F]'
                                    : 'text-[#9B9B8E] hover:text-[#F5F0E8]',
                            ].join(' ')}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {/* Table */}
                <div className="rounded-xl border border-sidebar-border/50 bg-[#111118] overflow-hidden">
                    {evaluations.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-[#9B9B8E]">No evaluations in this queue.</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/50">
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Patient</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Procedure</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Priority</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Score</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Photos</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#9B9B8E]">Submitted</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/30">
                                {evaluations.data.map((ev) => (
                                    <tr
                                        key={ev.id}
                                        className="group hover:bg-white/[0.03] transition-colors"
                                    >
                                        {/* Patient */}
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-[#F5F0E8]">
                                                {ev.patient?.name ?? <span className="italic text-[#9B9B8E]">Unknown</span>}
                                            </p>
                                            {ev.patient?.email && (
                                                <p className="text-xs text-[#9B9B8E] truncate max-w-[180px]">
                                                    {ev.patient.email}
                                                </p>
                                            )}
                                        </td>

                                        {/* Procedure */}
                                        <td className="px-4 py-3 text-[#F5F0E8]">
                                            {formatProcedure(ev.procedure_slug)}
                                        </td>

                                        {/* Priority */}
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${PRIORITY_COLORS[ev.priority] ?? PRIORITY_COLORS.standard}`}>
                                                {ev.priority}
                                            </span>
                                        </td>

                                        {/* Lead score */}
                                        <td className="px-4 py-3">
                                            <LeadScoreBar score={ev.lead_score} />
                                        </td>

                                        {/* Status */}
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${STATUS_COLORS[ev.status] ?? ''}`}>
                                                {ev.status.replace('_', ' ')}
                                            </span>
                                        </td>

                                        {/* Photos */}
                                        <td className="px-4 py-3 text-[#9B9B8E] tabular-nums">
                                            {ev.photos_count ?? 0}
                                        </td>

                                        {/* Date */}
                                        <td className="px-4 py-3 text-[#9B9B8E] whitespace-nowrap">
                                            {ev.completed_at
                                                ? formatDate(ev.completed_at)
                                                : formatDate(ev.created_at)}
                                        </td>

                                        {/* Action */}
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={show.url(ev.id)}
                                                className="text-xs font-medium text-[#C9A84C] opacity-0 group-hover:opacity-100 transition-opacity hover:underline"
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
                    <div className="flex items-center justify-between text-sm text-[#9B9B8E]">
                        <span>
                            {evaluations.meta.from}–{evaluations.meta.to} of {evaluations.meta.total}
                        </span>
                        <div className="flex gap-2">
                            {evaluations.links.prev && (
                                <Link
                                    href={evaluations.links.prev}
                                    className="rounded-md border border-sidebar-border/50 px-3 py-1.5 text-[#F5F0E8] hover:border-[#C9A84C]/50 transition-colors"
                                >
                                    ← Previous
                                </Link>
                            )}
                            {evaluations.links.next && (
                                <Link
                                    href={evaluations.links.next}
                                    className="rounded-md border border-sidebar-border/50 px-3 py-1.5 text-[#F5F0E8] hover:border-[#C9A84C]/50 transition-colors"
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
    breadcrumbs: [
        { title: 'Evaluations', href: index.url() },
    ],
};
