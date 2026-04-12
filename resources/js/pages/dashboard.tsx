import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { dashboard } from '@/routes';
import { edit as clinicSettingsEdit } from '@/routes/clinic/settings';
import { index as evaluationsIndex, show as evaluationShow } from '@/routes/evaluations';

// ── Types ─────────────────────────────────────────────────────────────────────

interface DashboardStats {
    new_today:        number;
    pending_review:   number;
    booked_this_week: number;
    urgent:           number;
}

interface RecentEvaluation {
    id:             string;
    procedure_slug: string;
    status:         string;
    priority:       string;
    lead_score:     number | null;
    created_at:     string;
    patient_name:   string | null;
}

interface Props {
    stats:              DashboardStats;
    recent_evaluations: RecentEvaluation[];
    clinic_name:        string;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const PRIORITY_COLORS: Record<string, string> = {
    urgent:   'text-red-400',
    high:     'text-orange-400',
    medium:   'text-yellow-400',
    standard: 'text-zinc-400',
};

const STATUS_COLORS: Record<string, string> = {
    analyzing: 'bg-blue-500/15 text-blue-400',
    complete:  'bg-emerald-500/15 text-emerald-400',
    contacted: 'bg-purple-500/15 text-purple-400',
    booked:    'bg-[#C9A84C]/15 text-[#C9A84C]',
    submitted: 'bg-sky-500/15 text-sky-400',
};

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);

    if (mins < 60) {
return `${mins}m ago`;
}

    const hrs = Math.floor(mins / 60);

    if (hrs < 24) {
return `${hrs}h ago`;
}

    return `${Math.floor(hrs / 24)}d ago`;
}

