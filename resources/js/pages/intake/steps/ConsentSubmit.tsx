import { type FC } from 'react';
import { type WizardState, type WizardAction, type ConsentFormData } from '@/types/intake';

interface Props {
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    onSubmit: () => void;
    onBack: () => void;
}

const CONSENTS: {
    field: keyof ConsentFormData;
    label: string;
    description: string;
    required: boolean;
}[] = [
    {
        field: 'hipaa_acknowledged',
        label: 'HIPAA Privacy Notice',
        description:
            'I acknowledge that I have been informed of my rights under the Health Insurance Portability and Accountability Act (HIPAA). My protected health information may be used for treatment and evaluation purposes.',
        required: true,
    },
    {
        field: 'terms_accepted',
        label: 'Terms of Service',
        description:
            'I agree to the Terms of Service. I understand that this evaluation is informational and does not constitute medical advice. A licensed physician will review my case.',
        required: true,
    },
    {
        field: 'photo_use_consent',
        label: 'AI Photo Analysis Consent',
        description:
            'I consent to my photos being analysed by artificial intelligence to assist in generating my evaluation report. Photos are encrypted and accessible only to clinic staff.',
        required: true,
    },
];

const ConsentSubmit: FC<Props> = ({ state, dispatch, onSubmit, onBack }) => {
    const { consent, contact } = state;

    const allConsented =
        consent.hipaa_acknowledged &&
        consent.terms_accepted &&
        consent.photo_use_consent;

    const toggle = (field: keyof ConsentFormData): void => {
        const current = consent[field] as boolean;
        dispatch({ type: 'SET_CONSENT', field, value: !current });
    };

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[#F5F0E8]">Review & confirm</h2>
            <p className="mt-2 text-sm text-[#9B9B8E]">
                Please review your details and grant the required consents to complete your
                evaluation submission.
            </p>

            {/* Summary card */}
            <div className="mt-6 rounded-xl border border-white/10 bg-[#111118] overflow-hidden">
                <div className="px-5 py-3 border-b border-white/5">
                    <p className="text-xs font-semibold uppercase tracking-widest text-[#C9A84C]">
                        Your submission
                    </p>
                </div>

                <dl className="divide-y divide-white/5">
                    <SummaryRow label="Procedure" value={state.selectedProcedure?.label ?? '—'} />
                    <SummaryRow label="Name" value={contact.name || '—'} />
                    <SummaryRow label="Email" value={contact.email || '—'} />
                    {contact.phone && <SummaryRow label="Phone" value={contact.phone} />}
                    <SummaryRow
                        label="Photos uploaded"
                        value={`${state.photos.filter((p) => !p.uploading && !p.error).length} photo(s)`}
                    />
                    <SummaryRow
                        label="Quiz answers"
                        value={`${Object.keys(state.quizAnswers).length} question(s)`}
                    />
                </dl>
            </div>

            {/* Consent checkboxes */}
            <div className="mt-6 space-y-4">
                <p className="text-xs font-semibold uppercase tracking-widest text-[#9B9B8E]">
                    Required consents
                </p>

                {CONSENTS.map(({ field, label, description }) => {
                    const checked = consent[field] as boolean;

                    return (
                        <button
                            key={field}
                            type="button"
                            onClick={() => toggle(field)}
                            className={[
                                'flex w-full items-start gap-4 rounded-xl border px-4 py-4 text-left transition-all duration-150',
                                checked
                                    ? 'border-[#C9A84C]/30 bg-[#C9A84C]/5'
                                    : 'border-white/10 bg-[#111118] hover:border-white/20',
                            ].join(' ')}
                        >
                            {/* Checkbox */}
                            <span
                                className={[
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border transition-all',
                                    checked ? 'border-[#C9A84C] bg-[#C9A84C]' : 'border-white/30 bg-transparent',
                                ].join(' ')}
                            >
                                {checked && (
                                    <svg className="h-3 w-3 text-[#0A0A0F]" viewBox="0 0 12 12" fill="none">
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

                            <div>
                                <p className={[
                                    'text-sm font-semibold',
                                    checked ? 'text-[#F5F0E8]' : 'text-[#9B9B8E]',
                                ].join(' ')}>
                                    {label} <span className="text-[#C9A84C]">*</span>
                                </p>
                                <p className="mt-1 text-xs text-[#9B9B8E] leading-relaxed">
                                    {description}
                                </p>
                            </div>
                        </button>
                    );
                })}
            </div>

            {state.error && (
                <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                    {state.error}
                </p>
            )}

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={state.loading}
                    className="flex-1 rounded-xl border border-white/10 bg-transparent px-6 py-3.5 text-sm font-medium text-[#9B9B8E] hover:border-white/20 hover:text-[#F5F0E8] transition-colors disabled:opacity-40"
                >
                    ← Back
                </button>

                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={!allConsented || state.loading}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        allConsented && !state.loading
                            ? 'bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    {state.loading ? (
                        <span className="flex items-center justify-center gap-2">
                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Submitting…
                        </span>
                    ) : (
                        'Submit My Evaluation ✓'
                    )}
                </button>
            </div>

            <p className="mt-4 text-center text-[11px] text-white/20">
                By submitting, you confirm that all information provided is accurate and truthful.
            </p>
        </div>
    );
};

const SummaryRow: FC<{ label: string; value: string }> = ({ label, value }) => (
    <div className="flex items-center justify-between px-5 py-3">
        <dt className="text-xs text-[#9B9B8E]">{label}</dt>
        <dd className="text-xs font-medium text-[#F5F0E8] text-right max-w-[60%] truncate">{value}</dd>
    </div>
);

export default ConsentSubmit;
