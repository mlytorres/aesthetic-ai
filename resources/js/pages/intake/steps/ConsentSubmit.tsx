import { Turnstile } from '@marsidev/react-turnstile';
import type {FC} from 'react';
import type {WizardState, WizardAction, ConsentFormData} from '@/types/intake';
import type { TranslationKey } from '@/i18n/translations';

type TFn = (key: TranslationKey, vars?: Record<string, string | number>) => string;

interface Props {
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    t: TFn;
    turnstileSiteKey: string;
    onSubmit: () => void;
    onBack: () => void;
}

const ConsentSubmit: FC<Props> = ({ state, dispatch, t, turnstileSiteKey, onSubmit, onBack }) => {
    const { consent, contact, turnstileToken } = state;

    const allConsented =
        consent.hipaa_acknowledged &&
        consent.terms_accepted &&
        consent.photo_use_consent &&
        !!turnstileToken;

    const toggle = (field: keyof ConsentFormData): void => {
        const current = consent[field] as boolean;
        dispatch({ type: 'SET_CONSENT', field, value: !current });
    };

    // Build consent items dynamically using translation keys
    const CONSENTS: { field: keyof ConsentFormData; labelKey: TranslationKey; descKey: TranslationKey; required: boolean }[] = [
        { field: 'hipaa_acknowledged', labelKey: 'consent.hipaa_label', descKey: 'consent.hipaa_text', required: true },
        { field: 'terms_accepted',     labelKey: 'consent.terms_label', descKey: 'consent.terms_text',  required: true },
        { field: 'photo_use_consent',  labelKey: 'consent.photo_label', descKey: 'consent.photo_text',  required: true },
        { field: 'opt_in_sms',         labelKey: 'consent.sms_label',   descKey: 'consent.sms_text',    required: false },
    ];

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[var(--intake-fg)]">{t('consent.title')}</h2>
            <p className="mt-2 text-sm text-[var(--intake-muted)]">
                {t('consent.subtitle')}
            </p>

            {/* Summary card */}
            <div className="mt-6 rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] overflow-hidden">
                <div className="px-5 py-3 border-b border-[var(--intake-border-xs)]">
                    <p className="text-xs font-semibold uppercase tracking-widest text-[#0E9E8E]">
                        {t('consent.summary_heading')}
                    </p>
                </div>

                <dl className="divide-y divide-white/5">
                    <SummaryRow label={t('consent.summary.procedure')} value={state.selectedProcedure?.label ?? '—'} />
                    <SummaryRow label={t('consent.summary.name')} value={contact.name || '—'} />
                    <SummaryRow label={t('consent.summary.email')} value={contact.email || '—'} />
                    {contact.phone && <SummaryRow label={t('consent.summary.phone')} value={contact.phone} />}
                    <SummaryRow
                        label={t('consent.summary.photos')}
                        value={`${state.photos.filter((p) => !p.uploading && !p.error).length}`}
                    />
                    <SummaryRow
                        label={t('consent.summary.quiz')}
                        value={`${Object.keys(state.quizAnswers).length}`}
                    />
                </dl>
            </div>

            {/* Consent checkboxes */}
            <div className="mt-6 space-y-4">
                <p className="text-xs font-semibold uppercase tracking-widest text-[var(--intake-muted)]">
                    {t('consent.required_heading')}
                </p>

                {CONSENTS.map(({ field, labelKey, descKey, required }) => {
                    const label = t(labelKey);
                    const description = t(descKey);
                    const checked = consent[field] as boolean;

                    return (
                        <button
                            key={field}
                            type="button"
                            onClick={() => toggle(field)}
                            className={[
                                'flex w-full items-start gap-4 rounded-xl border px-4 py-4 text-left transition-all duration-150',
                                checked
                                    ? 'border-[#0E9E8E]/30 bg-[#0E9E8E]/5'
                                    : 'border-[var(--intake-border)] bg-[var(--intake-surface)] hover:border-[var(--intake-border-hover)]',
                            ].join(' ')}
                        >
                            {/* Checkbox */}
                            <span
                                className={[
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border transition-all',
                                    checked ? 'border-[#0E9E8E] bg-[#0E9E8E]' : 'border-white/30 bg-transparent',
                                ].join(' ')}
                            >
                                {checked && (
                                    <svg className="h-3 w-3 text-[var(--intake-icon-on-teal)]" viewBox="0 0 12 12" fill="none">
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
                                    checked ? 'text-[var(--intake-fg)]' : 'text-[var(--intake-muted)]',
                                ].join(' ')}>
                                    {label}{' '}
                                    {required
                                        ? <span className="text-[#0E9E8E]">*</span>
                                        : <span className="text-[10px] font-normal text-[var(--intake-muted)] uppercase tracking-wide ml-1">{t('consent.optional_badge')}</span>
                                    }
                                </p>
                                <p className="mt-1 text-xs text-[var(--intake-muted)] leading-relaxed">
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

            {/* Turnstile / Security widget */}
            <div className="mt-6 flex justify-center min-h-[65px]">
                <Turnstile
                    siteKey={turnstileSiteKey}
                    onSuccess={(token) => dispatch({ type: 'SET_TURNSTILE_TOKEN', token })}
                    onError={() => dispatch({ type: 'SET_TURNSTILE_TOKEN', token: null })}
                    onExpire={() => dispatch({ type: 'SET_TURNSTILE_TOKEN', token: null })}
                    options={{ theme: 'dark' }}
                />
            </div>

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={state.loading}
                    className="flex-1 rounded-xl border border-[var(--intake-border)] bg-transparent px-6 py-3.5 text-sm font-medium text-[var(--intake-muted)] hover:border-[var(--intake-border-hover)] hover:text-[var(--intake-fg)] transition-colors disabled:opacity-40"
                >
                    {t('nav.back')}
                </button>

                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={!allConsented || state.loading}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        allConsented && !state.loading
                            ? 'bg-[#0E9E8E] text-[var(--intake-icon-on-teal)] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    {state.loading ? (
                        <span className="flex items-center justify-center gap-2">
                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {t('consent.submitting')}
                        </span>
                    ) : (
                        t('consent.submit_cta')
                    )}
                </button>
            </div>

            <p className="mt-4 text-center text-[11px] text-[var(--intake-muted-faint)]">
                {t('consent.disclaimer')}
            </p>
        </div>
    );
};

const SummaryRow: FC<{ label: string; value: string }> = ({ label, value }) => (
    <div className="flex items-center justify-between px-5 py-3">
        <dt className="text-xs text-[var(--intake-muted)]">{label}</dt>
        <dd className="text-xs font-medium text-[var(--intake-fg)] text-right max-w-[60%] truncate">{value}</dd>
    </div>
);

export default ConsentSubmit;