function formatProcedure(slug: string): string {
    return slug.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// ── Stat card ─────────────────────────────────────────────────────────────────

interface StatCardProps {
    label:    string;
    value:    number;
    color:    string;
    href?:    string;
    urgent?:  boolean;
}

function StatCard({ label, value, color, href, urgent }: StatCardProps) {
    const inner = (
        <div className={[
            'rounded-xl border bg-[#111118] px-5 py-4 transition-all',
            urgent && value > 0
                ? 'border-red-500/40 ring-1 ring-red-500/20'
                : 'border-white/10 hover:border-white/20',
        ].join(' ')}>
            <p className="text-xs font-medium text-[#9B9B8E]">{label}</p>
            <p className={`mt-2 text-3xl font-bold tabular-nums ${color}`}>{value}</p>
            {urgent && value > 0 && (
                <p className="mt-1 text-[10px] font-semibold text-red-400 uppercase tracking-widest">
                    Call now
                </p>
            )}
        </div>
    );

    if (href) {
        return <Link href={href}>{inner}</Link>;
    }

    return inner;
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Dashboard({ stats, recent_evaluations, clinic_name }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopyIntakeLink = () => {
        navigator.clipboard.writeText(`https://${window.location.hostname}/intake`);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-[#F5F0E8]">{clinic_name}</h1>
                        <p className="mt-0.5 text-sm text-[#9B9B8E]">
                            {new Intl.DateTimeFormat('en-US', {
                                weekday: 'long',
                                month:   'long',
                                day:     'numeric',
                            }).format(new Date())}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={handleCopyIntakeLink}
                            className="flex items-center gap-2 rounded-lg border border-white/10 bg-[#111118] px-4 py-2 text-sm font-medium text-[#F5F0E8] hover:border-white/20 transition-all active:scale-95"
                        >
                            {copied ? (
                                <>
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                    </svg>
                                    Copied!
                                </>
                            ) : (
                                <>
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-[#9B9B8E]" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                        <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                    </svg>
                                    Copy Intake Link
                                </>
                            )}
                        </button>
                        <Link
                            href={evaluationsIndex.url()}
                            className="rounded-lg border border-[#C9A84C]/30 bg-[#C9A84C]/10 px-4 py-2 text-sm font-medium text-[#C9A84C] hover:bg-[#C9A84C]/20 transition-colors"
                        >
                            View all evaluations →
                        </Link>
                    </div>
                </div>

                {/* Stats grid */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard
                        label="Urgent leads"
                        value={stats.urgent}
                        color="text-red-400"
                        href={evaluationsIndex.url({ query: { status: 'active' } })}
                        urgent
                    />
                    <StatCard
                        label="New today"
                        value={stats.new_today}
                        color="text-[#C9A84C]"
                        href={evaluationsIndex.url()}
                    />
                    <StatCard
                        label="Pending review"
                        value={stats.pending_review}
                        color="text-blue-400"
                        href={evaluationsIndex.url({ query: { status: 'complete' } })}
                    />
                    <StatCard
                        label="Booked this week"
                        value={stats.booked_this_week}
                        color="text-emerald-400"
                        href={evaluationsIndex.url({ query: { status: 'booked' } })}
                    />
                </div>

                {/* Recent evaluations */}
                <div className="flex-1">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-[#F5F0E8]">Recent evaluations</h2>
                        <Link
                            href={evaluationsIndex.url()}
                            className="text-xs text-[#9B9B8E] hover:text-[#C9A84C] transition-colors"
                        >
                            View all
                        </Link>
                    </div>

                    <div className="rounded-xl border border-white/10 bg-[#111118] overflow-hidden">
                        {recent_evaluations.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/5">
                                    <svg className="h-6 w-6 text-[#9B9B8E]" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
                                    </svg>
                                </div>
                                <p className="text-sm font-medium text-[#F5F0E8]">No evaluations yet</p>
                                <p className="mt-1 text-xs text-[#9B9B8E]">
                                    Patients submit via{' '}
                                    <span className="font-mono text-[#C9A84C]">
                                        {window.location.hostname}/intake
                                    </span>
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-white/5">
                                {recent_evaluations.map((ev) => (
                                    <li key={ev.id}>
                                        <Link
                                            href={evaluationShow.url(ev.id)}
                                            className="flex items-center gap-4 px-5 py-3.5 hover:bg-white/[0.03] transition-colors"
                                        >
                                            {/* Priority dot */}
                                            <div className={[
                                                'h-2 w-2 shrink-0 rounded-full',
                                                ev.priority === 'urgent'   ? 'bg-red-400'    :
                                                ev.priority === 'high'     ? 'bg-orange-400' :
                                                ev.priority === 'medium'   ? 'bg-yellow-400' :
                                                'bg-zinc-600',
                                            ].join(' ')} />

                                            {/* Name + procedure */}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-[#F5F0E8] truncate">
                                                    {ev.patient_name ?? 'Unknown Patient'}
                                                </p>
                                                <p className="text-xs text-[#9B9B8E]">
                                                    {formatProcedure(ev.procedure_slug)}
                                                </p>
                                            </div>

                                            {/* Status badge */}
                                            <span className={[
                                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                                STATUS_COLORS[ev.status] ?? 'bg-zinc-500/15 text-zinc-400',
                                            ].join(' ')}>
                                                {ev.status.replace('_', ' ')}
                                            </span>

                                            {/* Score */}
                                            {ev.lead_score !== null && (
                                                <span className={[
                                                    'shrink-0 text-xs font-semibold tabular-nums',
                                                    PRIORITY_COLORS[ev.priority] ?? 'text-zinc-400',
                                                ].join(' ')}>
                                                    {ev.lead_score}
                                                </span>
                                            )}

                                            {/* Time */}
                                            <span className="shrink-0 text-xs text-[#9B9B8E]">
                                                {timeAgo(ev.created_at)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                {/* Quick actions */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Link
                        href={evaluationsIndex.url({ query: { status: 'active' } })}
                        className="flex items-center gap-3 rounded-xl border border-white/10 bg-[#111118] px-4 py-3 hover:border-white/20 hover:bg-white/[0.04] transition-all"
                    >
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#C9A84C]/10">
                            <svg className="h-4 w-4 text-[#C9A84C]" viewBox="0 0 24 24" fill="none">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-[#F5F0E8]">Priority queue</p>
                            <p className="text-xs text-[#9B9B8E]">Active evaluations</p>
                        </div>
                    </Link>

                    <Link
                        href={evaluationsIndex.url({ query: { status: 'complete' } })}
                        className="flex items-center gap-3 rounded-xl border border-white/10 bg-[#111118] px-4 py-3 hover:border-white/20 hover:bg-white/[0.04] transition-all"
                    >
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10">
                            <svg className="h-4 w-4 text-emerald-400" viewBox="0 0 24 24" fill="none">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-[#F5F0E8]">AI complete</p>
                            <p className="text-xs text-[#9B9B8E]">Ready to review</p>
                        </div>
                    </Link>

                    <Link
                        href={clinicSettingsEdit.url()}
                        className="flex items-center gap-3 rounded-xl border border-white/10 bg-[#111118] px-4 py-3 hover:border-white/20 hover:bg-white/[0.04] transition-all"
                    >
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5">
                            <svg className="h-4 w-4 text-[#9B9B8E]" viewBox="0 0 24 24" fill="none">
                                <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke="currentColor" strokeWidth="1.5"/>
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" strokeWidth="1.5"/>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-[#F5F0E8]">Clinic settings</p>
                            <p className="text-xs text-[#9B9B8E]">Configure your clinic</p>
                        </div>
                    </Link>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
