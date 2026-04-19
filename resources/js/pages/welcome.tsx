import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { TrustBadges } from '@/components/trust-badges';
import type { FormEvent } from 'react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    const {
        data,
        setData,
        post,
        processing,
        errors,
        recentlySuccessful,
        reset,
    } = useForm({
        name: '',
        clinic_name: '',
        email: '',
        website_url: '',
    });

    const submitRequest = (e: FormEvent) => {
        e.preventDefault();
        post('/access-requests', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Welcome | SymetriHealth" />

            <div className="relative min-h-screen overflow-hidden bg-background font-sans text-foreground selection:bg-[#0E9E8E]/30">
                {/* Background glow */}
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[500px] bg-gradient-to-b from-[#0E9E8E]/15 via-[#0E9E8E]/5 to-transparent" />
                <div className="bg-gradient-radial pointer-events-none absolute -top-[500px] left-1/2 h-[1000px] w-[1000px] -translate-x-1/2 rounded-full from-[#0E9E8E]/10 to-transparent blur-3xl" />

                {/* Navigation */}
                <nav className="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-3">
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
                    </div>
                    <div className="flex items-center gap-6">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="text-sm font-medium transition-colors hover:text-[#0E9E8E]"
                            >
                                Go to Dashboard
                            </Link>
                        ) : (
                            <>
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
                            </>
                        )}
                    </div>
                </nav>

                {/* Hero */}
                <main className="relative z-10 mx-auto max-w-7xl px-6 pt-24 pb-20 text-center lg:pt-36">
                    <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-4 py-1.5 text-xs font-semibold tracking-wide text-[#0E9E8E] backdrop-blur-md">
                        <span className="h-2 w-2 animate-pulse rounded-full bg-[#0E9E8E]" />
                        HIPAA-Compliant Platform
                    </div>

                    <h1 className="mb-8 text-5xl font-bold tracking-tight lg:text-7xl">
                        The future of <br className="hidden lg:block" />
                        <span className="bg-gradient-to-r from-[#0E9E8E] via-[#2DD4BF] to-[#0E9E8E] bg-clip-text text-transparent">
                            aesthetic consultations.
                        </span>
                    </h1>

                    <p className="mx-auto mb-12 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                        Automate your patient intake pipeline with real-time AI
                        facial analysis, instant photo quality validation, and
                        advanced clinical lead scoring. Built securely for
                        modern plastic surgery clinics.
                    </p>

                    <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href={register()}
                            className="w-full rounded-xl bg-gradient-to-r from-[#0E9E8E] to-[#2DD4BF] px-8 py-4 text-center font-bold text-[#0A0A0F] shadow-[0_0_30px_rgba(14,158,142,0.3)] transition-all hover:scale-[1.02] hover:opacity-90 sm:w-auto"
                        >
                            Start Free Trial — 14 Days Free
                        </Link>
                        <a
                            href="#request-demo"
                            className="w-full rounded-xl border border-border bg-card px-8 py-4 text-center font-semibold transition-all hover:border-[#0E9E8E]/50 hover:bg-muted/30 sm:w-auto"
                        >
                            Request a Demo
                        </a>
                    </div>
                </main>

                {/* Stats Bar */}
                <section className="relative z-10 mx-auto max-w-5xl px-6 pb-20">
                    <div className="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border bg-border md:grid-cols-4">
                        {[
                            {
                                value: '< 4 min',
                                label: 'Average intake completion',
                            },
                            { value: '100%', label: 'PHI access audit-logged' },
                            { value: '15 min', label: 'Auto session timeout' },
                            { value: 'HIPAA', label: 'Compliant by design' },
                        ].map((stat, i) => (
                            <div
                                key={i}
                                className="bg-card px-6 py-8 text-center"
                            >
                                <div className="mb-1 text-3xl font-bold text-[#0E9E8E]">
                                    {stat.value}
                                </div>
                                <div className="text-xs leading-tight text-muted-foreground">
                                    {stat.label}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* How It Works */}
                <section className="relative z-10 mx-auto max-w-7xl px-6 pb-24">
                    <div className="mb-14 text-center">
                        <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                            From patient click to coordinator review
                        </h2>
                        <p className="mx-auto max-w-xl text-muted-foreground">
                            Three steps. No manual back-and-forth. Every lead
                            scored before it reaches your team.
                        </p>
                    </div>

                    <div className="relative grid gap-6 md:grid-cols-3">
                        {/* Connector line (desktop only) */}
                        <div className="absolute top-10 right-[calc(33.33%+1rem)] left-[calc(33.33%+1rem)] hidden h-px bg-gradient-to-r from-[#0E9E8E]/30 via-[#0E9E8E]/60 to-[#0E9E8E]/30 md:block" />

                        {[
                            {
                                step: '01',
                                title: 'Patient Submits Intake',
                                description:
                                    'Patient visits your hosted intake link or embedded widget on your website. They complete a procedure-specific quiz and upload clinical photos directly from their browser — no app download required.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                ),
                            },
                            {
                                step: '02',
                                title: 'AI Analyzes & Scores',
                                description:
                                    'Our AI pipeline validates photo quality, detects facial landmarks, calculates clinical proportions, and generates a lead score with priority tier — all in seconds, before a human ever sees it.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"
                                    />
                                ),
                            },
                            {
                                step: '03',
                                title: 'Coordinator Gets Notified',
                                description:
                                    'Your team receives a real-time in-app notification. The full evaluation — photos, quiz answers, AI score, and recommended procedure — is waiting in the dashboard, ready for clinical review.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                ),
                            },
                        ].map((item, i) => (
                            <div
                                key={i}
                                className="group relative rounded-2xl border border-border/50 bg-card p-8 transition-all hover:border-[#0E9E8E]/40"
                            >
                                <div className="mb-6 flex items-center gap-4">
                                    <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-[#0E9E8E]/20 bg-[#0E9E8E]/10 transition-all group-hover:bg-[#0E9E8E]/20">
                                        <svg
                                            className="h-6 w-6 text-[#0E9E8E]"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            {item.icon}
                                        </svg>
                                    </div>
                                    <span className="text-4xl font-black text-muted-foreground/20 transition-colors group-hover:text-[#0E9E8E]/20">
                                        {item.step}
                                    </span>
                                </div>
                                <h3 className="mb-3 text-lg font-semibold text-foreground">
                                    {item.title}
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {item.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Features Grid */}
                <section className="relative z-10 mx-auto max-w-7xl px-6 pb-24">
                    <div className="mb-14 text-center">
                        <h2 className="mb-4 text-3xl font-bold lg:text-4xl">
                            Everything your clinic needs
                        </h2>
                        <p className="mx-auto max-w-xl text-muted-foreground">
                            Purpose-built for aesthetic medicine — not adapted
                            from a generic CRM.
                        </p>
                    </div>

                    <div className="grid gap-6 md:grid-cols-3">
                        {[
                            {
                                title: 'AI Vision Pipeline',
                                description:
                                    'Real-time AWS Rekognition validation. Detects facial landmarks, calculates proportions, and rejects blurry or non-compliant photos before they reach your coordinators.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                ),
                            },
                            {
                                title: 'Zero-Friction Intake',
                                description:
                                    'WebRTC and native smartphone camera integrations let patients submit high-definition clinical photos straight from their browser — no app, no friction.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                    />
                                ),
                            },
                            {
                                title: 'Clinical Lead Scoring',
                                description:
                                    'Every evaluation is automatically scored by procedure type, photo quality, quiz answers, and urgency signals — so your team focuses on the highest-value consultations first.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                ),
                            },
                            {
                                title: 'Real-Time Notifications',
                                description:
                                    'Coordinators are notified the instant a new evaluation arrives — via in-app WebSocket push. No polling, no refresh, no delays between patient submission and coordinator review.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                ),
                            },
                            {
                                title: 'Embeddable Widget',
                                description:
                                    'Drop a single script tag onto your clinic website. The intake form opens as a modal, floating button, or inline embed — fully branded to your practice with your chosen theme and colors.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                    />
                                ),
                            },
                            {
                                title: 'Role-Based Access',
                                description:
                                    'Granular roles for every team member — Owner, Admin, Coordinator, Surgeon, and Viewer. Each role sees only what they need. Surgeons review clinical findings; coordinators manage follow-ups.',
                                icon: (
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                ),
                            },
                        ].map((feature, i) => (
                            <div
                                key={i}
                                className="group rounded-2xl border border-border/50 bg-card p-8 transition-all hover:-translate-y-1 hover:border-[#0E9E8E]/40 hover:bg-muted/20"
                            >
                                <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-xl border border-border bg-muted/50 transition-all group-hover:border-[#0E9E8E]/30 group-hover:bg-[#0E9E8E]/10">
                                    <svg
                                        className="h-7 w-7 text-[#0E9E8E]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        {feature.icon}
                                    </svg>
                                </div>
                                <h3 className="mb-3 text-xl font-semibold text-foreground">
                                    {feature.title}
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Security & HIPAA Deep-Dive */}
                <section className="relative z-10 mx-auto max-w-7xl px-6 pb-24">
                    <div className="overflow-hidden rounded-3xl border border-border bg-card">
                        <div className="grid lg:grid-cols-2">
                            {/* Left: copy */}
                            <div className="flex flex-col justify-center p-10 lg:p-14">
                                <div className="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-[#0E9E8E]/20 bg-[#0E9E8E]/10 px-3 py-1 text-xs font-semibold tracking-wide text-[#0E9E8E]">
                                    <svg
                                        className="h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                        />
                                    </svg>
                                    Security & Compliance
                                </div>

                                <h2 className="mb-4 text-3xl font-bold text-foreground lg:text-4xl">
                                    HIPAA compliance isn't a checkbox.
                                    <br />
                                    <span className="text-[#0E9E8E]">
                                        It's the foundation.
                                    </span>
                                </h2>
                                <p className="mb-8 leading-relaxed text-muted-foreground">
                                    Every architectural decision — from how we
                                    store photos to how long sessions stay
                                    active — was made with PHI protection as the
                                    first priority, not an afterthought.
                                </p>

                                <Link
                                    href={register()}
                                    className="inline-flex w-fit items-center gap-2 rounded-xl bg-[#0E9E8E] px-6 py-3 font-bold text-[#0A0A0F] transition-colors hover:bg-[#2DD4BF]"
                                >
                                    Start Secure Free Trial
                                    <svg
                                        className="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M17 8l4 4m0 0l-4 4m4-4H3"
                                        />
                                    </svg>
                                </Link>
                            </div>

                            {/* Right: security checklist */}
                            <div className="border-t border-border bg-muted/20 p-10 lg:border-t-0 lg:border-l lg:p-14">
                                <ul className="space-y-5">
                                    {[
                                        {
                                            title: 'End-to-End Encryption',
                                            detail: 'All data encrypted in transit (TLS 1.3) and at rest (AES-256). PHI never travels unencrypted.',
                                        },
                                        {
                                            title: 'Multi-Tenant Data Isolation',
                                            detail: "Every clinic's data is logically isolated at the query level. Cross-tenant data access is architecturally impossible.",
                                        },
                                        {
                                            title: 'Automatic Audit Logging',
                                            detail: 'Every PHI access, status change, and export is logged with timestamp, user, and IP — immutable and searchable.',
                                        },
                                        {
                                            title: 'HIPAA Session Timeout',
                                            detail: 'Automatic 30-minute inactivity logout with a 60-second warning — a hard HIPAA technical safeguard requirement.',
                                        },
                                        {
                                            title: '15-Minute Expiring Photo URLs',
                                            detail: 'Patient photos are never publicly accessible. Every S3 link is a signed URL that expires in 15 minutes.',
                                        },
                                        {
                                            title: 'Two-Factor Authentication',
                                            detail: 'TOTP-based 2FA with QR code setup and recovery codes for all staff accounts. Enforced at the org level.',
                                        },
                                        {
                                            title: 'Role-Based Access Control',
                                            detail: 'Granular permissions per role. Coordinators cannot access billing; surgeons cannot manage team members.',
                                        },
                                    ].map((item, i) => (
                                        <li key={i} className="flex gap-4">
                                            <div className="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-[#0E9E8E]/30 bg-[#0E9E8E]/15">
                                                <svg
                                                    className="h-2.5 w-2.5 text-[#0E9E8E]"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={3}
                                                        d="M5 13l4 4L19 7"
                                                    />
                                                </svg>
                                            </div>
                                            <div>
                                                <p className="mb-0.5 text-sm font-semibold text-foreground">
                                                    {item.title}
                                                </p>
                                                <p className="text-xs leading-relaxed text-muted-foreground">
                                                    {item.detail}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Request Demo Form */}
                <section
                    id="request-demo"
                    className="relative z-10 mx-auto max-w-3xl px-6 pb-32"
                >
                    <div className="relative overflow-hidden rounded-3xl border border-[#0E9E8E]/20 bg-card p-10 shadow-[0_0_50px_rgba(14,158,142,0.05)]">
                        <div className="absolute top-0 right-0 h-64 w-64 rounded-full bg-[#0E9E8E]/5 blur-3xl" />

                        {recentlySuccessful ? (
                            <div className="py-16 text-center">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10">
                                    <svg
                                        className="h-8 w-8 text-emerald-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </div>
                                <h3 className="mb-3 text-2xl font-bold">
                                    Demo Request Received!
                                </h3>
                                <p className="text-muted-foreground">
                                    Thank you for your interest. Our team will
                                    reach out within 1 business day to schedule
                                    your personalized walkthrough.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="relative z-10 mb-8 text-center">
                                    <h2 className="mb-3 text-3xl font-bold">
                                        Request a Demo
                                    </h2>
                                    <p className="text-sm text-muted-foreground md:text-base">
                                        Multi-location practice or want a guided
                                        walkthrough before getting started?
                                        Submit your details and we'll schedule a
                                        personalized demo.
                                    </p>
                                </div>

                                <form
                                    onSubmit={submitRequest}
                                    className="relative z-10 space-y-5"
                                >
                                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-muted-foreground">
                                                Your Name *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.name}
                                                onChange={(e) =>
                                                    setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded-xl border border-border bg-muted px-4 py-3 text-foreground transition outline-none focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E]"
                                                placeholder="e.g. Dr. Sarah Smith"
                                            />
                                            {errors.name && (
                                                <p className="mt-1 text-xs text-red-400">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-sm font-medium text-muted-foreground">
                                                Clinic Name *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={data.clinic_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'clinic_name',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded-xl border border-border bg-muted px-4 py-3 text-foreground transition outline-none focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E]"
                                                placeholder="e.g. Beverly Hills Aesthetics"
                                            />
                                            {errors.clinic_name && (
                                                <p className="mt-1 text-xs text-red-400">
                                                    {errors.clinic_name}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Work Email *
                                        </label>
                                        <input
                                            type="email"
                                            required
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                            className="w-full rounded-xl border border-border bg-muted px-4 py-3 text-foreground transition outline-none focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E]"
                                            placeholder="sarah@clinic.com"
                                        />
                                        {errors.email && (
                                            <p className="mt-1 text-xs text-red-400">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Clinic Website URL
                                        </label>
                                        <input
                                            type="url"
                                            value={data.website_url}
                                            onChange={(e) =>
                                                setData(
                                                    'website_url',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-border bg-muted px-4 py-3 text-foreground transition outline-none focus:border-[#0E9E8E] focus:ring-1 focus:ring-[#0E9E8E]"
                                            placeholder="https://www.clinic.com"
                                        />
                                        {errors.website_url && (
                                            <p className="mt-1 text-xs text-red-400">
                                                {errors.website_url}
                                            </p>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-4 w-full rounded-xl bg-[#0E9E8E] px-8 py-4 font-bold text-[#0A0A0F] transition-colors hover:bg-[#2DD4BF] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {processing
                                            ? 'Sending...'
                                            : 'Request Your Demo'}
                                    </button>
                                </form>
                            </>
                        )}
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-dashed border-border py-8">
                    <div className="mx-auto flex max-w-7xl flex-col items-center gap-6 px-6 text-xs text-muted-foreground">
                        {/* Trust badges */}
                        <TrustBadges variant="light" />

                        <div className="h-px w-full max-w-sm bg-border/50" />

                        <div className="flex justify-center gap-6">
                            <Link
                                href="/affiliate-program"
                                className="transition-colors hover:text-foreground"
                            >
                                Affiliate Program
                            </Link>
                            <Link
                                href="/legal/terms"
                                className="transition-colors hover:text-foreground"
                            >
                                Terms of Service
                            </Link>
                            <Link
                                href="/legal/privacy"
                                className="transition-colors hover:text-foreground"
                            >
                                Privacy Policy
                            </Link>
                        </div>
                        <div>
                            &copy; 2026 SymetriHealth Platform. All rights
                            reserved. Strictly confidential.
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
