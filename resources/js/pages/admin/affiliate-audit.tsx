import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Activity, DollarSign, TrendingUp, Users } from 'lucide-react';

interface Stats {
    total_partners: number;
    active_partners: number;
    total_payout_volume_cents: number;
    pending_payouts_cents: number;
    released_payouts_cents: number;
}

interface TenantStats {
    id: string;
    name: string;
    affiliate_partners_count: number;
    affiliate_payout_ledgers_sum_amount_cents: number;
}

interface Payout {
    id: string;
    amount_cents: number;
    status: string;
    created_at: string;
    partner: { id: string; name: string } | null;
    tenant: { id: string; name: string } | null;
}

interface Props {
    stats: Stats;
    topTenants: TenantStats[];
    recentPayouts: Payout[];
}

export default function AffiliateAudit({ stats, topTenants, recentPayouts }: Props) {
    const formatCurrency = (cents: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(cents / 100);
    };

    return (
        <div className="space-y-8">
            <Head title="Affiliate Audit - Platform Admin" />

            <Heading
                title="Global Affiliate Audit"
                description="Cross-tenant oversight of partner performance and payout volume"
            />

            {/* High Level Stats */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Partners</CardTitle>
                        <Users className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.total_partners}</div>
                        <p className="text-xs text-muted-foreground">
                            {stats.active_partners} currently active
                        </p>
                    </CardContent>
                </Card>

                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Platform Volume</CardTitle>
                        <DollarSign className="h-4 w-4 text-[#C9A84C]" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{formatCurrency(stats.total_payout_volume_cents)}</div>
                        <p className="text-xs text-muted-foreground">
                            Aggregate payouts accrued
                        </p>
                    </CardContent>
                </Card>

                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Pending Release</CardTitle>
                        <Activity className="h-4 w-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{formatCurrency(stats.pending_payouts_cents)}</div>
                        <p className="text-xs text-muted-foreground">
                            Waiting for clinic approval
                        </p>
                    </CardContent>
                </Card>

                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Released Total</CardTitle>
                        <TrendingUp className="h-4 w-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{formatCurrency(stats.released_payouts_cents)}</div>
                        <p className="text-xs text-muted-foreground">
                            Paid out by clinics
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-8 lg:grid-cols-2">
                {/* Top Tenants Table */}
                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader>
                        <CardTitle className="text-lg">Top Clinics by Affiliate Volume</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {topTenants.length === 0 ? (
                            <div className="rounded-md border border-dashed border-sidebar-border/50 p-6 text-center text-sm text-muted-foreground">
                                No clinic affiliate activity yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/50">
                                            <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Clinic
                                            </th>
                                            <th className="px-4 py-2 text-center text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Partners
                                            </th>
                                            <th className="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Total Payouts
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topTenants.map((tenant) => (
                                            <tr
                                                key={tenant.id}
                                                className="border-b border-sidebar-border/30"
                                            >
                                                <td className="px-4 py-3 font-medium text-foreground">
                                                    {tenant.name}
                                                </td>
                                                <td className="px-4 py-3 text-center text-foreground">
                                                    {tenant.affiliate_partners_count}
                                                </td>
                                                <td className="px-4 py-3 text-right font-semibold text-foreground">
                                                    {formatCurrency(
                                                        tenant.affiliate_payout_ledgers_sum_amount_cents ||
                                                            0,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Payouts Table */}
                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader>
                        <CardTitle className="text-lg">Recent Ledger Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentPayouts.length === 0 ? (
                            <div className="rounded-md border border-dashed border-sidebar-border/50 p-6 text-center text-sm text-muted-foreground">
                                No recent payout ledger activity.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/50">
                                            <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Partner / Clinic
                                            </th>
                                            <th className="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Amount
                                            </th>
                                            <th className="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayouts.map((payout) => (
                                            <tr
                                                key={payout.id}
                                                className="border-b border-sidebar-border/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-foreground">
                                                        {payout.partner?.name ||
                                                            'Unknown'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {payout.tenant?.name}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-right text-foreground">
                                                    {formatCurrency(payout.amount_cents)}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Badge
                                                        variant={
                                                            payout.status === 'released'
                                                                ? 'default'
                                                                : payout.status ===
                                                                    'rejected'
                                                                  ? 'destructive'
                                                                  : 'outline'
                                                        }
                                                        className={`text-[10px] uppercase ${
                                                            payout.status === 'released'
                                                                ? 'bg-emerald-500 text-white hover:bg-emerald-500/90'
                                                                : payout.status ===
                                                                    'approved'
                                                                  ? 'border-[#C9A84C]/60 text-[#C9A84C]'
                                                                  : payout.status ===
                                                                      'pending_hold'
                                                                    ? 'border-amber-500/60 text-amber-400'
                                                                    : ''
                                                        }`}
                                                    >
                                                        {payout.status.replace('_', ' ')}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
