import type {FC, ReactNode} from 'react';
import type {WizardStep} from '@/types/intake';
import ProgressBar from './ProgressBar';

interface Props {
    clinicName: string;
    clinicLogo?: string;
    currentStep: WizardStep;
    children: ReactNode;
    hideHeader?: boolean;
}

const WizardShell: FC<Props> = ({ clinicName, clinicLogo, currentStep, hideHeader = false, children }) => {
    return (
        <div className="flex min-h-screen flex-col bg-[#0A0A0F]">
            {/* Header */}
            {!hideHeader && (
            <header className="flex items-center justify-between border-b border-white/5 px-6 py-4">
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
                            <span className="text-sm font-semibold tracking-wide text-[#F5F0E8]">
                                {clinicName}
                            </span>
                        </div>
                    )}
                </div>

                {/* Powered-by badge */}
                <span className="text-[10px] font-medium tracking-widest text-white/20 uppercase">
                    AI Evaluation
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
            <footer className="border-t border-white/5 py-4 text-center">
                <p className="text-[11px] text-white/20">
                    Your information is encrypted and protected under HIPAA.
                </p>
            </footer>
        </div>
    );
};

export default WizardShell;
