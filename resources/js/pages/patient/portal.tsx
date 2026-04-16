import { Head } from '@inertiajs/react';
import {
    FileText,
    ImageIcon,
    Calendar,
    CheckCircle2,
    Clock,
    Phone,
} from 'lucide-react';
import type { FC } from 'react';

// Status constants matching Backend
const STATUS_SUBMITTED = 'submitted';
const STATUS_ANALYZING = 'analyzing';
const STATUS_COMPLETE = 'complete';
const STATUS_CONTACTED = 'contacted';
const STATUS_BOOKED = 'booked';

interface Props {
    evaluation: {
        secure_token: string;
        procedure_slug: string;
        created_at: string;
    };
    status: string;
    isComplete: boolean;
    tenant: {
        name: string;
        slug: string;
        settings: {
            logo_url?: string;
        };
    };
    phone: string | null;
    bookingUrl: string | null;
}

function procedureLabel(slug: string): string {
    const labels: Record<string, string> = {
        bbl: 'Brazilian Butt Lift (BBL)',
        lipo_360: 'Liposuction 360',
        breast_augmentation: 'Breast Augmentation',
        rhinoplasty: 'Rhinoplasty',
        facelift: 'Facelift',
    };

    return labels[slug] ?? slug.replace(/_/g, ' ');
}

// Map backend statuses to friendly frontend steps
const getActiveStep = (status: string) => {
    if (status === STATUS_SUBMITTED) {
        return 0;
    }

    if (status === STATUS_ANALYZING) {
        return 1;
    }

    if (status === STATUS_COMPLETE) {
        return 2;
    }

    if (status === STATUS_CONTACTED || status === STATUS_BOOKED) {
        return 3;
    }

    return 0;
};

