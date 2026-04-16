import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FC } from 'react';

interface Props {
    clinic: {
        name: string;
        logo?: string;
        booking_url?: string;
        theme?: string;
        brand_primary?: string | null;
        brand_font?: string | null;
    };
    evaluation?: {
        token: string | null;
        name: string | null;
        email: string | null;
    };
}

const SuccessPage: FC<Props> = ({ clinic, evaluation }) => {
    useEffect(() => {
        // 📢 Notify parent window if we are in an iframe
        if (window.self !== window.top) {
            window.parent.postMessage(
                {
                    type: 'EVALUATION_COMPLETE',
                    clinic: clinic.name,
                    name: evaluation?.name,
                    email: evaluation?.email,
                    token: evaluation?.token,
                },
                '*',
            );

            // If a booking URL is set, redirect the parent window after a short delay
            if (clinic.booking_url) {
                setTimeout(() => {
                    window.top?.location.assign(clinic.booking_url!);
                }, 3000);
            }
        }
    }, [clinic, evaluation]);

    return (
        <>
            <Head title={`Thank You — ${clinic.name}`} />

            <div
                className="flex min-h-screen flex-col items-center justify-center bg-[var(--intake-bg)] px-6 py-12"
                data-intake-theme={clinic.theme}
                style={
                    {
                        fontFamily: 'var(--intake-font)',
                        ...(clinic.brand_primary
                            ? { '--intake-accent': clinic.brand_primary }
                            : {}),
                        ...(clinic.brand_font
                            ? { '--intake-font': clinic.brand_font }
                            : {}),
                    } as React.CSSProperties
                }
            >
                {/* Animated gold ring + checkmark */}
                <div className="relative mb-8">
                    <div className="flex h-24 w-24 items-center justify-center rounded-full bg-[var(--intake-accent)]/10 ring-1 ring-[var(--intake-accent)]/30">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--intake-accent)]/20 ring-1 ring-[var(--intake-accent)]/40">
                            <svg
                                className="h-8 w-8 text-[var(--intake-accent)]"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M5 13l4 4L19 7"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </div>
                    </div>

                    {/* Pulse ring */}
                    <div className="absolute inset-0 animate-ping rounded-full ring-1 ring-[var(--intake-accent)]/20" />
                </div>

                {/* Clinic identity */}
                <p className="mb-3 text-xs font-semibold tracking-widest text-[var(--intake-accent)] uppercase">
                    {clinic.name}
                </p>

                {/* Headline */}
                <h1 className="text-center text-3xl leading-tight font-bold text-[var(--intake-fg)]">
                    Evaluation submitted!
                </h1>

                <p className="mt-4 max-w-sm text-center text-sm leading-relaxed text-[var(--intake-muted)]">
                    Thank you for completing your AI evaluation. Our team will
                    review your results and reach out within&nbsp;
                    <span className="font-medium text-[var(--intake-fg)]">
                        1–2 business days
                    </span>{' '}
                    to discuss next steps.
                </p>

                {/* What happens next */}
                <div className="mt-10 w-full max-w-sm overflow-hidden rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)]">
                    <div className="border-b border-[var(--intake-border-xs)] px-5 py-3">
                        <p className="text-xs font-semibold tracking-widest text-[var(--intake-accent)] uppercase">
                            What happens next
                        </p>
                    </div>

                    <ul className="divide-y divide-white/5">
                        {[
                            {
                                icon: '🤖',
                                title: 'AI analysis',
                                description:
                                    'Our system analyses your photos and quiz responses.',
                            },
                            {
                                icon: '👨‍⚕️',
                                title: 'Physician review',
                                description:
                                    'A licensed surgeon reviews your AI-generated report.',
                            },
                            {
                                icon: '📞',
                                title: 'Consultation call',
                                description:
                                    'A coordinator contacts you to schedule your consultation.',
                            },
                        ].map((item) => (
                            <li
                                key={item.title}
                                className="flex items-start gap-4 px-5 py-4"
                            >
                                <span className="shrink-0 text-xl">
                                    {item.icon}
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-[var(--intake-fg)]">
                                        {item.title}
                                    </p>
                                    <p className="mt-0.5 text-xs text-[var(--intake-muted)]">
                                        {item.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Check email note */}
                <div className="mt-6 flex items-center gap-2 rounded-xl border border-[var(--intake-border-xs)] bg-white/[0.02] px-5 py-3.5">
                    <svg
                        className="h-4 w-4 shrink-0 text-[var(--intake-accent)]"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <rect
                            x="2"
                            y="4"
                            width="20"
                            height="16"
                            rx="2"
                            stroke="currentColor"
                            strokeWidth="1.5"
                        />
                        <path
                            d="M2 8l10 6 10-6"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinejoin="round"
                        />
                    </svg>
                    <p className="text-xs text-[var(--intake-muted)]">
                        Check your email — we've sent you a confirmation.
                    </p>
                </div>

                {/* Footer */}
                <p className="mt-12 text-center text-[11px] text-[var(--intake-muted-faint)]">
                    {clinic.name} · AI-Powered Aesthetic Evaluation · HIPAA
                    Compliant
                </p>
            </div>
        </>
    );
};

export default SuccessPage;
