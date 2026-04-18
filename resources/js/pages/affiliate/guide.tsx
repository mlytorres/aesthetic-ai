import { Head, Link } from '@inertiajs/react';

export default function AffiliateGuide() {
    return (
        <div className="min-h-screen bg-[#0A0A0F] text-[#F5F0E8] selection:bg-[#C9A84C] selection:text-[#0A0A0F]">
            <Head title="Affiliate Program Guide" />

            <header className="border-b border-[#C9A84C]/30 bg-[#12121A]">
                <div className="container mx-auto flex items-center justify-between px-6 py-4">
                    <span className="text-xl font-bold tracking-widest text-[#C9A84C] uppercase">
                        AestheticAI Partners
                    </span>
                    <Link
                        href="/"
                        className="text-sm font-medium text-[#F5F0E8]/70 hover:text-[#F5F0E8]"
                    >
                        Back to Home
                    </Link>
                </div>
            </header>

            <main className="container mx-auto max-w-4xl space-y-8 px-6 py-12 text-sm leading-relaxed md:text-base">
                <section>
                    <h1 className="text-3xl font-bold tracking-tight">Affiliate Program Guide</h1>
                    <p className="mt-2 text-[#F5F0E8]/75">
                        This guide explains how creators register, receive approved campaign links, and get paid for qualified completed evaluations.
                    </p>
                </section>

                <section className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                    <h2 className="text-xl font-semibold">1. How To Join</h2>
                    <ol className="mt-3 list-decimal space-y-2 pl-5">
                        <li>Receive an invite from a clinic administrator.</li>
                        <li>Open your secure Partner Portal link.</li>
                        <li>Accept the current Partner Terms to activate tracking eligibility.</li>
                        <li>Use only campaign links and creative assets shown in your portal.</li>
                    </ol>
                </section>

                <section className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                    <h2 className="text-xl font-semibold">2. Campaign Rules</h2>
                    <ul className="mt-3 list-disc space-y-2 pl-5">
                        <li>Only platform-approved images, captions, and videos may be used.</li>
                        <li>Include required ad disclosure in your social post.</li>
                        <li>Do not make medical promises or unapproved claims.</li>
                        <li>Do not modify tracking URLs.</li>
                    </ul>
                </section>

                <section className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                    <h2 className="text-xl font-semibold">3. How Tracking Works</h2>
                    <p className="mt-3 text-[#F5F0E8]/80">
                        The system tracks click, intake start, and evaluation completion events. Payout eligibility requires an active partner account, accepted current terms, active campaign, and approved linked asset.
                    </p>
                </section>

                <section className="rounded-xl border border-[#C9A84C]/20 bg-[#12121A] p-6">
                    <h2 className="text-xl font-semibold">4. Payout Lifecycle</h2>
                    <ul className="mt-3 list-disc space-y-2 pl-5">
                        <li><strong>Pending Hold:</strong> evaluation is qualified and waiting through hold window.</li>
                        <li><strong>Approved:</strong> clinic reviewed and approved payout.</li>
                        <li><strong>Released:</strong> payout released by clinic after hold expires.</li>
                    </ul>
                </section>

                <section className="rounded-xl border border-amber-400/30 bg-amber-950/20 p-6">
                    <h2 className="text-xl font-semibold">Data Privacy</h2>
                    <p className="mt-3 text-[#F5F0E8]/80">
                        Partners only see aggregate campaign performance and payout totals. No patient-identifying or medical data is shown in the partner portal.
                    </p>
                </section>
            </main>
        </div>
    );
}
