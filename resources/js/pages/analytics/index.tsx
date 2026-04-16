import { Head } from '@inertiajs/react';
import { Deferred } from '@inertiajs/react';
import { analytics } from '@/routes';

// ── Types ─────────────────────────────────────────────────────────────────────

interface WeekPoint {
    week: string;
    count: number;
}

interface StatusRow {
    status: string;
    label: string;
    count: number;
}

interface ScoreBucket {
    bucket: string;
    count: number;
}

interface PriorityRow {
    priority: string;
    label: string;
    count: number;
}

interface FunnelStep {
    step: number;
    label: string;
    count: number;
    rate: number; // % of patients who reached at least this step
}

interface MonthMetric {
    current: number;
    previous: number;
    delta: number;
}

interface MonthMetricFloat {
    current: number | null;
    previous: number | null;
    delta: number | null;
}

interface MonthOverMonth {
    current_month: string;
    previous_month: string;
    evaluations: MonthMetric;
    avg_score: MonthMetricFloat;
    booked: MonthMetric;
}

interface ProcedureRow {
    procedure: string;
    label: string;
    count: number;
    booked: number;
    booking_rate: number;
}

interface ScoreVsBookingRow {
    bucket: string;
    total: number;
    booked: number;
    booking_rate: number;
}

interface Props {
    weeklyVolume: WeekPoint[];
    statusFunnel: StatusRow[];
    scoreDistrib: ScoreBucket[];
    priorityBreakdown: PriorityRow[];
    avgTimeToContact: number | null;
    intakeFunnel: FunnelStep[];
    monthOverMonth: MonthOverMonth;
    procedureMix: ProcedureRow[];
    scoreVsBooking: ScoreVsBookingRow[];
}

// ── Colour maps ───────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, string> = {
    submitted: 'bg-sky-500',
    analyzing: 'bg-blue-500',
    complete: 'bg-emerald-500',
    contacted: 'bg-purple-500',
    booked: 'bg-[#0E9E8E]',
    no_show: 'bg-zinc-500',
    not_a_fit: 'bg-zinc-600',
    failed: 'bg-red-600',
};

const PRIORITY_COLORS: Record<string, string> = {
    urgent: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-yellow-500',
    standard: 'bg-zinc-500',
};

const PROCEDURE_COLORS = [
    'bg-[#0E9E8E]',
    'bg-purple-500',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-rose-500',
];

// ── Skeleton ──────────────────────────────────────────────────────────────────

function Skeleton({ className = '' }: { className?: string }) {
    return <div className={`animate-pulse rounded bg-zinc-800 ${className}`} />;
}

// ── Delta badge ───────────────────────────────────────────────────────────────

function Delta({
    value,
    suffix = '',
}: {
    value: number | null;
    suffix?: string;
}) {
    if (value === null) {
        return <span className="text-zinc-500">—</span>;
    }

    if (value === 0) {
        return <span className="text-zinc-400">±0{suffix}</span>;
    }

    const positive = value > 0;

    return (
        <span className={positive ? 'text-emerald-400' : 'text-red-400'}>
            {positive ? '+' : ''}
            {value}
            {suffix}
        </span>
    );
}

// ── Bar chart (pure CSS) ──────────────────────────────────────────────────────

