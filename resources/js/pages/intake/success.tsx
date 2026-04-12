import { Head } from '@inertiajs/react';
import type {FC} from 'react';

interface Props {
    clinic: {
        name: string;
        logo?: string;
    };
}

const SuccessPage: FC<Props> = ({ clinic }) => {
    return (
        <>
            <Head title={`Thank You — ${clinic.name}`} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-[#0A0A0F] px-6 py-12">
                {/* Animated gold ring + checkmark */}
                <div className="relative mb-8">
                    <div className="h-24 w-24 rounded-full bg-[#0E9E8E]/10 ring-1 ring-[#0E9E8E]/30 flex items-center justify-center">
                        <div className="h-16 w-16 rounded-full bg-[#0E9E8E]/20 ring-1 ring-[#0E9E8E]/40 flex items-center justify-center">
                            <svg
                                className="h-8 w-8 text-[#0E9E8E]"
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
                    <div className="absolute inset-0 rounded-full ring-1 ring-[#0E9E8E]/20 animate-ping" />
                </div>

                {/* Clinic identity */}
                <p className="text-xs font-semibold uppercase tracking-widest text-[#0E9E8E] mb-3">
                    {clinic.name}
                </p>

                {/* Headline */}
                <h1 className="text-3xl font-bold text-[#F5F0E8] text-center leading-tight">
                    Evaluation submitted!
                </h1>

                <p className="mt-4 max-w-sm text-center text-sm text-[#9B9B8E] leading-relaxed">
                    Thank you for completing your AI evaluation. Our team will review your
                    results and reach out within&nbsp;
                    <span className="text-[#F5F0E8] font-medium">1–2 business days</span> to
                    discuss next steps.
                </p>

                {/* What happens next */}
                <div className="mt-10 w-full max-w-sm rounded-2xl border border-white/10 bg-[#111118] overflow-hidden">
                    <div className="px-5 py-3 border-b border-white/5">
                        <p className="text-xs font-semibold uppercase tracking-widest text-[#0E9E8E]">
                            What happens next
                        </p>
                    </div>

                    <ul className="divide-y divide-white/5">
                        {[
                            {
                                icon: '🤖',
                                title: 'AI analysis',
                                description: 'Our system analyses your photos and quiz responses.',
                            },
                            {
                                icon: '👨‍⚕️',
                                title: 'Physician review',
                                description: 'A licensed surgeon reviews your AI-generated report.',
                            },
                            {
                                icon: '📞',
                                title: 'Consultation call',
                                description: 'A coordinator contacts you to schedule your consultation.',
                            },
                        ].map((item) => (
                            <li key={item.title} className="flex items-start gap-4 px-5 py-4">
                                <span className="text-xl shrink-0">{item.icon}</span>
                                <div>
                                    <p className="text-sm font-semibold text-[#F5F0E8]">
                                        {item.title}
                                    </p>
                                    <p className="text-xs text-[#9B9B8E] mt-0.5">
                                        {item.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Check email note */}
                <div className="mt-6 flex items-center gap-2 rounded-xl border border-white/5 bg-white/[0.02] px-5 py-3.5">
                    <svg className="h-4 w-4 text-[#0E9E8E] shrink-0" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" strokeWidth="1.5" />
                        <path d="M2 8l10 6 10-6" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
                    </svg>
                    <p className="text-xs text-[#9B9B8E]">
                        Check your email — we've sent you a confirmation.
                    </p>
                </div>

                {/* Footer */}
                <p className="mt-12 text-[11px] text-white/20 text-center">
                    {clinic.name} · AI-Powered Aesthetic Evaluation · HIPAA Compliant
                </p>
            </div>
        </>
    );
};

export default SuccessPage;
