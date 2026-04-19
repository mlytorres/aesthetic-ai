import { Head, Link } from '@inertiajs/react';
import { Award, CheckCircle2, ChevronRight, Gift, Lock, ShieldCheck, TrendingUp, Users, Zap } from 'lucide-react';
import { login, register } from '@/routes';

export default function AffiliateProgram() {
    return (
        <div className="relative min-h-screen overflow-hidden bg-background font-sans text-foreground selection:bg-[#0E9E8E]/30">
            <Head title="Affiliate Partner Program | SymetriHealth" />

            {/* Background glow — same as welcome.tsx */}
            <div className="pointer-events-none absolute inset-x-0 top-0 h-[500px] bg-gradient-to-b from-[#0E9E8E]/15 via-[#0E9E8E]/5 to-transparent" />
            <div className="bg-gradient-radial pointer-events-none absolute -top-[500px] left-1/2 h-[1000px] w-[1000px] -translate-x-1/2 rounded-full from-[#0E9E8E]/10 to-transparent blur-3xl" />

            {/* Navigation */}
            <nav className="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
                <Link href="/" className="flex items-center gap-3">
                    <div className="shrink-0 rounded-xl bg-white p-1.5 shadow-sm">
                        <img
                            src="/logo.png"
                            alt="SymetriHealth Logo"
                            className="h-9 w-auto"
                        />
                    </div>
                    <span className="hidden text-xl font-bold tracking-widest text-[#0E9E8E] uppercase sm:block">
                        SymetriHealth
                    </span>
                </Link>
                <div className="flex items-center gap-6">
                    <a href="#benefits" className="hidden text-sm font-medium text-muted-foreground transition-colors hover:text-foreground md:block">
                        Benefits
                    </a>
                    <a href="#hipaa" className="hidden text-sm font-medium text-muted-foreground transition-colors hover:text-foreground md:block">
                        HIPAA Compliance
                    </a>
                    <Link
                        href={login()}
                        className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Clinic Login
                    </Link>
                    <Link
                        href={register()}
                        className="rounded-lg bg-[#0E9E8E] px-5 py-2.5 text-sm font-semibold text-[#0A0A0F] shadow-[0_0_15px_rgba(14,158,142,0.2)] transition-colors hover:bg-[#2DD4BF]"
                    >
                        Start Free Trial
                    </Link>
                </div>
            </nav>

            {/* Hero */}
            <main className="relative z-10 mx-auto max-w-7xl px-6 pt-24 pb-20 text-center lg:pt-36">
                <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-4 py-1.5 text-xs font-semibold tracking-wide text-[#0E9E8E] backdrop-blur-md">
                    <Award className="h-3.5 w-3.5" />
                    Invitation-Only Elite Network
                </div>

                <h1 className="mb-8 text-5xl font-bold tracking-tight lg:text-7xl">
                    The standard for <br className="hidden lg:block" />
                    <span className="bg-gradient-to-r from-[#0E9E8E] via-[#2DD4BF] to-[#0E9E8E] bg-clip-text text-transparent">
                        aesthetic partners.
                    </span>
                </h1>

                <p className="mx-auto mb-12 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                    Join the most trusted circle in medical aesthetics. We connect world-class influencers with premier plastic surgery clinics through a secure, medically-vetted, HIPAA-compliant ecosystem.
                </p>

                <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a href="#cta">
                        <button className="flex h-14 items-center gap-2 rounded-xl bg-gradient-to-r from-[#0E9E8E] to-[#2DD4BF] px-8 font-bold text-[#0A0A0F] shadow-[0_0_30px_rgba(14,158,142,0.3)] transition-all hover:scale-[1.02] hover:opacity-90">
                            Request an Invitation
                            <ChevronRight className="h-5 w-5" />
                        </button>
                    </a>
                    <a href="#benefits" className="flex h-14 items-center rounded-xl border border-border bg-card px-8 font-semibold transition-all hover:border-[#0E9E8E]/50 hover:bg-muted/30">
                        See Partner Benefits
                    </a>
                </div>
            </main>

            {/* Stats Bar */}
            <section className="relative z-10 mx-auto max-w-5xl px-6 pb-20">
                <div className="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border bg-border md:grid-cols-4">
                    {[
                        { value: 'HIPAA', label: 'Compliant by design' },
                        { value: '100%', label: 'Patient data anonymity' },
                        { value: 'AES-256', label: 'Bank-grade encryption' },
                        { value: 'Real-time', label: 'Earnings dashboard' },
                    ].map((stat, i) => (
                        <div key={i} className="bg-card px-6 py-8 text-center">
                            <div className="mb-1 text-2xl font-bold text-[#0E9E8E]">{stat.value}</div>
                            <div className="text-xs leading-tight text-muted-foreground">{stat.label}</div>
                        </div>
                    ))}
                </div>
            </section>

            {/* Benefits */}
            <section id="benefits" className="relative z-10 mx-auto max-w-7xl px-6 pb-24">
                <div className="mb-14 text-center">
                    <h2 className="mb-4 text-3xl font-bold lg:text-4xl">High-altitude partner benefits</h2>
                    <p className="mx-auto max-w-xl text-muted-foreground">
                        We don't just provide links. We provide a platform for clinical excellence and professional authority.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {[
                        {
                            icon: Zap,
                            title: 'Surgeon-Vetted Assets',
                            desc: 'Access a private library of surgeon-approved clinical results, ultra-HD videos, and expert-level captions that build your authority and credibility.',
                        },
                        {
                            icon: Gift,
                            title: 'Elite Clinic Access',
                            desc: 'Connect with the top 1% of aesthetic practices. Our invitation-only model ensures you only promote the height of clinical safety and excellence.',
                        },
                        {
                            icon: TrendingUp,
                            title: 'High-Yield Rewards',
                            desc: 'Participate in a rewards structure designed for professional influencers. Track your real-time earnings in a clean, high-performance dashboard.',
                        },
                        {
                            icon: ShieldCheck,
                            title: 'HIPAA-Safe Referrals',
                            desc: 'Every referral link is cryptographically signed and fully HIPAA-compliant. Your followers\' privacy is protected at every step of the patient journey.',
                        },
                        {
                            icon: Users,
                            title: 'Dedicated Partner Support',
                            desc: 'A dedicated partner success team is available to help you with content strategy, compliance questions, and campaign performance optimization.',
                        },
                        {
                            icon: Lock,
                            title: 'Full Compliance Auditing',
                            desc: 'Every interaction is logged and auditable. You\'ll always have a clear record of referrals, conversions, and earnings for complete transparency.',
                        },
                    ].map((feat, i) => (
                        <div
                            key={i}
                            className="group rounded-2xl border border-border/50 bg-card p-8 transition-all hover:-translate-y-1 hover:border-[#0E9E8E]/40 hover:bg-muted/20"
                        >
                            <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-xl border border-border bg-muted/50 transition-all group-hover:border-[#0E9E8E]/30 group-hover:bg-[#0E9E8E]/10">
                                <feat.icon className="h-7 w-7 text-[#0E9E8E]" />
                            </div>
                            <h3 className="mb-3 text-lg font-semibold">{feat.title}</h3>
                            <p className="text-sm leading-relaxed text-muted-foreground">{feat.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* HIPAA Section */}
            <section id="hipaa" className="relative z-10 mx-auto max-w-7xl px-6 pb-24">
                <div className="overflow-hidden rounded-3xl border border-border bg-card">
                    <div className="grid lg:grid-cols-2">
                        {/* Left */}
                        <div className="flex flex-col justify-center p-10 lg:p-14">
                            <div className="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-[#0E9E8E]/20 bg-[#0E9E8E]/10 px-3 py-1 text-xs font-semibold tracking-wide text-[#0E9E8E]">
                                <ShieldCheck className="h-3.5 w-3.5" />
                                Professional Integrity
                            </div>
                            <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                                Your reputation,{' '}
                                <span className="text-[#0E9E8E]">obsessively protected.</span>
                            </h2>
                            <p className="mb-8 leading-relaxed text-muted-foreground">
                                In medical aesthetics, trust is your most valuable asset. SymetriHealth is built on a 100% HIPAA-compliant foundation — ensuring that while you earn, your followers' most sensitive data remains locked in a vault. No leaks, no scandals, just pure professional growth.
                            </p>
                            <a href="#cta">
                                <button className="inline-flex w-fit items-center gap-2 rounded-xl bg-[#0E9E8E] px-6 py-3 font-bold text-[#0A0A0F] transition-colors hover:bg-[#2DD4BF]">
                                    Request an Invitation
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </a>
                        </div>

                        {/* Right: checklist */}
                        <div className="border-t border-border bg-muted/20 p-10 lg:border-t-0 lg:border-l lg:p-14">
                            <ul className="space-y-5">
                                {[
                                    { title: 'Total Patient Anonymity', detail: 'Referred patients are never identified to the partner. All PHI is handled exclusively by the clinic under HIPAA safeguards.' },
                                    { title: 'Bank-Grade Encryption', detail: 'All data encrypted in transit (TLS 1.3) and at rest (AES-256). PHI never travels unencrypted.' },
                                    { title: 'Surgeon-Approved Content', detail: 'Every asset in the partner library is reviewed and approved by licensed surgeons before distribution.' },
                                    { title: 'Full Compliance Auditing', detail: 'Every referral click, conversion, and payout is logged with timestamp and IP — immutable and transparent.' },
                                    { title: 'Cryptographic Link Signing', detail: 'Every affiliate link is cryptographically signed and tamper-proof. Attribution is always accurate.' },
                                    { title: 'Invitation-Only Access', detail: 'The partner network is curated. Access is granted exclusively by participating clinics, not open to the public.' },
                                ].map((item, i) => (
                                    <li key={i} className="flex gap-4">
                                        <div className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[#0E9E8E]/30 bg-[#0E9E8E]/15">
                                            <CheckCircle2 className="h-3 w-3 text-[#0E9E8E]" />
                                        </div>
                                        <div>
                                            <p className="mb-0.5 text-sm font-semibold">{item.title}</p>
                                            <p className="text-xs leading-relaxed text-muted-foreground">{item.detail}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section id="cta" className="relative z-10 mx-auto max-w-3xl px-6 pb-32">
                <div className="relative overflow-hidden rounded-3xl border border-[#0E9E8E]/20 bg-card p-10 shadow-[0_0_50px_rgba(14,158,142,0.05)]">
                    <div className="absolute top-0 right-0 h-64 w-64 rounded-full bg-[#0E9E8E]/5 blur-3xl pointer-events-none" />
                    <div className="relative z-10 text-center">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-4 py-1.5 text-xs font-semibold tracking-wide text-[#0E9E8E]">
                            <Lock className="h-3.5 w-3.5" />
                            By Invitation Only
                        </div>
                        <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                            Ready to join the network?
                        </h2>
                        <p className="mx-auto mb-10 max-w-lg text-muted-foreground">
                            SymetriHealth's partner program is a curated ecosystem. Membership is only available via invitation from a participating clinic. Reach out to your clinic to get started.
                        </p>
                        <a href="mailto:partners@symetrihealth.com?subject=Partner Program Inquiry">
                            <button className="h-14 rounded-xl bg-gradient-to-r from-[#0E9E8E] to-[#2DD4BF] px-10 font-bold text-[#0A0A0F] shadow-[0_0_30px_rgba(14,158,142,0.3)] transition-all hover:scale-[1.02] hover:opacity-90">
                                Inquire with Your Clinic
                            </button>
                        </a>
                    </div>
                </div>
            </section>

            {/* Footer — matches welcome.tsx */}
            <footer className="border-t border-dashed border-border py-8">
                <div className="mx-auto flex max-w-7xl flex-col items-center gap-4 px-6 text-xs text-muted-foreground">
                    <div className="flex justify-center gap-6">
                        <Link href="/" className="transition-colors hover:text-foreground">
                            Home
                        </Link>
                        <Link href="/legal/terms" className="transition-colors hover:text-foreground">
                            Terms of Service
                        </Link>
                        <Link href="/legal/privacy" className="transition-colors hover:text-foreground">
                            Privacy Policy
                        </Link>
                    </div>
                    <div>&copy; 2026 SymetriHealth Platform. All rights reserved.</div>
                </div>
            </footer>
        </div>
    );
}
