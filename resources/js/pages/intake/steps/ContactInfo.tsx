import { Turnstile } from '@marsidev/react-turnstile';
import type { FC, ChangeEvent } from 'react';
import type { TranslationKey } from '@/i18n/translations';
import type {
    WizardState,
    WizardAction,
    ContactFormData,
} from '@/types/intake';

type TFn = (
    key: TranslationKey,
    vars?: Record<string, string | number>,
) => string;

interface Props {
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    t: TFn;
    turnstileSiteKey: string;
    leadCapturePosition: 'beginning' | 'end';
    onNext: () => void;
    onBack: () => void;
}

const ContactInfo: FC<Props> = ({
    state,
    dispatch,
    t,
    turnstileSiteKey,
    leadCapturePosition,
    onNext,
    onBack,
}) => {
    const { contact } = state;

    const set =
        (field: keyof ContactFormData) =>
        (e: ChangeEvent<HTMLInputElement>): void => {
            dispatch({ type: 'SET_CONTACT', field, value: e.target.value });
        };

    const isInputValid =
        contact.name.trim().length > 0 &&
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email);

    // Turnstile is only strictly required here if we are submitting the lead immediately.
    const isBotValidationReady =
        leadCapturePosition === 'end' || !!state.turnstileToken;

    const canContinue = isInputValid && isBotValidationReady && !state.loading;

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[var(--intake-fg)]">
                {t('contact.title')}
            </h2>
            <p className="mt-2 text-sm text-[var(--intake-muted)]">
                {t('contact.subtitle')}
            </p>

            <div className="mt-8 space-y-4">
                {/* Name */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-widest text-[var(--intake-muted)] uppercase">
                        {t('contact.name_label')}{' '}
                        <span className="text-[#0E9E8E]">*</span>
                    </label>
                    <input
                        type="text"
                        autoComplete="name"
                        placeholder={t('contact.name_placeholder')}
                        value={contact.name}
                        onChange={set('name')}
                        className="w-full rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] px-4 py-3 text-sm text-[var(--intake-fg)] placeholder-white/25 transition-colors focus:border-[#0E9E8E]/60 focus:ring-1 focus:ring-[#0E9E8E]/30 focus:outline-none"
                    />
                </div>

                {/* Email */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-widest text-[var(--intake-muted)] uppercase">
                        {t('contact.email_label')}{' '}
                        <span className="text-[#0E9E8E]">*</span>
                    </label>
                    <input
                        type="email"
                        autoComplete="email"
                        placeholder={t('contact.email_placeholder')}
                        value={contact.email}
                        onChange={set('email')}
                        className="w-full rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] px-4 py-3 text-sm text-[var(--intake-fg)] placeholder-white/25 transition-colors focus:border-[#0E9E8E]/60 focus:ring-1 focus:ring-[#0E9E8E]/30 focus:outline-none"
                    />
                </div>

                {/* Phone */}
                <div>
                    <label className="mb-1.5 block text-xs font-semibold tracking-widest text-[var(--intake-muted)] uppercase">
                        {t('contact.phone_label')}{' '}
                        <span className="ml-1 font-normal tracking-normal text-white/30 normal-case">
                            ({t('consent.optional_badge')})
                        </span>
                    </label>
                    <input
                        type="tel"
                        autoComplete="tel"
                        placeholder={t('contact.phone_placeholder')}
                        value={contact.phone}
                        onChange={set('phone')}
                        className="w-full rounded-xl border border-[var(--intake-border)] bg-[var(--intake-surface)] px-4 py-3 text-sm text-[var(--intake-fg)] placeholder-white/25 transition-colors focus:border-[#0E9E8E]/60 focus:ring-1 focus:ring-[#0E9E8E]/30 focus:outline-none"
                    />
                    <p className="mt-1 text-xs text-[var(--intake-muted)]">
                        {t('contact.phone_hint')}
                    </p>
                </div>
            </div>

            {/* Privacy note */}
            <div className="mt-5 flex items-start gap-3 rounded-xl border border-[var(--intake-border-xs)] bg-white/[0.02] px-4 py-3">
                <svg
                    className="mt-0.5 h-4 w-4 shrink-0 text-[#0E9E8E]"
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path
                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinejoin="round"
                    />
                </svg>
                <p className="text-xs leading-relaxed text-[var(--intake-muted)]">
                    {t('footer.hipaa_notice')}
                </p>
            </div>

            {state.error && (
                <p className="mt-4 rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    {state.error}
                </p>
            )}

            {/* Turnstile / Security widget — only shown if lead is captured early */}
            {leadCapturePosition === 'beginning' && (
                <div className="mt-8 flex min-h-[65px] justify-center">
                    <Turnstile
                        siteKey={turnstileSiteKey}
                        onSuccess={(token) =>
                            dispatch({ type: 'SET_TURNSTILE_TOKEN', token })
                        }
                        onError={() =>
                            dispatch({
                                type: 'SET_TURNSTILE_TOKEN',
                                token: null,
                            })
                        }
                        onExpire={() =>
                            dispatch({
                                type: 'SET_TURNSTILE_TOKEN',
                                token: null,
                            })
                        }
                        options={{ theme: 'dark' }}
                    />
                </div>
            )}

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex-1 rounded-xl border border-[var(--intake-border)] bg-transparent px-6 py-3.5 text-sm font-medium text-[var(--intake-muted)] transition-colors hover:border-[var(--intake-border-hover)] hover:text-[var(--intake-fg)]"
                >
                    {t('nav.back')}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    disabled={!canContinue}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        canContinue
                            ? 'bg-[#0E9E8E] text-[var(--intake-icon-on-teal)] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    {state.loading ? (
                        <span className="flex items-center justify-center gap-2">
                            <svg
                                className="h-4 w-4 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <circle
                                    className="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    strokeWidth="4"
                                />
                                <path
                                    className="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                />
                            </svg>
                            {t('nav.processing') || 'Processing...'}
                        </span>
                    ) : (
                        t('contact.continue_cta')
                    )}
                </button>
            </div>
        </div>
    );
};

export default ContactInfo;
