import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import clinic from '@/routes/clinic';

interface Props {
    appUrl: string;
}

/**
 * Staff-only reference: SymetriHealth ↔ CRM (no third-party product names).
 */
export default function ClinicApiDocs({ appUrl }: Props) {
    const base = appUrl.replace(/\/$/, '');

    return (
        <>
            <Head title="CRM API documentation" />
            <div className="mx-auto max-w-3xl space-y-8">
                <Heading
                    title="CRM API documentation"
                    description="How your CRM authenticates to this app, calls the REST API, and verifies outbound webhooks."
                />

                <section className="rounded-lg border border-sidebar-border/50 bg-card p-6 text-sm text-muted-foreground">
                    <h3 className="mb-2 font-semibold text-foreground">
                        Inbound authentication (CRM → this app)
                    </h3>
                    <p className="leading-relaxed">
                        Send your issued API token as{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs text-foreground">
                            X-Api-Key
                        </code>{' '}
                        with every server request. For REST v1, also send{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs text-foreground">
                            X-Clinic-ID
                        </code>{' '}
                        (your clinic UUID). Generate and revoke tokens under{' '}
                        <strong className="text-foreground">Integrations</strong>.
                    </p>
                    <p className="mt-3 text-xs">
                        The same token value may alternatively be sent as{' '}
                        <code className="rounded bg-muted px-1 font-mono">Authorization: Bearer …</code>{' '}
                        for legacy clients; prefer <code className="font-mono">X-Api-Key</code> for new integrations.
                    </p>
                </section>

                <section className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground">
                        REST API v1
                    </h3>
                    <div className="space-y-2 font-mono text-xs text-muted-foreground">
                        <p>
                            <span className="rounded bg-teal-500/15 px-2 py-0.5 font-semibold text-teal-700 dark:text-teal-400">
                                GET
                            </span>{' '}
                            <span className="break-all text-foreground">
                                {base}/api/v1/evaluations/&lt;evaluation_token&gt;
                            </span>
                        </p>
                        <p className="text-sm leading-relaxed">
                            Returns evaluation details for the given secure token (used after your automation receives a
                            webhook).
                        </p>
                    </div>
                </section>

                <section className="rounded-lg border border-sidebar-border/50 bg-card p-6 text-sm text-muted-foreground">
                    <h3 className="mb-2 font-semibold text-foreground">
                        Outbound webhooks (this app → your CRM)
                    </h3>
                    <p className="leading-relaxed">
                        Events POST to the webhook URL configured under Integrations. Verify each request using{' '}
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs text-foreground">
                            X-SymetriHealth-Signature
                        </code>{' '}
                        — format{' '}
                        <code className="font-mono text-xs">sha256=&lt;hex_hmac&gt;</code> where the HMAC-SHA256 is computed
                        over the <strong className="text-foreground">raw JSON body</strong> with your configured webhook
                        secret. Use constant-time comparison. Event name is in{' '}
                        <code className="rounded bg-muted px-1 font-mono text-xs">X-SymetriHealth-Event</code>.
                    </p>
                </section>
            </div>
        </>
    );
}

ClinicApiDocs.layout = {
    breadcrumbs: [
        {
            title: 'CRM API',
            href: clinic.apiDocs.url(),
        },
    ],
};
