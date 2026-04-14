import { Head } from '@inertiajs/react';
import { FileText, ImageIcon, Calendar, CheckCircle2, Clock, MapPin, Phone } from 'lucide-react';
import type { FC } from 'react';

// Status constants matching Backend
const STATUS_DRAFT = 'draft';
const STATUS_SUBMITTED = 'submitted';
const STATUS_ANALYZING = 'analyzing';
const STATUS_COMPLETE = 'complete';
const STATUS_CONTACTED = 'contacted';
const STATUS_BOOKED = 'booked';
const STATUS_NO_SHOW = 'no_show';
const STATUS_NOT_A_FIT = 'not_a_fit';
const STATUS_FAILED = 'failed';

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
    if (status === STATUS_SUBMITTED) return 0;
    if (status === STATUS_ANALYZING) return 1;
    if (status === STATUS_COMPLETE) return 2;
    if (status === STATUS_CONTACTED || status === STATUS_BOOKED) return 3;
    return 0;
};

const PatientPortal: FC<Props> = ({ evaluation, status, isComplete, tenant }) => {
    const clinic = tenant.name;
    const activeStep = getActiveStep(status);

    const steps = [
        { title: 'Received', description: 'Your intake forms have been successfully submitted.' },
        { title: 'AI Analysis', description: 'Our AI is generating your surgical simulation.' },
        { title: 'Doctor Review', description: 'Your clinical team is reviewing the results.' },
        { title: 'Ready', description: 'Your file is complete. Ready to schedule!' },
    ];

    return (
        <>
            <Head title={`Patient Portal — ${clinic}`} />

            <div className="flex min-h-screen flex-col items-center bg-[var(--intake-bg,-#0A0A0F)] px-6 py-12" data-intake-theme="luxury-dark">
                
                {/* Header */}
                <div className="mb-10 w-full max-w-xl text-center flex flex-col items-center">
                    {tenant.settings?.logo_url ? (
                        <img 
                            src={tenant.settings.logo_url} 
                            alt={`${clinic} Logo`} 
                            className="h-12 w-auto mb-6 object-contain"
                        />
                    ) : (
                        <p className="text-xs uppercase tracking-widest text-[#C9A84C] mb-6">{clinic}</p>
                    )}
                    
                    <h1 className="text-3xl font-light text-[var(--intake-fg)] tracking-tight">
                        Patient Dashboard
                    </h1>
                    <p className="mt-2 text-[var(--intake-muted)]">
                        {procedureLabel(evaluation.procedure_slug)} Consultation
                    </p>
                </div>

                {/* Status Timeline Card */}
                <div className="w-full max-w-xl rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-8 shadow-xl mb-8">
                    <h2 className="text-lg text-[var(--intake-fg)] font-medium mb-6">Status Tracker</h2>
                    <div className="relative">
                        {/* Vertical line connecting steps */}
                        <div className="absolute left-4 top-4 bottom-4 w-px bg-[var(--intake-border)]" />

                        <div className="space-y-8 relative">
                            {steps.map((step, index) => {
                                const isCompleted = index < activeStep || isComplete;
                                const isCurrent = index === activeStep && !isComplete;
                                
                                return (
                                    <div key={step.title} className="flex gap-4 items-start">
                                        <div className="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-[var(--intake-surface)]">
                                            {isCompleted ? (
                                                <div className="h-6 w-6 rounded-full bg-[var(--intake-accent)] flex items-center justify-center shadow-[0_0_10px_rgba(14,158,142,0.3)]">
                                                    <CheckCircle2 className="h-4 w-4" style={{ color: 'var(--intake-icon-on-teal)' }} />
                                                </div>
                                            ) : isCurrent ? (
                                                <div className="h-6 w-6 rounded-full border-2 animate-pulse" style={{ borderColor: 'var(--intake-accent)', backgroundColor: 'color-mix(in srgb, var(--intake-accent) 20%, transparent)' }} />
                                            ) : (
                                                <div className="h-6 w-6 rounded-full border-2 border-[var(--intake-border)]" />
                                            )}
                                        </div>
                                        <div className="pt-1 flex-1">
                                            <h3 className={`text-sm font-medium ${isCompleted || isCurrent ? 'text-[var(--intake-fg)]' : 'text-[var(--intake-muted)]'}`}>
                                                {step.title}
                                            </h3>
                                            <p className="text-xs text-[var(--intake-muted)] mt-1">{step.description}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Action Documents Grid */}
                <div className="w-full max-w-xl grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <a
                        href={`/intake/simulations/${evaluation.secure_token}`}
                        target="_blank"
                        rel="noreferrer"
                        className={`group relative overflow-hidden rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-6 transition-all duration-300 ${isComplete ? 'hover:border-[#C9A84C]/50 hover:bg-[var(--intake-bg)]' : 'opacity-50 pointer-events-none'}`}
                    >
                        <div className="mb-4 text-[#C9A84C]">
                            <ImageIcon className="h-6 w-6" />
                        </div>
                        <h3 className="text-sm font-medium text-[var(--intake-fg)]">AI Simulation</h3>
                        <p className="mt-1 text-xs text-[var(--intake-muted)]">View your generated post-surgery preview.</p>
                        
                        {!isComplete && (
                            <div className="mt-4 flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-[var(--intake-muted-faint)] font-semibold">
                                <Clock className="h-3 w-3" /> Processing
                            </div>
                        )}
                    </a>

                    <a
                        href={`/intake/evaluations/${evaluation.secure_token}/report`}
                        target="_blank"
                        rel="noreferrer"
                        className={`group relative overflow-hidden rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-6 transition-all duration-300 ${isComplete ? 'hover:border-[var(--intake-accent)]/50 hover:bg-[var(--intake-bg)]' : 'opacity-50 pointer-events-none'}`}
                    >
                        <div className="mb-4 text-[var(--intake-accent)]">
                            <FileText className="h-6 w-6" />
                        </div>
                        <h3 className="text-sm font-medium text-[var(--intake-fg)]">Beauty Roadmap</h3>
                        <p className="mt-1 text-xs text-[var(--intake-muted)]">Download your medical evaluation PDF.</p>
                        
                        {!isComplete && (
                            <div className="mt-4 flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-[var(--intake-muted-faint)] font-semibold">
                                <Clock className="h-3 w-3" /> Processing
                            </div>
                        )}
                    </a>
                </div>

                {/* Scheduling Call to Action */}
                <div className="w-full max-w-xl rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)] p-8 text-center shadow-xl">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full" style={{ backgroundColor: 'color-mix(in srgb, var(--intake-accent) 10%, transparent)' }}>
                        <Calendar className="h-6 w-6" style={{ color: 'var(--intake-accent)' }} />
                    </div>
                    <h2 className="text-lg font-medium text-[var(--intake-fg)] mb-2">Ready for your Consultation?</h2>
                    <p className="text-sm text-[var(--intake-muted)] mb-6">
                        Contact our patient coordinators to finalize your surgical plan and reserve your date.
                    </p>
                    
                    <a href="javascript:void(0)" className="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold transition-all shadow-md sm:w-auto hover:opacity-90" style={{ backgroundColor: 'var(--intake-accent)', color: 'var(--intake-icon-on-teal)' }}>
                        <Phone className="h-4 w-4" />
                        Contact Clinic to Book
                    </a>
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
