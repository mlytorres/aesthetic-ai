import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, RefreshCw, Webhook } from 'lucide-react';
import {
    index as webhooksIndex,
    retry as webhooksRetry,
} from '@/actions/App/Http/Controllers/Clinic/WebhookDeliveryController';
import { index as integrationsIndex } from '@/actions/App/Http/Controllers/Clinic/IntegrationController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface LastResponse {
    status_code: number;
    body: string;
    latency_ms: number;
    attempted_at: string;
}

interface Delivery {
    id: string;
    event: string;
    status: 'pending' | 'retrying' | 'delivered' | 'failed';
    attempt_count: number;
    last_response: LastResponse | null;
    delivered_at: string | null;
    created_at: string;
    evaluation: {
        id: string;
        procedure: string;
        token: string;
    } | null;
}

interface PaginatedDeliveries {
    data: Delivery[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Stats {
    delivered: number;
    failed: number;
    pending: number;
}

interface Props {
    deliveries: PaginatedDeliveries;
    webhookUrl: string | null;
    stats: Stats;
}

const STATUS_CONFIG = {
    delivered: {
        label: 'Delivered',
        icon: CheckCircle2,
        className: 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
    },
    failed: {
        label: 'Failed',
        icon: AlertCircle,
        className: 'text-red-400 bg-red-400/10 border-red-400/20',
    },
    retrying: {
        label: 'Retrying',
        icon: RefreshCw,
        className: 'text-amber-400 bg-amber-400/10 border-amber-400/20',
    },
    pending: {
        label: 'Pending',
        icon: Clock,
        className: 'text-muted-foreground bg-muted-foreground/10 border-muted-foreground/20',
    },
};

function StatusBadge({ status }: { status: Delivery['status'] }) {
    const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.pending;
    const Icon = config.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                config.className,
            )}
        >
            <Icon className="h-3 w-3" />
            {config.label}
        </span>
    );
}

function formatEvent(event: string): string {
    return event.replace(/\./g, ' › ');
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function WebhooksPage({ deliveries, webhookUrl, stats }: Props) {
    const handleRetry = (delivery: Delivery) => {
        router.post(webhooksRetry.url({ webhookDelivery: delivery.id }));
    };

    return (
        <>
            <Head title="Webhook Deliveries" />

            <div className="space-y-8">
                <Heading
                    title="Webhook Deliveries"
                    description="Monitor outbound webhook events sent to your CRM or integration endpoint."
                />

                {/* No webhook configured warning */}
                {!webhookUrl && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-400/20 bg-amber-400/5 p-4">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                        <div className="text-sm text-foreground">
                            No webhook URL configured.{' '}
                            <a
                                href={integrationsIndex.url()}
                                className="font-medium text-[#0E9E8E] underline-offset-4 hover:underline"
                            >
                                Add one in Integrations
                            </a>{' '}
                            to start receiving events.
                        </div>
                    </div>
                )}

                {/* Stats row */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Delivered', value: stats.delivered, color: 'text-emerald-400' },
                        { label: 'Failed', value: stats.failed, color: 'text-red-400' },
                        { label: 'Pending / Retrying', value: stats.pending, color: 'text-amber-400' },
                    ].map(({ label, value, color }) => (
                        <div
                            key={label}
                            className="rounded-lg border border-sidebar-border/50 bg-card p-5"
                        >
                            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {label}
                            </p>
                            <p className={cn('mt-1 text-2xl font-semibold', color)}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Delivery table */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card">
                    {deliveries.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <Webhook className="h-8 w-8 text-muted-foreground/40" />
                            <p className="text-sm text-muted-foreground">No webhook deliveries yet.</p>
                            <p className="text-xs text-muted-foreground/60">
                                Events are fired when an evaluation completes AI analysis or a
                                coordinator updates the status.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/50">
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Event
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Procedure
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            HTTP
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Attempts
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            Time
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/30">
                                    {deliveries.data.map((delivery) => (
                                        <tr
                                            key={delivery.id}
                                            className="transition-colors hover:bg-background/50"
                                        >
                                            <td className="px-4 py-3 font-mono text-xs text-[#0E9E8E]">
                                                {formatEvent(delivery.event)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={delivery.status} />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {delivery.evaluation?.procedure ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {delivery.last_response ? (
                                                    <span
                                                        className={cn(
                                                            'font-mono text-xs',
                                                            delivery.last_response.status_code >= 200 &&
                                                                delivery.last_response.status_code < 300
                                                                ? 'text-emerald-400'
                                                                : 'text-red-400',
                                                        )}
                                                    >
                                                        {delivery.last_response.status_code}
                                                        <span className="ml-1 text-muted-foreground">
                                                            ({delivery.last_response.latency_ms}ms)
                                                        </span>
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground/40">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {delivery.attempt_count}
                                            </td>
                                            <td className="px-4 py-3 text-right text-xs text-muted-foreground">
                                                {formatTime(delivery.created_at)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {delivery.status === 'failed' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleRetry(delivery)}
                                                        className="h-7 gap-1.5 border-sidebar-border/50 text-xs text-foreground hover:border-[#0E9E8E]/50 hover:text-[#0E9E8E]"
                                                    >
                                                        <RefreshCw className="h-3 w-3" />
                                                        Retry
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {deliveries.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-sidebar-border/50 px-4 py-3">
                            <p className="text-xs text-muted-foreground">
                                Page {deliveries.current_page} of {deliveries.last_page} &middot;{' '}
                                {deliveries.total} total
                            </p>
                            <div className="flex gap-2">
                                {deliveries.links
                                    .filter((l) => l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;')
                                    .slice(0, 7)
                                    .map((link) => (
                                        <Button
                                            key={link.label}
                                            size="sm"
                                            variant={link.active ? 'default' : 'outline'}
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url)}
                                            className={cn(
                                                'h-7 w-7 p-0 text-xs',
                                                link.active &&
                                                    'bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90',
                                            )}
                                        >
                                            {link.label}
                                        </Button>
                                    ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

WebhooksPage.layout = {
    breadcrumbs: [
        { title: 'Integrations', href: integrationsIndex.url() },
        { title: 'Delivery Logs', href: webhooksIndex.url() },
    ],
};
