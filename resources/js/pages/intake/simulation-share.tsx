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

const SimulationSharePage: FC<Props> = ({
    procedure,
    simulationUrl,
    tenantName,
}) => {
    const clinic = tenantName ?? 'Your Clinic';

    return (
        <>
            <Head title={`AI Simulation — ${procedureLabel(procedure)}`} />

            <div
                className="flex min-h-screen flex-col items-center bg-[var(--intake-bg,-#0A0A0F)] px-6 py-12"
                data-intake-theme="luxury-dark"
            >
                {/* Header */}
                <div className="mb-8 text-center">
                    <p className="mb-2 text-xs tracking-widest text-[var(--intake-accent,-#C9A84C)] uppercase">
                        {clinic}
                    </p>
                    <h1 className="text-2xl font-light text-[var(--intake-fg,-#F5F0E8)]">
                        AI Simulation Result
                    </h1>
                    <p className="mt-1 text-sm text-[var(--intake-muted,-#9B9B8E)]">
                        {procedureLabel(procedure)}
                    </p>
                </div>

                {/* Simulation image or placeholder */}
                <div className="w-full max-w-md">
                    {simulationUrl ? (
                        <img
                            src={simulationUrl}
                            alt={`AI simulation result for ${procedureLabel(procedure)}`}
                            className="w-full rounded-xl border border-[var(--intake-border,-rgba(255,255,255,0.1))] object-cover shadow-2xl"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-[#C9A84C]/50 bg-[var(--intake-surface,-#111118)] px-8 py-16">
                            <span className="text-3xl text-[#C9A84C]">✦</span>
                            <p className="text-center text-sm text-[var(--intake-muted,-#9B9B8E)]">
                                Simulation image is currently unavailable.
                                Please contact your clinic.
                            </p>
                        </div>
                    )}
                </div>

                {/* Disclaimer */}
                <div className="mt-6 w-full max-w-md rounded-lg border border-amber-500/20 bg-amber-500/10 px-4 py-3">
                    <p className="text-center text-[11px] leading-relaxed font-medium text-amber-500">
                        ⚠ This simulation is a visual aid for consultation
                        purposes only. Results are computer-generated and are
                        not a guarantee of surgical outcome. Individual results
                        may vary.
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