function BarChart({
    data,
    labelKey,
    valueKey,
    colorFn,
}: {
    data: Record<string, unknown>[];
    labelKey: string;
    valueKey: string;
    colorFn: (row: Record<string, unknown>, i: number) => string;
}) {
    const max = Math.max(...data.map((d) => Number(d[valueKey]) || 0), 1);

    return (
        <div className="flex h-40 items-end gap-2">
            {data.map((row, i) => {
                const value = Number(row[valueKey]) || 0;
                const height = Math.round((value / max) * 100);

                return (
                    <div
                        key={i}
                        className="group relative flex flex-1 flex-col items-center gap-1"
                    >
                        {/* Tooltip */}
                        <span className="pointer-events-none absolute -top-7 rounded bg-zinc-700 px-2 py-0.5 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100">
                            {value}
                        </span>
                        <div
                            className={`w-full rounded-t transition-all ${colorFn(row, i)}`}
                            style={{
                                height: `${height}%`,
                                minHeight: value > 0 ? '4px' : '0',
                            }}
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

// ── Dual bar chart (count + booking rate) ────────────────────────────────────

function DualBarChart({
    data,
}: {
    data: ProcedureRow[] | ScoreVsBookingRow[];
}) {
    type Row = {
        label: string;
        count: number;
        booking_rate: number;
        color: string;
    };

    const rows: Row[] = (data as (ProcedureRow | ScoreVsBookingRow)[]).map(
        (d, i) => ({
            label: 'label' in d ? d.label : d.bucket,
            count: 'count' in d ? d.count : d.total,
            booking_rate: d.booking_rate,
            color: PROCEDURE_COLORS[i % PROCEDURE_COLORS.length],
        }),
    );

    const maxCount = Math.max(...rows.map((r) => r.count), 1);

    return (
        <div className="flex flex-col gap-3">
            {rows.map((row, i) => (
                <div key={i} className="flex flex-col gap-1">
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-medium text-zinc-200">
                            {row.label}
                        </span>
                        <span className="text-zinc-400 tabular-nums">
                            {row.count} evals
                            <span className="ml-3 text-[#0E9E8E]">
                                {row.booking_rate}% booked
                            </span>
                        </span>
                    </div>
                    {/* Volume bar */}
                    <div className="h-4 overflow-hidden rounded bg-zinc-800">
                        <div
                            className={`h-full rounded transition-all ${row.color}`}
                            style={{
                                width: `${Math.round((row.count / maxCount) * 100)}%`,
                                opacity: 0.75,
                            }}
                        />
                    </div>
                    {/* Booking rate bar */}
                    <div className="h-2 overflow-hidden rounded bg-zinc-800">
                        <div
                            className="h-full rounded bg-[#0E9E8E] transition-all"
                            style={{
                                width: `${row.booking_rate}%`,
                                opacity: 0.6,
                            }}
                        />
                    </div>
                </div>
            ))}
            <div className="mt-1 flex gap-4 text-[10px] text-zinc-500">
                <span className="flex items-center gap-1.5">
                    <span className="inline-block h-2 w-3 rounded bg-[#0E9E8E] opacity-75" />{' '}
                    Volume
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="inline-block h-2 w-3 rounded bg-[#0E9E8E] opacity-60" />{' '}
                    Booking rate
                </span>
            </div>
        </div>
    );
}

// ── Line/area sparkline for weekly volume ─────────────────────────────────────

function SparkLine({ data }: { data: WeekPoint[] }) {
    const max = Math.max(...data.map((d) => d.count), 1);
    const w = 100;
    const h = 60;
    const pts = data.map((d, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = h - (d.count / max) * (h - 4);

        return `${x},${y}`;
    });
    const polyline = pts.join(' ');
    const area = `0,${h} ${polyline} ${w},${h}`;

    return (
        <div className="flex flex-col gap-2">
            <svg
                viewBox={`0 0 ${w} ${h}`}
                className="h-16 w-full"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="#0E9E8E"
                            stopOpacity="0.4"
                        />
                        <stop
                            offset="100%"
                            stopColor="#0E9E8E"
                            stopOpacity="0.0"
                        />
                    </linearGradient>
                </defs>
                <polygon points={area} fill="url(#sparkGrad)" />
                <polyline
                    points={polyline}
                    fill="none"
                    stroke="#0E9E8E"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {data.map((d, i) => {
                    const [x, y] = pts[i].split(',').map(Number);

                    return (
                        <circle key={i} cx={x} cy={y} r="2" fill="#0E9E8E" />
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
                            <span className="font-medium text-zinc-200">
                                {step.label}
                            </span>
                            <span className="text-zinc-400 tabular-nums">
                                {step.count.toLocaleString()}
                                {i > 0 && dropOff > 0 && (
                                    <span className="ml-2 text-red-400">
                                        −{dropOff.toFixed(1)}%
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="h-6 overflow-hidden rounded bg-zinc-800">
                            <div
                                className="h-full rounded bg-[#0E9E8E] transition-all"
                                style={{
                                    width: `${step.rate}%`,
                                    opacity: 0.6 + (step.rate / 100) * 0.4,
                                }}
                            />
                        </div>
                        <span className="text-right text-[10px] text-zinc-500">
                            {step.rate}% completion
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Stat card ─────────────────────────────────────────────────────────────────

function StatCard({
    label,
    value,
    sub,
}: {
    label: string;
    value: string | number;
    sub?: string;
}) {
    return (
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <p className="text-sm text-zinc-400">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-white">{value}</p>
            {sub && <p className="mt-1 text-xs text-zinc-500">{sub}</p>}
        </div>
    );
}

// ── Month-over-month stat card ────────────────────────────────────────────────

function MoMCard({
    label,
    current,
    delta,
    suffix = '',
    sub,
}: {
    label: string;
    current: number | null;
    delta: number | null;
    suffix?: string;
    sub?: string;
}) {
    return (
        <div className="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <p className="text-sm text-zinc-400">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-white">
                {current !== null ? `${current}${suffix}` : '—'}
            </p>
            <p className="mt-1 text-xs">
                <Delta value={delta} suffix={suffix} />
                {sub && <span className="ml-1.5 text-zinc-500">{sub}</span>}
            </p>
        </div>
    );
}

// ── Section card ─────────────────────────────────────────────────────────────

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
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
    monthOverMonth,
    procedureMix,
    scoreVsBooking,
}: Props) {
    // Aggregate totals from deferred data (may be undefined while loading)
    const totalEvaluations =
        statusFunnel?.reduce((sum, r) => sum + r.count, 0) ?? null;
    const bookedCount =
        statusFunnel?.find((r) => r.status === 'booked')?.count ?? null;

    return (
        <>
            <Head title="Analytics" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold text-white">
                        Analytics
                    </h1>
                    <p className="mt-1 text-sm text-zinc-400">
                        Performance overview across all evaluations
                    </p>
                </div>

                {/* ── KPI row ── */}
                <Deferred
                    data={['weeklyVolume', 'statusFunnel', 'avgTimeToContact']}
                    fallback={
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {[...Array(4)].map((_, i) => (
                                <Skeleton key={i} className="h-24" />
                            ))}
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
                            value={
                                weeklyVolume?.[weeklyVolume.length - 1]
                                    ?.count ?? '—'
                            }
                            sub="New submissions"
                        />
                        <StatCard
                            label="Avg. Time to Contact"
                            value={
                                avgTimeToContact != null
                                    ? `${avgTimeToContact}h`
                                    : '—'
                            }
                            sub="From submission to first contact"
                        />
                    </div>
                </Deferred>

                {/* ── Month-over-month KPI row ── */}
                <Deferred
                    data="monthOverMonth"
                    fallback={
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-5 w-40" />
                            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                                {[...Array(3)].map((_, i) => (
                                    <Skeleton key={i} className="h-24" />
                                ))}
                            </div>
                        </div>
                    }
                >
                    {monthOverMonth && (
                        <div className="flex flex-col gap-3">
                            <p className="text-xs text-zinc-500">
                                Month over month —{' '}
                                <span className="text-zinc-300">
                                    {monthOverMonth.current_month}
                                </span>{' '}
                                vs {monthOverMonth.previous_month}
                            </p>
                            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                                <MoMCard
                                    label="Evaluations This Month"
                                    current={monthOverMonth.evaluations.current}
                                    delta={monthOverMonth.evaluations.delta}
                                    sub="vs last month"
                                />
                                <MoMCard
                                    label="Avg. Lead Score"
                                    current={monthOverMonth.avg_score.current}
                                    delta={monthOverMonth.avg_score.delta}
                                    sub="vs last month"
                                />
                                <MoMCard
                                    label="Bookings This Month"
                                    current={monthOverMonth.booked.current}
                                    delta={monthOverMonth.booked.delta}
                                    sub="vs last month"
                                />
                            </div>
                        </div>
                    )}
                </Deferred>

                {/* ── Charts row ── */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Weekly volume */}
                    <Card title="Weekly Submission Volume (last 8 weeks)">
                        <Deferred
                            data="weeklyVolume"
                            fallback={<Skeleton className="h-24" />}
                        >
                            {weeklyVolume && weeklyVolume.length > 1 ? (
                                <SparkLine data={weeklyVolume} />
                            ) : (
                                <p className="text-sm text-zinc-500">
                                    Not enough data yet.
                                </p>
                            )}
                        </Deferred>
                    </Card>

                    {/* Status funnel */}
                    <Card title="Evaluation Status Funnel">
                        <Deferred
                            data="statusFunnel"
                            fallback={<Skeleton className="h-40" />}
                        >
                            {statusFunnel && (
                                <BarChart
                                    data={
                                        statusFunnel as unknown as Record<
                                            string,
                                            unknown
                                        >[]
                                    }
                                    labelKey="label"
                                    valueKey="count"
                                    colorFn={(row) =>
                                        STATUS_COLORS[String(row.status)] ??
                                        'bg-zinc-500'
                                    }
                                />
                            )}
                        </Deferred>
                    </Card>

                    {/* Lead score distribution */}
                    <Card title="Lead Score Distribution">
                        <Deferred
                            data="scoreDistrib"
                            fallback={<Skeleton className="h-40" />}
                        >
                            {scoreDistrib && (
                                <BarChart
                                    data={
                                        scoreDistrib as unknown as Record<
                                            string,
                                            unknown
                                        >[]
                                    }
                                    labelKey="bucket"
                                    valueKey="count"
                                    colorFn={() => 'bg-[#0E9E8E]'}
                                />
                            )}
                        </Deferred>
                    </Card>

                    {/* Priority breakdown */}
                    <Card title="Priority Breakdown">
                        <Deferred
                            data="priorityBreakdown"
                            fallback={<Skeleton className="h-40" />}
                        >
                            {priorityBreakdown && (
                                <BarChart
                                    data={
                                        priorityBreakdown as unknown as Record<
                                            string,
                                            unknown
                                        >[]
                                    }
                                    labelKey="label"
                                    valueKey="count"
                                    colorFn={(row) =>
                                        PRIORITY_COLORS[String(row.priority)] ??
                                        'bg-zinc-500'
                                    }
                                />
                            )}
                        </Deferred>
                    </Card>

                    {/* Procedure mix */}
                    <Card title="Procedure Mix & Booking Rate">
                        <Deferred
                            data="procedureMix"
                            fallback={<Skeleton className="h-40" />}
                        >
                            {procedureMix && procedureMix.length > 0 ? (
                                <DualBarChart data={procedureMix} />
                            ) : (
                                <p className="text-sm text-zinc-500">
                                    No procedure data yet.
                                </p>
                            )}
                        </Deferred>
                    </Card>

                    {/* Score vs booking rate */}
                    <Card title="Lead Score vs Booking Rate">
                        <Deferred
                            data="scoreVsBooking"
                            fallback={<Skeleton className="h-40" />}
                        >
                            {scoreVsBooking &&
                            scoreVsBooking.some((r) => r.total > 0) ? (
                                <DualBarChart data={scoreVsBooking} />
                            ) : (
                                <p className="text-sm text-zinc-500">
                                    Not enough conversion data yet.
                                </p>
                            )}
                        </Deferred>
                    </Card>
                </div>

                {/* ── Intake funnel ── */}
                <Card title="Intake Wizard Drop-off Funnel">
                    <Deferred
                        data="intakeFunnel"
                        fallback={<Skeleton className="h-40" />}
                    >
                        {intakeFunnel && (
                            <IntakeFunnelChart data={intakeFunnel} />
                        )}
                    </Deferred>
                </Card>

                {/* ── Status detail table ── */}
                <Card title="Status Breakdown">
                    <Deferred
                        data="statusFunnel"
                        fallback={<Skeleton className="h-32" />}
                    >
                        {statusFunnel && (
                            <div className="overflow-hidden rounded-lg border border-zinc-800">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-zinc-800 bg-zinc-800/50">
                                            <th className="px-4 py-2 text-left font-medium text-zinc-400">
                                                Status
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium text-zinc-400">
                                                Count
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium text-zinc-400">
                                                Share
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statusFunnel.map((row) => {
                                            const share = totalEvaluations
                                                ? Math.round(
                                                      (row.count /
                                                          totalEvaluations) *
                                                          100,
                                                  )
                                                : 0;

                                            return (
                                                <tr
                                                    key={row.status}
                                                    className="border-b border-zinc-800/50 last:border-0"
                                                >
                                                    <td className="px-4 py-3 text-zinc-200">
                                                        <span
                                                            className={`mr-2 inline-block h-2 w-2 rounded-full ${STATUS_COLORS[row.status] ?? 'bg-zinc-500'}`}
                                                        />
                                                        {row.label}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-zinc-300">
                                                        {row.count}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-zinc-500">
                                                        {share}%
                                                    </td>
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

Analytics.layout = {
    breadcrumbs: [{ title: 'Analytics', href: analytics.url() }],
};
