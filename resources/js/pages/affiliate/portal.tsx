import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { acceptTerms } from '@/actions/App/Http/Controllers/Affiliate/AffiliatePortalController';

interface Partner {
    id: string;
    name: string;
    handle: string;
    status: string;
    platform: string;
}

interface TermsInfo {
    current_version: string;
    accepted_current: boolean;
    summary: string[];
}

interface Metrics {
    clicks: number;
    intake_starts: number;
    completed_evaluations: number;
    pending_payout_cents: number;
    released_payout_cents: number;
}

interface Asset {
    id: string;
    name: string;
    type: 'image' | 'video' | 'caption';
    url: string;
}

interface LinkItem {
    id: string;
    status: string;
    click_count: number;
    last_clicked_at: string | null;
    campaign_name: string | null;
    asset_name: string | null;
    tracking_url: string;
    short_tracking_url: string | null;
}

interface Props {
    partner: Partner;
    portal_token: string;
    terms: TermsInfo;
    metrics: Metrics;
    links: LinkItem[];
    media_kit: Asset[];
}

export default function AffiliatePortal({
    partner,
    portal_token,
    terms,
    metrics,
    links,
    media_kit,
}: Props) {
    const acceptCurrentTerms = (): void => {
        router.post(
            acceptTerms({
                partner: partner.id,
                token: portal_token,
            }).url,
        );
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        // We could add a toast here if sonner is available
    };

    return (
        <>
            <Head title="Affiliate Portal" />

            <main className="min-h-screen bg-[#0A0A0F] px-4 py-8 text-[#F5F0E8]">
                <div className="mx-auto max-w-5xl space-y-8">
                    {/* Header */}
                    <header className="flex flex-col gap-4 rounded-2xl border border-[#C9A84C]/30 bg-[#12121A] p-6 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Welcome, {partner.name}
                            </h1>
                            <p className="mt-1 text-sm text-[#F5F0E8]/70">
                                {partner.platform} {partner.handle}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span
                                className={`h-2 w-2 rounded-full ${partner.status === 'active' ? 'bg-emerald-500' : 'bg-amber-500'}`}
                            />
                            <span className="text-sm font-medium capitalize">
                                {partner.status} Partner
                            </span>
                        </div>
                    </header>

                    {!terms.accepted_current ? (
                        <section className="rounded-2xl border border-amber-400/40 bg-amber-950/20 p-6 shadow-2xl shadow-amber-900/10">
                            <h2 className="text-lg font-semibold">
                                Terms Acceptance Required
                            </h2>
                            <p className="mt-2 text-sm text-[#F5F0E8]/75">
                                You must accept terms version {terms.current_version}{' '}
                                before using campaign links and assets.
                            </p>
                            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-[#F5F0E8]/80">
                                {terms.summary.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                            <Button
                                type="button"
                                className="mt-4 bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/90"
                                onClick={acceptCurrentTerms}
                            >
                                Accept Partner Terms
                            </Button>
                        </section>
                    ) : (
                        <>
                            {/* Stats Grid */}
                            <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-4">
                                    <div className="text-xs font-medium uppercase tracking-wider text-[#F5F0E8]/40">
                                        Clicks
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold">
                                        {metrics.clicks.toLocaleString()}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-4">
                                    <div className="text-xs font-medium uppercase tracking-wider text-[#F5F0E8]/40">
                                        Intake Starts
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold">
                                        {metrics.intake_starts.toLocaleString()}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-4">
                                    <div className="text-xs font-medium uppercase tracking-wider text-[#F5F0E8]/40">
                                        Evaluations
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold">
                                        {metrics.completed_evaluations.toLocaleString()}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-4">
                                    <div className="text-xs font-medium uppercase tracking-wider text-[#F5F0E8]/40">
                                        Pending Payout
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold text-emerald-400">
                                        $
                                        {(metrics.pending_payout_cents / 100).toFixed(
                                            2,
                                        )}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-4">
                                    <div className="text-xs font-medium uppercase tracking-wider text-[#F5F0E8]/40">
                                        Total Released
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold opacity-70">
                                        $
                                        {(metrics.released_payout_cents / 100).toFixed(
                                            2,
                                        )}
                                    </div>
                                </div>
                            </section>

                            <div className="grid gap-8 lg:grid-cols-3">
                                {/* Left Column: Tracking Links */}
                                <div className="lg:col-span-1 space-y-6">
                                    <section className="rounded-2xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                                        <h2 className="mb-4 text-lg font-semibold flex items-center gap-2">
                                            <span className="h-1.5 w-1.5 rounded-full bg-[#C9A84C]" />
                                            Active Links
                                        </h2>
                                        <div className="space-y-4">
                                            {links.map((link) => (
                                                <div
                                                    key={link.id}
                                                    className="space-y-3 rounded-xl border border-[#C9A84C]/10 bg-black/20 p-4"
                                                >
                                                    <div className="flex items-center justify-between font-medium text-sm">
                                                        <span>
                                                            {link.campaign_name ??
                                                                'Campaign'}
                                                        </span>
                                                        <span className="text-[10px] uppercase tracking-tighter opacity-50">
                                                            {link.asset_name ??
                                                                'Generic'}
                                                        </span>
                                                    </div>

                                                    {/* Short Social Link */}
                                                    <div className="space-y-1.5 cursor-pointer group" onClick={() => copyToClipboard(link.short_tracking_url ?? link.tracking_url)}>
                                                        <label className="text-[10px] uppercase font-bold text-[#C9A84C]/70">Social Link (Recommended)</label>
                                                        <div className="flex items-center gap-2 rounded bg-[#0A0A0F] border border-[#C9A84C]/20 p-2 text-xs transition-colors group-hover:border-[#C9A84C]/50">
                                                            <span className="flex-1 truncate font-mono text-emerald-400/90">{link.short_tracking_url ?? link.tracking_url}</span>
                                                            <span className="text-[8px] uppercase font-bold px-1.5 py-0.5 rounded bg-[#C9A84C] text-[#0A0A0F]">Copy</span>
                                                        </div>
                                                    </div>

                                                    <div className="flex items-center justify-between text-[10px] text-[#F5F0E8]/40">
                                                        <span>Clicks: {link.click_count}</span>
                                                        <span>Status: {link.status}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </section>
                                </div>

                                {/* Right Column: Media Kit */}
                                <div className="lg:col-span-2 space-y-6">
                                    <section className="rounded-2xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                                        <h2 className="mb-4 text-lg font-semibold flex items-center gap-2">
                                            <span className="h-1.5 w-1.5 rounded-full bg-[#C9A84C]" />
                                            Media Kit
                                        </h2>
                                        <p className="mb-6 text-sm text-[#F5F0E8]/60">
                                            Ready-to-use materials for your social platforms. Use these assets to drive traffic to your social links.
                                        </p>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {media_kit.map((asset) => (
                                                <div key={asset.id} className="group relative overflow-hidden rounded-xl border border-[#C9A84C]/10 bg-black/20 transition-all hover:border-[#C9A84C]/30">
                                                    {asset.type === 'image' && (
                                                        <div className="aspect-[4/3] w-full overflow-hidden bg-[#0A0A0F]">
                                                            <img
                                                                src={asset.url}
                                                                alt={asset.name}
                                                                className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                                            />
                                                        </div>
                                                    )}

                                                    {asset.type === 'caption' && (
                                                        <div className="flex aspect-[4/3] w-full items-center justify-center bg-[#0A0A0F] p-6 text-center italic text-sm text-[#F5F0E8]/70">
                                                            "{asset.name}"
                                                        </div>
                                                    )}

                                                    <div className="p-3">
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-xs font-medium truncate pr-2">{asset.name}</span>
                                                            <span className="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-black/40 text-[#C9A84C]">
                                                                {asset.type}
                                                            </span>
                                                        </div>

                                                        <div className="mt-3 grid grid-cols-2 gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="h-7 text-[10px] font-bold uppercase transition-colors hover:bg-[#C9A84C] hover:text-[#0A0A0F]"
                                                                variant="outline"
                                                                onClick={() => window.open(asset.url, '_blank')}
                                                            >
                                                                View
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="h-7 text-[10px] font-bold uppercase bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#C9A84C]/80"
                                                                onClick={() => {
                                                                    if (asset.type === 'caption') {
                                                                        copyToClipboard(asset.url);
                                                                    } else {
                                                                       // For images we trigger download
                                                                       const link = document.createElement('a');
                                                                       link.href = asset.url;
                                                                       link.download = `${asset.name}.${asset.type === 'image' ? 'jpg' : 'mp4'}`;
                                                                       link.click();
                                                                    }
                                                                }}
                                                            >
                                                                {asset.type === 'caption' ? 'Copy Text' : 'Download'}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                            {media_kit.length === 0 && (
                                                <div className="col-span-full border border-dashed border-[#C9A84C]/20 rounded-xl p-12 text-center text-sm text-[#F5F0E8]/40 italic">
                                                    No media assets available for your active campaigns.
                                                </div>
                                            )}
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
