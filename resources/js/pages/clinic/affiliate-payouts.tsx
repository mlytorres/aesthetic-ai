import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    release as releasePayout,
    review as reviewPayout,
} from '@/actions/App/Http/Controllers/Clinic/AffiliatePayoutController';

interface Payout {
    id: string;
    status: 'pending_hold' | 'approved' | 'rejected' | 'released';
    amount_cents: number;
    currency: string;
    hold_until: string | null;
    released_at: string | null;
    rejection_reason: string | null;
    partner: {
        name: string;
        handle: string;
    };
    campaign: {
        name: string;
    };
    evaluation: {
        secure_token: string;
        status: string;
        completed_at: string | null;
    } | null;
}

interface Props {
    payouts: Payout[];
    serverNow: string;
}

export default function AffiliatePayouts({ payouts, serverNow }: Props) {
    const now = new Date(serverNow);

    const approve = (payoutId: string): void => {
        router.patch(reviewPayout(payoutId).url, {
            status: 'approved',
        });
    };

    const reject = (payoutId: string): void => {
        const reason = window.prompt('Provide rejection reason:');

        if (!reason) {
            return;
        }

        router.patch(reviewPayout(payoutId).url, {
            status: 'rejected',
            rejection_reason: reason,
        });
    };

    const release = (payoutId: string): void => {
        router.patch(releasePayout(payoutId).url);
    };

    return (
        <>
            <Head title="Affiliate Payouts" />

            <div className="space-y-6">
                <Heading
                    title="Affiliate Payouts"
                    description="Review, approve, reject, and release affiliate payouts after hold windows"
                />

                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/50">
                                    <th className="px-4 py-2 text-left">Partner</th>
                                    <th className="px-4 py-2 text-left">Campaign</th>
                                    <th className="px-4 py-2 text-left">Status</th>
                                    <th className="px-4 py-2 text-left">Hold Until</th>
                                    <th className="px-4 py-2 text-right">Amount</th>
                                    <th className="px-4 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payouts.map((payout) => {
                                    const holdExpired =
                                        payout.hold_until === null ||
                                        new Date(payout.hold_until) <= now;

                                    return (
                                        <tr
                                            key={payout.id}
                                            className="border-b border-sidebar-border/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {payout.partner?.name ?? '-'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {payout.partner?.handle ?? ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {payout.campaign?.name ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="capitalize">
                                                    {payout.status.replace('_', ' ')}
                                                </span>
                                                {payout.rejection_reason && (
                                                    <div className="text-xs text-red-400">
                                                        {payout.rejection_reason}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {payout.hold_until
                                                    ? new Date(
                                                          payout.hold_until,
                                                      ).toLocaleString()
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {payout.currency}{' '}
                                                {(payout.amount_cents / 100).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    {payout.status ===
                                                        'pending_hold' && (
                                                        <>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    approve(
                                                                        payout.id,
                                                                    )
                                                                }
                                                            >
                                                                Approve
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    reject(
                                                                        payout.id,
                                                                    )
                                                                }
                                                            >
                                                                Reject
                                                            </Button>
                                                        </>
                                                    )}

                                                    {payout.status ===
                                                        'approved' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                release(
                                                                    payout.id,
                                                                )
                                                            }
                                                            disabled={!holdExpired}
                                                        >
                                                            Release
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
