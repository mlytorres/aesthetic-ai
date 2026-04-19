import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    clear as clearFraudFlag,
    reject as rejectFraudPayout,
} from '@/actions/App/Http/Controllers/Clinic/AffiliateFraudQueueController';

interface FlaggedPayout {
    id: string;
    status: 'pending_hold' | 'approved' | 'rejected' | 'released';
    amount_cents: number;
    currency: string;
    hold_until: string | null;
    released_at: string | null;
    rejection_reason: string | null;
    fraud_flags: string[];
    fraud_risk: 'low' | 'medium' | 'high';
    fraud_reviewed_at: string | null;
    fraud_reviewed_by_user_id: number | null;
    created_at: string;
    partner: {
        id: string;
        name: string;
        handle: string;
        platform: string;
    };
    campaign: {
        id: string;
        name: string;
    };
    evaluation: {
        secure_token: string;
        status: string;
        completed_at: string | null;
    } | null;
}

interface Stats {
    total_flagged: number;
    pending_review: number;
    cleared: number;
    confirmed_fraud: number;
}

interface Props {
    flaggedPayouts: FlaggedPayout[];
    stats: Stats;
    serverNow: string;
}

const RISK_BADGE: Record<string, string> = {
    high: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    low: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
};

const FLAG_LABELS: Record<string, string> = {
    missing_fingerprint: 'Missing fingerprint',
    duplicate_ip_24h: 'Duplicate IP (24h)',
    duplicate_ua_1h: 'Duplicate UA (1h)',
    high_velocity_24h: 'High velocity (24h)',
    high_click_velocity_1h: 'Click burst (1h)',
};

function RiskBadge({ risk }: { risk: string }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${(RISK_BADGE[risk] ?? RISK_BADGE.low)}`}
        >
            {risk}
        </span>
    );
}

function StatCard({
    label,
    value,
    accent,
}: {
    label: string;
    value: number;
    accent?: string;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/50 bg-card p-4">
            <div
                className={`text-2xl font-bold ${(accent ?? 'text-foreground')}`}
            >
                {value}
            </div>
            <div className="text-xs text-muted-foreground">{label}</div>
        </div>
    );
}

export default function AffiliateFraudQueue({
    flaggedPayouts,
    stats,
}: Props) {
    const clearPayout = (payoutId: string): void => {
        router.patch(clearFraudFlag(payoutId).url);
    };

    const rejectPayout = (payoutId: string): void => {
        if (
            !window.confirm(
                'Confirm: reject this payout as fraud? This cannot be undone.',
            )
        ) {
            return;
        }

        router.patch(rejectFraudPayout(payoutId).url);
    };

    const formatCurrency = (cents: number, currency: string): string =>
        new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
        }).format(cents / 100);

    const pendingPayouts = flaggedPayouts.filter(
        (p) => p.fraud_reviewed_at === null,
    );
    const reviewedPayouts = flaggedPayouts.filter(
        (p) => p.fraud_reviewed_at !== null,
    );

    return (
        <>
            <Head title="Fraud Velocity Queue" />

            <div className="space-y-6">
                <Heading
                    title="Fraud Velocity Queue"
                    description="Review flagged payouts before they can be released. Clear legitimate entries or confirm fraud to reject them."
                />

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard
                        label="Total flagged"
                        value={stats.total_flagged}
                    />
                    <StatCard
                        label="Pending review"
                        value={stats.pending_review}
                        accent={
                            stats.pending_review > 0
                                ? 'text-red-600 dark:text-red-400'
                                : undefined
                        }
                    />
                    <StatCard
                        label="Cleared"
                        value={stats.cleared}
                        accent="text-green-600 dark:text-green-400"
                    />
                    <StatCard
                        label="Confirmed fraud"
                        value={stats.confirmed_fraud}
                        accent="text-muted-foreground"
                    />
                </div>

                {/* Pending review */}
                {pendingPayouts.length > 0 && (
                    <div className="rounded-lg border border-sidebar-border/50 bg-card">
                        <div className="border-b border-sidebar-border/50 px-6 py-4">
                            <h2 className="font-semibold text-foreground">
                                Pending Review
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                These payouts cannot be released until reviewed.
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/30 text-muted-foreground">
                                        <th className="px-4 py-3 text-left">
                                            Partner
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Campaign
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Risk
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Flags
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Amount
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pendingPayouts.map((payout) => (
                                        <tr
                                            key={payout.id}
                                            className="border-b border-sidebar-border/20 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {payout.partner?.name ??
                                                        '-'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {payout.partner?.handle ??
                                                        ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {payout.campaign?.name ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <RiskBadge
                                                    risk={payout.fraud_risk}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {payout.fraud_flags.map(
                                                        (flag) => (
                                                            <span
                                                                key={flag}
                                                                className="inline-flex items-center rounded border border-sidebar-border/50 bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                                                title={flag}
                                                            >
                                                                {FLAG_LABELS[
                                                                    flag
                                                                ] ?? flag}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 capitalize text-muted-foreground">
                                                {payout.status.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {formatCurrency(
                                                    payout.amount_cents,
                                                    payout.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            clearPayout(
                                                                payout.id,
                                                            )
                                                        }
                                                    >
                                                        Clear
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            rejectPayout(
                                                                payout.id,
                                                            )
                                                        }
                                                    >
                                                        Confirm Fraud
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Reviewed history */}
                {reviewedPayouts.length > 0 && (
                    <div className="rounded-lg border border-sidebar-border/50 bg-card">
                        <div className="border-b border-sidebar-border/50 px-6 py-4">
                            <h2 className="font-semibold text-foreground">
                                Review History
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/30 text-muted-foreground">
                                        <th className="px-4 py-3 text-left">
                                            Partner
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Campaign
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Risk
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Flags
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Outcome
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Reviewed
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reviewedPayouts.map((payout) => (
                                        <tr
                                            key={payout.id}
                                            className="border-b border-sidebar-border/20"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {payout.partner?.name ??
                                                        '-'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {payout.partner?.handle ??
                                                        ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {payout.campaign?.name ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <RiskBadge
                                                    risk={payout.fraud_risk}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {payout.fraud_flags.map(
                                                        (flag) => (
                                                            <span
                                                                key={flag}
                                                                className="inline-flex items-center rounded border border-sidebar-border/50 bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                                            >
                                                                {FLAG_LABELS[
                                                                    flag
                                                                ] ?? flag}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {payout.status ===
                                                'rejected' ? (
                                                    <Badge variant="destructive">
                                                        Fraud confirmed
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Cleared
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {payout.fraud_reviewed_at
                                                    ? new Date(
                                                          payout.fraud_reviewed_at,
                                                      ).toLocaleString()
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {formatCurrency(
                                                    payout.amount_cents,
                                                    payout.currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {flaggedPayouts.length === 0 && (
                    <div className="rounded-lg border border-sidebar-border/50 bg-card px-6 py-12 text-center text-muted-foreground">
                        No fraud-flagged payouts. The velocity rules have not
                        triggered for any recent conversions.
                    </div>
                )}
            </div>
        </>
    );
}