const PatientPortal: FC<Props> = ({
    evaluation,
    status,
    isComplete,
    tenant,
    phone,
    bookingUrl,
}) => {
    const clinic = tenant.name;
    const activeStep = getActiveStep(status);

    const steps = [
        {
            title: 'Received',
            description: 'Your intake forms have been successfully submitted.',
        },
        {
            title: 'AI Analysis',
            description: 'Our AI is generating your surgical simulation.',
        },
        {
            title: 'Doctor Review',
            description: 'Your clinical team is reviewing the results.',
        },
        {
            title: 'Ready',
            description: 'Your file is complete. Ready to schedule!',
        },
    ];

    return (
        <>
            <Head title={`Patient Portal — ${clinic}`} />

            <div
                className="flex min-h-screen flex-col items-center bg-[var(--intake-bg,-#0A0A0F)] px-6 py-12"
                data-intake-theme="luxury-dark"
            >
                {/* Header */}
                <div className="mb-10 flex w-full max-w-xl flex-col items-center text-center">
                    {tenant.settings?.logo_url ? (
                        <img
                            src={tenant.settings.logo_url}
                            alt={`${clinic} Logo`}
                            className="mb-6 h-12 w-auto object-contain"
                        />
                    ) : (
                        <p className="mb-6 text-xs tracking-widest text-[#C9A84C] uppercase">
                            {clinic}
                        </p>
                    )}

                    <h1 className="text-3xl font-light tracking-tight text-[var(--intake-fg)]">
                        Patient Dashboard
                    </h1>
                    <p className="mt-2 text-[var(--intake-muted)]">
                        {procedureLabel(evaluation.procedure_slug)} Consultation
                    </p>
                </div>

                {/* Status Timeline Card */}
                <div className="mb-8 w-full max-w-xl rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-8 shadow-xl">
                    <h2 className="mb-6 text-lg font-medium text-[var(--intake-fg)]">
                        Status Tracker
                    </h2>
                    <div className="relative">
                        {/* Vertical line connecting steps */}
                        <div className="absolute top-4 bottom-4 left-4 w-px bg-[var(--intake-border)]" />

                        <div className="relative space-y-8">
                            {steps.map((step, index) => {
                                const isCompleted =
                                    index < activeStep || isComplete;
                                const isCurrent =
                                    index === activeStep && !isComplete;

                                return (
                                    <div
                                        key={step.title}
                                        className="flex items-start gap-4"
                                    >
                                        <div className="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-[var(--intake-surface)]">
                                            {isCompleted ? (
                                                <div className="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--intake-accent)] shadow-[0_0_10px_rgba(14,158,142,0.3)]">
                                                    <CheckCircle2
                                                        className="h-4 w-4"
                                                        style={{
                                                            color: 'var(--intake-icon-on-teal)',
                                                        }}
                                                    />
                                                </div>
                                            ) : isCurrent ? (
                                                <div
                                                    className="h-6 w-6 animate-pulse rounded-full border-2"
                                                    style={{
                                                        borderColor:
                                                            'var(--intake-accent)',
                                                        backgroundColor:
                                                            'color-mix(in srgb, var(--intake-accent) 20%, transparent)',
                                                    }}
                                                />
                                            ) : (
                                                <div className="h-6 w-6 rounded-full border-2 border-[var(--intake-border)]" />
                                            )}
                                        </div>
                                        <div className="flex-1 pt-1">
                                            <h3
                                                className={`text-sm font-medium ${isCompleted || isCurrent ? 'text-[var(--intake-fg)]' : 'text-[var(--intake-muted)]'}`}
                                            >
                                                {step.title}
                                            </h3>
                                            <p className="mt-1 text-xs text-[var(--intake-muted)]">
                                                {step.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Action Documents Grid */}
                <div className="mb-8 grid w-full max-w-xl grid-cols-1 gap-4 md:grid-cols-2">
                    <a
                        href={`/intake/simulations/${evaluation.secure_token}`}
                        target="_blank"
                        rel="noreferrer"
                        className={`group relative overflow-hidden rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-6 transition-all duration-300 ${isComplete ? 'hover:border-[#C9A84C]/50 hover:bg-[var(--intake-bg)]' : 'pointer-events-none opacity-50'}`}
                    >
                        <div className="mb-4 text-[#C9A84C]">
                            <ImageIcon className="h-6 w-6" />
                        </div>
                        <h3 className="text-sm font-medium text-[var(--intake-fg)]">
                            AI Simulation
                        </h3>
                        <p className="mt-1 text-xs text-[var(--intake-muted)]">
                            View your generated post-surgery preview.
                        </p>

                        {!isComplete && (
                            <div className="mt-4 flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-[var(--intake-muted-faint)] uppercase">
                                <Clock className="h-3 w-3" /> Processing
                            </div>
                        )}
                    </a>

                    <a
                        href={`/intake/evaluations/${evaluation.secure_token}/report`}
                        target="_blank"
                        rel="noreferrer"
                        className={`group relative overflow-hidden rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-6 transition-all duration-300 ${isComplete ? 'hover:border-[var(--intake-accent)]/50 hover:bg-[var(--intake-bg)]' : 'pointer-events-none opacity-50'}`}
                    >
                        <div className="mb-4 text-[var(--intake-accent)]">
                            <FileText className="h-6 w-6" />
                        </div>
                        <h3 className="text-sm font-medium text-[var(--intake-fg)]">
                            Beauty Roadmap
                        </h3>
                        <p className="mt-1 text-xs text-[var(--intake-muted)]">
                            Download your medical evaluation PDF.
                        </p>

                        {!isComplete && (
                            <div className="mt-4 flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-[var(--intake-muted-faint)] uppercase">
                                <Clock className="h-3 w-3" /> Processing
                            </div>
                        )}
                    </a>
                </div>

                {/* Scheduling Call to Action */}
                <div className="w-full max-w-xl rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-8 text-center shadow-xl">
                    <div
                        className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full"
                        style={{
                            backgroundColor:
                                'color-mix(in srgb, var(--intake-accent) 10%, transparent)',
                        }}
                    >
                        <Calendar
                            className="h-6 w-6"
                            style={{ color: 'var(--intake-accent)' }}
                        />
                    </div>
                    <h2 className="mb-2 text-lg font-medium text-[var(--intake-fg)]">
                        Ready for your Consultation?
                    </h2>
                    <p className="mb-6 text-sm text-[var(--intake-muted)]">
                        Contact our patient coordinators to finalize your
                        surgical plan and reserve your date.
                    </p>

                    {bookingUrl ? (
                        <a
                            href={bookingUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold shadow-md transition-all hover:opacity-90 sm:w-auto"
                            style={{
                                backgroundColor: 'var(--intake-accent)',
                                color: 'var(--intake-icon-on-teal)',
                            }}
                        >
                            <Calendar className="h-4 w-4" />
                            Book a Consultation
                        </a>
                    ) : phone ? (
                        <a
                            href={`tel:${phone}`}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold shadow-md transition-all hover:opacity-90 sm:w-auto"
                            style={{
                                backgroundColor: 'var(--intake-accent)',
                                color: 'var(--intake-icon-on-teal)',
                            }}
                        >
                            <Phone className="h-4 w-4" />
                            {phone}
                        </a>
                    ) : (
                        <p className="text-sm text-[var(--intake-muted)]">
                            Our team will reach out within 1–2 business days to
                            schedule your consultation.
                        </p>
                    )}
                </div>

                {/* Footer */}
                <p className="mt-12 text-xs text-[var(--intake-muted-faint)]">
                    Secure Patient Portal • Powered by AestheticAI
                </p>
            </div>
        </>
    );
};

export default PatientPortal;
