import { Head } from '@inertiajs/react';
import { Deferred } from '@inertiajs/react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface WeekPoint {
    week:  string;
    count: number;
}

interface StatusRow {
    status: string;
    label:  string;
    count:  number;
}

interface ScoreBucket {
    bucket: string;
    count:  number;
}

interface PriorityRow {
    priority: string;
    label:    string;
    count:    number;
}

interface FunnelStep {
    step:  number;
    label: string;
    count: number;
    rate:  number; // % of patients who reached at least this step
}

interface Props {
    weeklyVolume:      WeekPoint[];
    statusFunnel:      StatusRow[];
    scoreDistrib:      ScoreBucket[];
    priorityBreakdown: PriorityRow[];
    avgTimeToContact:  number | null;
    intakeFunnel:      FunnelStep[];
}

// ── Colour maps ───────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, string> = {
    submitted:  'bg-sky-500',
    analyzing:  'bg-blue-500',
    complete:   'bg-emerald-500',
    contacted:  'bg-purple-500',
    booked:     'bg-[#C9A84C]',
    no_show:    'bg-zinc-500',
    not_a_fit:  'bg-zinc-600',
    failed:     'bg-red-600',
};

const PRIORITY_COLORS: Record<string, string> = {
    urgent:   'bg-red-500',
    high:     'bg-orange-500',
    medium:   'bg-yellow-500',
    standard: 'bg-zinc-500',
};

// ── Skeleton ──────────────────────────────────────────────────────────────────

function Skeleton({ className = '' }: { className?: string }) {
    return <div className={`animate-pulse rounded bg-zinc-800 ${className}`} />;
}

// ── Bar chart (pure CSS) ──────────────────────────────────────────────────────

