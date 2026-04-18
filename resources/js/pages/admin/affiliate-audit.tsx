import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Users, DollarSign, Activity, TrendingUp } from 'lucide-react';

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
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Clinic</TableHead>
                                    <TableHead className="text-center">Partners</TableHead>
                                    <TableHead className="text-right">Total Payouts</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {topTenants.map((tenant) => (
                                    <TableRow key={tenant.id}>
                                        <TableCell className="font-medium">{tenant.name}</TableCell>
                                        <TableCell className="text-center">{tenant.affiliate_partners_count}</TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {formatCurrency(tenant.affiliate_payout_ledgers_sum_amount_cents || 0)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Recent Payouts Table */}
                <Card className="border-sidebar-border/50 bg-card">
                    <CardHeader>
                        <CardTitle className="text-lg">Recent Ledger Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Partner / Clinic</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="text-right">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentPayouts.map((payout) => (
                                    <TableRow key={payout.id}>
                                        <TableCell>
                                            <div className="font-medium">{payout.partner?.name || 'Unknown'}</div>
                                            <div className="text-[10px] text-muted-foreground">{payout.tenant?.name}</div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(payout.amount_cents)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Badge variant={payout.status === 'released' ? 'success' : 'outline'} className="text-[10px] uppercase">
                                                {payout.status}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
