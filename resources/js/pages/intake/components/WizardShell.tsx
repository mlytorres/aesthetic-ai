import type {FC, ReactNode} from 'react';
import type {WizardStep} from '@/types/intake';
import ProgressBar from './ProgressBar';
import type { TranslationKey } from '@/i18n/translations';

type TFn = (key: TranslationKey, vars?: Record<string, string | number>) => string;

interface Props {
    clinicName: string;
    clinicLogo?: string;
    currentStep: WizardStep;
    children: ReactNode;
    hideHeader?: boolean;
    theme?: string;
    /** Optional hex color that overrides the default teal accent (#0E9E8E). */
    brandPrimary?: string | null;
    /** Translation function from useTranslation. */
    t?: TFn;
}

const WizardShell: FC<Props> = ({ clinicName, clinicLogo, currentStep, hideHeader = false, children, theme = 'luxury-dark', brandPrimary, t }) => {
    // Build an inline style that overrides --intake-accent when a brand color is set.
    const brandStyle = brandPrimary
        ? ({ '--intake-accent': brandPrimary } as React.CSSProperties)
        : undefined;

    return (
        <div className="flex min-h-screen flex-col bg-[var(--intake-bg)]" data-intake-theme={theme} style={brandStyle}>
            {/* Header */}
            {!hideHeader && (
            <header className="flex items-center justify-between border-b border-[var(--intake-border-xs)] px-6 py-4">
                <div className="flex items-center gap-3">
                    {clinicLogo ? (
                        <img
                            src={clinicLogo}
                            alt={clinicName}
                            className="h-8 w-auto object-contain"
                        />
                    ) : (
                        <div className="flex items-center gap-2">
                            {/* Wordmark fallback */}
                            <div className="h-7 w-7 rounded-full bg-[#0E9E8E]/20 ring-1 ring-[#0E9E8E]/40 flex items-center justify-center">
                                <span className="text-[10px] font-bold text-[#0E9E8E]">
                                    {clinicName.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <span className="text-sm font-semibold tracking-wide text-[var(--intake-fg)]">
                                {clinicName}
                            </span>
                        </div>
                    )}
                </div>

                {/* Powered-by badge */}
                <span className="text-[10px] font-medium tracking-widest text-[var(--intake-muted-faint)] uppercase">
                    {t ? t('footer.powered_by') : 'AI Evaluation'}
                </span>
            </header>
            )}

            {/* Progress */}
            <ProgressBar currentStep={currentStep} />

            {/* Content area */}
            <main className="flex flex-1 flex-col items-center px-4 pb-12">
                <div className="w-full max-w-lg">{children}</div>
            </main>

            {/* Footer */}
            <footer className="border-t border-[var(--intake-border-xs)] py-4 text-center">
                <p className="text-[11px] text-[var(--intake-muted-faint)]">
                    {t ? t('footer.hipaa_notice') : 'Your information is encrypted and protected under HIPAA.'}
                </p>
            </footer>
        </div>
    );
};

export default WizardShell;
