import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';

interface DayPoint {
    date: string;
    clicks: number;
    conversions: number;
}

interface PartnerRow {
    id: string;
    name: string;
    platform: string;
    handle: string;
    status: string;
    currency: string;
    clicks: number;
    conversions: number;
    conversion_rate: number;
    total_payouts: number;
    released_cents: number;
    pending_cents: number;
}

interface Summary {
    total_clicks: number;
    total_intakes: number;
    total_conversions: number;
    conversion_rate: number;
    pending_cents: number;
    approved_cents: number;
    released_cents: number;
    total_cents: number;
}

interface Props {
    summary: Summary;
    trend: DayPoint[];
    partners: PartnerRow[];
}

function formatCurrency(cents: number, currency = 'USD'): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
}

function SparkLine({ data, valueKey, color }: { data: DayPoint[]; valueKey: 'clicks' | 'conversions'; color: string }) {
    const values = data.map((d) => d[valueKey]);
    const max = Math.max(...values, 1);
    const width = 100;
    const height = 40;
    const points = values
        .map((v, i) => {
            const x = (i / (values.length - 1)) * width;
            const y = height - (v / max) * (height - 4);
            return `${x},${y}`;
        })
        .join(' ');

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="w-full" preserveAspectRatio="none">
            <polyline
                points={points}
                fill="none"
                stroke={color}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function TrendChart({ data }: { data: DayPoint[] }) {
    const maxClicks = Math.max(...data.map((d) => d.clicks), 1);
    const chartH = 120;
    const chartW = 100; // percent-based via viewBox
    const barW = chartW / data.length - 0.5;

    return (
        <svg viewBox={`0 0 ${chartW} ${chartH + 20}`} className="w-full" preserveAspectRatio="none">
            {data.map((d, i) => {
                const x = (i / data.length) * chartW;
                const clickH = (d.clicks / maxClicks) * chartH;
                const convH = (d.conversions / maxClicks) * chartH;
                return (
                    <g key={d.date}>
                        <rect
                            x={x + 0.1}
                            y={chartH - clickH}
                            width={barW * 0.6}
                            height={clickH}
                            fill="#0E9E8E"
                            opacity={0.3}
                            rx={0.5}
                        />
                        <rect
                            x={x + barW * 0.35}
                            y={chartH - convH}
                            width={barW * 0.6}
                            height={convH}
                            fill="#0E9E8E"
                            rx={0.5}
                        />
                    </g>
                );
            })}
        </svg>
    );
}

export default function AffiliateAnalytics({ summary, trend, partners }: Props) {
    const cards = [
        { label: 'Total Clicks', value: summary.total_clicks.toLocaleString(), sub: 'last 30 days from trend', spark: true, sparkKey: 'clicks' as const },
        { label: 'Evaluations Completed', value: summary.total_conversions.toLocaleString(), sub: `${summary.conversion_rate}% conversion rate`, spark: true, sparkKey: 'conversions' as const },
        { label: 'Pending Payouts', value: formatCurrency(summary.pending_cents + summary.approved_cents), sub: 'hold + approved, not yet released', spark: false, sparkKey: 'clicks' as const },
        { label: 'Total Released', value: formatCurrency(summary.released_cents), sub: 'all-time released to partners', spark: false, sparkKey: 'clicks' as const },
    ];

    return (
        <>
            <Head title="Affiliate Analytics" />

            <div className="space-y-6">
                <Heading
                    title="Affiliate Analytics"
                    description="Performance overview for your affiliate program"
                />

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-lg border border-sidebar-border/50 bg-card p-5"
                        >
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {card.label}
                            </p>
                            <p className="mt-1 text-2xl font-bold text-foreground">{card.value}</p>
                            <p className="mt-0.5 text-xs text-muted-foreground">{card.sub}</p>
                            {card.spark && (
                                <div className="mt-3 h-10 opacity-70">
                                    <SparkLine data={trend} valueKey={card.sparkKey} color="#0E9E8E" />
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {/* 30-day trend */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-foreground">30-Day Trend</h3>
                        <div className="flex items-center gap-4 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block h-2.5 w-2.5 rounded-sm bg-[#0E9E8E]/30" />
                                Clicks
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block h-2.5 w-2.5 rounded-sm bg-[#0E9E8E]" />
                                Completions
                            </span>
                        </div>
                    </div>
                    <div className="h-32">
                        <TrendChart data={trend} />
                    </div>
                    <div className="mt-1 flex justify-between text-[10px] text-muted-foreground">
                        <span>{trend[0]?.date}</span>
                        <span>{trend[trend.length - 1]?.date}</span>
                    </div>
                </div>

                {/* Partner performance table */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-sm font-semibold text-foreground">Partner Performance</h3>

                    {partners.length === 0 ? (
                        <div className="rounded-md border border-dashed border-sidebar-border/50 p-10 text-center text-sm text-muted-foreground">
                            No partners yet. Add your first affiliate partner to start tracking performance.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/50">
                                        {['Partner', 'Status', 'Clicks', 'Completions', 'Conv. Rate', 'Released', 'Pending'].map(
                                            (h, i) => (
                                                <th
                                                    key={h}
                                                    className={`px-4 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground ${i >= 2 ? 'text-right' : 'text-left'}`}
                                                >
                                                    {h}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {partners.map((partner) => (
                                        <tr
                                            key={partner.id}
                                            className="border-b border-sidebar-border/30 last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{partner.name}</div>
                                                <div className="text-xs capitalize text-muted-foreground">
                                                    {partner.platform} · {partner.handle}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        partner.status === 'active'
                                                            ? 'default'
                                                            : partner.status === 'paused'
                                                              ? 'secondary'
                                                              : 'destructive'
                                                    }
                                                    className={
                                                        partner.status === 'active'
                                                            ? 'bg-[#0E9E8E] text-white hover:bg-[#0E9E8E]/90'
                                                            : ''
                                                    }
                                                >
                                                    <span className="capitalize">{partner.status}</span>
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right text-foreground">
                                                {partner.clicks.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right text-foreground">
                                                {partner.conversions.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <span
                                                    className={`font-medium ${
                                                        partner.conversion_rate >= 10
                                                            ? 'text-[#0E9E8E]'
                                                            : partner.conversion_rate >= 5
                                                              ? 'text-foreground'
                                                              : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {partner.conversion_rate}%
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {formatCurrency(partner.released_cents, partner.currency)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {partner.pending_cents > 0
                                                    ? formatCurrency(partner.pending_cents, partner.currency)
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
