import type { FC, ReactNode } from 'react';
import type { TranslationKey } from '@/i18n/translations';
import type { WizardStep } from '@/types/intake';
import ProgressBar from './ProgressBar';
import { TrustBadges } from '@/components/trust-badges';

type TFn = (
    key: TranslationKey,
    vars?: Record<string, string | number>,
) => string;

interface Props {
    clinicName: string;
    clinicLogo?: string;
    currentStep: WizardStep;
    children: ReactNode;
    hideHeader?: boolean;
    theme?: string;
    /** Optional hex color that overrides the default teal accent (#0E9E8E). */
    brandPrimary?: string | null;
    /** Optional font family that overrides the default. */
    brandFont?: string | null;
    /** Translation function from useTranslation. */
    t?: TFn;
}

const WizardShell: FC<Props> = ({
    clinicName,
    clinicLogo,
    currentStep,
    hideHeader = false,
    children,
    theme = 'luxury-dark',
    brandPrimary,
    brandFont,
    t,
}) => {
    // Build an inline style that overrides variables when brand overrides are set.
    const brandStyle = {
        ...(brandPrimary ? { '--intake-accent': brandPrimary } : {}),
        ...(brandFont ? { '--intake-font': brandFont } : {}),
    } as React.CSSProperties;

    return (
        <div
            className="flex min-h-screen flex-col bg-[var(--intake-bg)]"
            data-intake-theme={theme}
            style={{
                fontFamily: 'var(--intake-font)',
                ...brandStyle,
            }}
        >
            {/* Header */}
            {!hideHeader && (
                <header className="flex items-center justify-between border-b border-[var(--intake-border-xs)] px-6 py-4">
                    <div className="flex items-center gap-3">
                        {clinicLogo ? (
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 shrink-0 items-center justify-center rounded border border-white/10 bg-white px-2 py-1 shadow-sm">
                                    <img
                                        src={clinicLogo}
                                        alt={clinicName}
                                        className="h-full w-auto max-w-[120px] object-contain"
                                    />
                                </div>
                                <span className="hidden text-sm font-semibold tracking-wide text-[var(--intake-fg)] sm:block">
                                    {clinicName}
                                </span>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2">
                                {/* Wordmark fallback */}
                                <div className="flex h-7 w-7 items-center justify-center rounded-full bg-[#0E9E8E]/20 ring-1 ring-[#0E9E8E]/40">
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
            <footer className="border-t border-[var(--intake-border-xs)] px-4 py-5">
                <TrustBadges variant="dark" />
            </footer>
        </div>
    );
};

export default WizardShell;
