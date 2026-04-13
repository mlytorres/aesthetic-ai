import { Head } from '@inertiajs/react';
import type { FC } from 'react';

interface Props {
    procedure: string;
    simulationUrl: string | null;
    tenantName: string | null;
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

const SimulationSharePage: FC<Props> = ({ procedure, simulationUrl, tenantName }) => {
    const clinic = tenantName ?? 'Your Clinic';

    return (
        <>
            <Head title={`AI Simulation — ${procedureLabel(procedure)}`} />

            <div className="flex min-h-screen flex-col items-center bg-[var(--intake-bg)] px-6 py-12">
                {/* Header */}
                <div className="mb-8 text-center">
                    <p className="text-xs uppercase tracking-widest text-[#C9A84C] mb-2">{clinic}</p>
                    <h1 className="text-2xl font-light text-[var(--intake-fg)]">
                        AI Simulation Result
                    </h1>
                    <p className="mt-1 text-sm text-[var(--intake-muted)]">{procedureLabel(procedure)}</p>
                </div>

                {/* Simulation image or placeholder */}
                <div className="w-full max-w-md">
                    {simulationUrl ? (
                        <img
                            src={simulationUrl}
                            alt={`AI simulation result for ${procedureLabel(procedure)}`}
                            className="w-full rounded-xl border border-[var(--intake-border)] object-cover shadow-2xl"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-[#C9A84C]/20 bg-[#0D0D14] py-16 px-8">
                            <span className="text-3xl text-[#C9A84C]">✦</span>
                            <p className="text-center text-sm text-[var(--intake-muted)]">
                                Simulation image is currently unavailable. Please contact your clinic.
                            </p>
                        </div>
                    )}
                </div>

                {/* Disclaimer */}
                <div className="mt-6 w-full max-w-md rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                    <p className="text-center text-[11px] leading-relaxed text-amber-300/70">
                        ⚠ This simulation is a visual aid for consultation purposes only.
                        Results are computer-generated and are not a guarantee of surgical outcome.
                        Individual results may vary.
                    </p>
                </div>

                {/* Footer */}
                <p className="mt-8 text-xs text-[var(--intake-muted)]/50">
                    Powered by {clinic} · AI-Assisted Visualization
                </p>
            </div>
        </>
    );
};

export default SimulationSharePage;