function BarChart({
    data,
    labelKey,
    valueKey,
    colorFn,
}: {
    data:     Record<string, unknown>[];
    labelKey: string;
    valueKey: string;
    colorFn:  (row: Record<string, unknown>) => string;
}) {
    const max = Math.max(...data.map((d) => Number(d[valueKey]) || 0), 1);

    return (
        <div className="flex h-40 items-end gap-2">
            {data.map((row, i) => {
                const value  = Number(row[valueKey]) || 0;
                const height = Math.round((value / max) * 100);

                return (
                    <div key={i} className="group relative flex flex-1 flex-col items-center gap-1">
                        {/* Tooltip */}
                        <span className="pointer-events-none absolute -top-7 rounded bg-zinc-700 px-2 py-0.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100">
                            {value}
                        </span>
                        <div
                            className={`w-full rounded-t transition-all ${colorFn(row)}`}
                            style={{ height: `${height}%`, minHeight: value > 0 ? '4px' : '0' }}
                        />
                        <span className="max-w-full truncate text-center text-[10px] text-zinc-400">
                            {String(row[labelKey])}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Line/area sparkline for weekly volume ─────────────────────────────────────

function SparkLine({ data }: { data: WeekPoint[] }) {
    const max    = Math.max(...data.map((d) => d.count), 1);
    const w      = 100;
    const h      = 60;
    const pts    = data.map((d, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = h - (d.count / max) * (h - 4);
        return `${x},${y}`;
    });
    const polyline = pts.join(' ');
    const area     = `0,${h} ${polyline} ${w},${h}`;

    return (
        <div className="flex flex-col gap-2">
            <svg
                viewBox={`0 0 ${w} ${h}`}
                className="h-16 w-full"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#C9A84C" stopOpacity="0.4" />
                        <stop offset="100%" stopColor="#C9A84C" stopOpacity="0.0" />
                    </linearGradient>
                </defs>
                <polygon points={area} fill="url(#sparkGrad)" />
                <polyline
                    points={polyline}
                    fill="none"
                    stroke="#C9A84C"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {data.map((d, i) => {
                    const [x, y] = pts[i].split(',').map(Number);
                    return (
                        <circle key={i} cx={x} cy={y} r="2" fill="#C9A84C" />
                    );
                })}
            </svg>
            {/* X-axis labels — show first, mid, last */}
            <div className="flex justify-between text-[10px] text-zinc-500">
                <span>{data[0]?.week}</span>
                <span>{data[Math.floor(data.length / 2)]?.week}</span>
                <span>{data[data.length - 1]?.week}</span>
            </div>
        </div>
    );
}

// ── Intake funnel chart ───────────────────────────────────────────────────────

function IntakeFunnelChart({ data }: { data: FunnelStep[] }) {
    if (data.length === 0) {
        return <p className="text-sm text-zinc-500">No intake data yet.</p>;
    }

    return (
        <div className="flex flex-col gap-3">
            {data.map((step, i) => {
                const dropOff = i > 0 ? data[i - 1].rate - step.rate : 0;
                return (
                    <div key={step.step} className="flex flex-col gap-1">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-zinc-200">{step.label}</span>
                            <span className="tabular-nums text-zinc-400">
                                {step.count.toLocaleString()}
                                {i > 0 && dropOff > 0 && (
                                    <span className="ml-2 text-red-400">−{dropOff.toFixed(1)}%</span>
                                )}
                            </span>
                        </div>
                        <div className="h-6 overflow-hidden rounded bg-zinc-800">
                            <div
                                className="h-full rounded bg-[#C9A84C] transition-all"
                                style={{ width: `${step.rate}%`, opacity: 0.6 + (step.rate / 100) * 0.4 }}
                            />
                        </div>
                        <span className="text-right text-[10px] text-zinc-500">{step.rate}% completion</span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Stat card ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, sub }: { label: string; value: string | number; sub?: string }) {
    return (
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <p className="text-sm text-zinc-400">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-white">{value}</p>
            {sub && <p className="mt-1 text-xs text-zinc-500">{sub}</p>}
        </div>
    );
}

// ── Section card ─────────────────────────────────────────────────────────────

function Card({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <h2 className="mb-4 text-sm font-medium text-zinc-300">{title}</h2>
            {children}
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Analytics({
    weeklyVolume,
    statusFunnel,
    scoreDistrib,
    priorityBreakdown,
    avgTimeToContact,
    intakeFunnel,
}: Props) {
    // Aggregate totals from deferred data (may be undefined while loading)
    const totalEvaluations = statusFunnel?.reduce((sum, r) => sum + r.count, 0) ?? null;
    const bookedCount      = statusFunnel?.find((r) => r.status === 'booked')?.count ?? null;

    return (
        <>
            <Head title="Analytics" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold text-white">Analytics</h1>
                    <p className="mt-1 text-sm text-zinc-400">Performance overview across all evaluations</p>
                </div>

                {/* ── KPI row ── */}
                <Deferred data={['weeklyVolume', 'statusFunnel', 'avgTimeToContact']}
                    fallback={
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-24" />)}
                        </div>
                    }
                >
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <StatCard
                            label="Total Evaluations"
                            value={totalEvaluations ?? '—'}
                            sub="All time, all statuses"
                        />
                        <StatCard
                            label="Booked"
                            value={bookedCount ?? '—'}
                            sub="Converted to appointment"
                        />
                        <StatCard
                            label="This Week"
                            value={weeklyVolume?.[weeklyVolume.length - 1]?.count ?? '—'}
                            sub="New submissions"
                        />
                        <StatCard
                            label="Avg. Time to Contact"
                            value={avgTimeToContact != null ? `${avgTimeToContact}h` : '—'}
                            sub="From submission to first contact"
                        />
                    </div>
                </Deferred>

                {/* ── Charts row ── */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    {/* Weekly volume */}
                    <Card title="Weekly Submission Volume (last 8 weeks)">
                        <Deferred data="weeklyVolume" fallback={<Skeleton className="h-24" />}>
                            {weeklyVolume && weeklyVolume.length > 1
                                ? <SparkLine data={weeklyVolume} />
                                : <p className="text-sm text-zinc-500">Not enough data yet.</p>
                            }
                        </Deferred>
                    </Card>

                    {/* Status funnel */}
                    <Card title="Evaluation Status Funnel">
                        <Deferred data="statusFunnel" fallback={<Skeleton className="h-40" />}>
                            {statusFunnel && (
                                <BarChart
                                    data={statusFunnel as unknown as Record<string, unknown>[]}
                                    labelKey="label"
                                    valueKey="count"
                                    colorFn={(row) => STATUS_COLORS[String(row.status)] ?? 'bg-zinc-500'}
                                />
                            )}
                        </Deferred>
                    </Card>

                    {/* Lead score distribution */}
                    <Card title="Lead Score Distribution">
                        <Deferred data="scoreDistrib" fallback={<Skeleton className="h-40" />}>
                            {scoreDistrib && (
                                <BarChart
                                    data={scoreDistrib as unknown as Record<string, unknown>[]}
                                    labelKey="bucket"
                                    valueKey="count"
                                    colorFn={() => 'bg-[#C9A84C]'}
                                />
                            )}
                        </Deferred>
                    </Card>

                    {/* Priority breakdown */}
                    <Card title="Priority Breakdown">
                        <Deferred data="priorityBreakdown" fallback={<Skeleton className="h-40" />}>
                            {priorityBreakdown && (
                                <BarChart
                                    data={priorityBreakdown as unknown as Record<string, unknown>[]}
                                    labelKey="label"
                                    valueKey="count"
                                    colorFn={(row) => PRIORITY_COLORS[String(row.priority)] ?? 'bg-zinc-500'}
                                />
                            )}
                        </Deferred>
                    </Card>
                </div>

                {/* ── Intake funnel ── */}
                <Card title="Intake Wizard Drop-off Funnel">
                    <Deferred data="intakeFunnel" fallback={<Skeleton className="h-40" />}>
                        {intakeFunnel && <IntakeFunnelChart data={intakeFunnel} />}
                    </Deferred>
                </Card>

                {/* ── Status detail table ── */}
                <Card title="Status Breakdown">
                    <Deferred data="statusFunnel" fallback={<Skeleton className="h-32" />}>
                        {statusFunnel && (
                            <div className="overflow-hidden rounded-lg border border-zinc-800">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-zinc-800 bg-zinc-800/50">
                                            <th className="px-4 py-2 text-left font-medium text-zinc-400">Status</th>
                                            <th className="px-4 py-2 text-right font-medium text-zinc-400">Count</th>
                                            <th className="px-4 py-2 text-right font-medium text-zinc-400">Share</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statusFunnel.map((row) => {
                                            const share = totalEvaluations
                                                ? Math.round((row.count / totalEvaluations) * 100)
                                                : 0;
                                            return (
                                                <tr key={row.status} className="border-b border-zinc-800/50 last:border-0">
                                                    <td className="px-4 py-3 text-zinc-200">
                                                        <span className={`mr-2 inline-block h-2 w-2 rounded-full ${STATUS_COLORS[row.status] ?? 'bg-zinc-500'}`} />
                                                        {row.label}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-zinc-300">{row.count}</td>
                                                    <td className="px-4 py-3 text-right text-zinc-500">{share}%</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Deferred>
                </Card>
            </div>
        </>
    );
}
