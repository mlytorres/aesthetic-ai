import type {FC} from 'react';
import type {Procedure, WizardState, WizardAction} from '@/types/intake';

interface Props {
    procedures: Procedure[];
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    onNext: () => void;
}

const CATEGORY_LABELS: Record<string, string> = {
    face:   'Face & Nose',
    body:   'Body Contouring',
    breast: 'Breast',
    skin:   'Skin & Rejuvenation',
};

const ProcedureSelect: FC<Props> = ({ procedures, state, dispatch, onNext }) => {
    // Group procedures by category
    const groups = procedures.reduce<Record<string, Procedure[]>>((acc, p) => {
        const cat = p.category ?? 'other';

        if (!acc[cat]) {
acc[cat] = [];
}

        acc[cat].push(p);

        return acc;
    }, {});

    const handleSelect = (procedure: Procedure): void => {
        dispatch({ type: 'SELECT_PROCEDURE', procedure });
    };

    return (
        <div className="py-6">
            <h1 className="text-2xl font-bold text-[var(--intake-fg)] tracking-tight">
                What brings you in today?
            </h1>
            <p className="mt-2 text-sm text-[var(--intake-muted)]">
                Select the procedure you are interested in. Our AI will guide you through a
                personalised evaluation.
            </p>

            <div className="mt-8 space-y-6">
                {Object.entries(groups).map(([category, procs]) => (
                    <div key={category}>
                        <p className="mb-3 text-[11px] font-semibold uppercase tracking-widest text-[#0E9E8E]">
                            {CATEGORY_LABELS[category] ?? category}
                        </p>

                        <div className="space-y-2">
                            {procs.map((proc) => {
                                const isSelected = state.selectedProcedure?.slug === proc.slug;

                                return (
                                    <button
                                        key={proc.slug}
                                        type="button"
                                        onClick={() => handleSelect(proc)}
                                        className={[
                                            'group w-full rounded-xl border px-5 py-4 text-left transition-all duration-200',
                                            isSelected
                                                ? 'border-[#0E9E8E] bg-[#0E9E8E]/10 ring-1 ring-[#0E9E8E]/30'
                                                : 'border-[var(--intake-border)] bg-[var(--intake-surface)] hover:border-[var(--intake-border-hover)] hover:bg-white/[0.04]',
                                        ].join(' ')}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span
                                                className={[
                                                    'text-sm font-medium transition-colors',
                                                    isSelected
                                                        ? 'text-[#0E9E8E]'
                                                        : 'text-[var(--intake-fg)] group-hover:text-white',
                                                ].join(' ')}
                                            >
                                                {proc.label}
                                            </span>

                                            {/* Checkmark */}
                                            <span
                                                className={[
                                                    'flex h-5 w-5 items-center justify-center rounded-full border transition-all',
                                                    isSelected
                                                        ? 'border-[#0E9E8E] bg-[#0E9E8E]'
                                                        : 'border-[var(--intake-border-hover)]',
                                                ].join(' ')}
                                            >
                                                {isSelected && (
                                                    <svg
                                                        className="h-3 w-3 text-[var(--intake-icon-on-teal)]"
                                                        viewBox="0 0 12 12"
                                                        fill="none"
                                                    >
                                                        <path
                                                            d="M2 6l3 3 5-5"
                                                            stroke="currentColor"
                                                            strokeWidth="1.5"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                        />
                                                    </svg>
                                                )}
                                            </span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            {/* Error */}
            {state.error && (
                <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                    {state.error}
                </p>
            )}

            {/* CTA */}
            <button
                type="button"
                onClick={onNext}
                disabled={!state.selectedProcedure || state.loading}
                className={[
                    'mt-8 w-full rounded-xl px-6 py-4 text-sm font-semibold tracking-wide transition-all duration-200',
                    state.selectedProcedure && !state.loading
                        ? 'bg-[#0E9E8E] text-[var(--intake-icon-on-teal)] hover:bg-[#a8883e] active:scale-[0.98]'
                        : 'cursor-not-allowed bg-white/10 text-white/30',
                ].join(' ')}
            >
                {state.loading ? (
                    <span className="flex items-center justify-center gap-2">
                        <Spinner />
                        Starting evaluation…
                    </span>
                ) : (
                    'Continue →'
                )}
            </button>
        </div>
    );
};

const Spinner: FC = () => (
    <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path
            className="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
        />
    </svg>
);

export default ProcedureSelect;
