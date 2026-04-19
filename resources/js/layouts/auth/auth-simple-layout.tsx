import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const trustSignals = [
    { label: 'HIPAA-compliant by design' },
    { label: 'AES-256 encrypted patient data' },
    { label: 'Automatic 30-min session timeout' },
    { label: 'Role-based access control' },
    { label: 'Full PHI audit logging' },
];

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="relative min-h-svh bg-background font-sans text-foreground lg:grid lg:grid-cols-2">

            {/* ── Left panel (hidden on mobile) ────────────────────────────── */}
            <div className="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
                {/* Background glow — same as welcome page */}
                <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#0E9E8E]/20 via-[#0E9E8E]/5 to-transparent" />
                <div className="pointer-events-none absolute -top-40 -left-40 h-[600px] w-[600px] rounded-full bg-[#0E9E8E]/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 -right-20 h-[500px] w-[500px] rounded-full bg-[#0E9E8E]/5 blur-3xl" />

                {/* Logo */}
                <Link href={home()} className="relative z-10 flex items-center gap-3">
                    <div className="shrink-0 rounded-xl bg-white p-1.5 shadow-sm">
                        <img src="/logo.png" alt="SymetriHealth" className="h-9 w-auto" />
                    </div>
                    <span className="text-xl font-bold tracking-widest text-[#0E9E8E] uppercase">
                        SymetriHealth
                    </span>
                </Link>

                {/* Middle content */}
                <div className="relative z-10 space-y-8">
                    <div>
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#0E9E8E]/20 bg-[#0E9E8E]/10 px-3 py-1 text-xs font-semibold tracking-wide text-[#0E9E8E]">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-[#0E9E8E]" />
                            HIPAA-Compliant Platform
                        </div>
                        <h2 className="text-4xl font-bold tracking-tight leading-tight">
                            The future of<br />
                            <span className="bg-gradient-to-r from-[#0E9E8E] via-[#2DD4BF] to-[#0E9E8E] bg-clip-text text-transparent">
                                aesthetic consultations.
                            </span>
                        </h2>
                        <p className="mt-4 text-muted-foreground leading-relaxed max-w-sm">
                            Automate your patient intake pipeline with real-time AI facial analysis, instant photo quality validation, and advanced clinical lead scoring.
                        </p>
                    </div>

                    {/* Trust signals */}
                    <ul className="space-y-3">
                        {trustSignals.map((signal) => (
                            <li key={signal.label} className="flex items-center gap-3">
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[#0E9E8E]/30 bg-[#0E9E8E]/15">
                                    <ShieldCheck className="h-3 w-3 text-[#0E9E8E]" />
                                </div>
                                <span className="text-sm text-muted-foreground">{signal.label}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Bottom */}
                <p className="relative z-10 text-xs text-muted-foreground/50">
                    &copy; 2026 SymetriHealth Platform. All rights reserved.
                </p>
            </div>

            {/* ── Right panel — form ────────────────────────────────────────── */}
            <div className="flex min-h-svh flex-col items-center justify-center p-6 md:p-10 lg:min-h-0">

                {/* Mobile logo */}
                <Link href={home()} className="mb-8 flex items-center gap-3 lg:hidden">
                    <div className="shrink-0 rounded-xl bg-white p-1.5 shadow-sm">
                        <img src="/logo.png" alt="SymetriHealth" className="h-8 w-auto" />
                    </div>
                    <span className="text-lg font-bold tracking-widest text-[#0E9E8E] uppercase">
                        SymetriHealth
                    </span>
                </Link>

                <div className="w-full max-w-sm">
                    {/* Card */}
                    <div className="rounded-2xl border border-border bg-card p-8 shadow-[0_0_40px_rgba(14,158,142,0.06)]">
                        {/* Heading */}
                        <div className="mb-8 space-y-1.5">
                            <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                            {description && (
                                <p className="text-sm text-muted-foreground">{description}</p>
                            )}
                        </div>

                        {children}
                    </div>

                    {/* Footer */}
                    <p className="mt-6 text-center text-xs text-muted-foreground/50">
                        Protected by HIPAA-compliant infrastructure.{' '}
                        <Link href="/legal/terms" className="underline underline-offset-2 hover:text-muted-foreground">
                            Terms
                        </Link>
                        {' · '}
                        <Link href="/legal/privacy" className="underline underline-offset-2 hover:text-muted-foreground">
                            Privacy
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
