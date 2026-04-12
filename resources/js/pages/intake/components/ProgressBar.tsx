import type {FC} from 'react';
import type {WizardStep} from '@/types/intake';

const STEPS: { key: WizardStep; label: string }[] = [
    { key: 'procedure', label: 'Procedure' },
    { key: 'quiz',      label: 'About You' },
    { key: 'photos',    label: 'Photos' },
    { key: 'contact',   label: 'Contact' },
    { key: 'consent',   label: 'Confirm' },
];

interface Props {
    currentStep: WizardStep;
}

const ProgressBar: FC<Props> = ({ currentStep }) => {
    const currentIndex = STEPS.findIndex((s) => s.key === currentStep);

    return (
        <div className="w-full px-6 pt-8 pb-4">
            {/* Step dots + connector line */}
            <div className="relative flex items-center justify-between">
                {/* Background line */}
                <div className="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-white/10" />

                {/* Progress fill */}
                <div
                    className="absolute top-1/2 left-0 h-px -translate-y-1/2 bg-[#0E9E8E] transition-all duration-500"
                    style={{
                        width:
                            currentIndex === 0
                                ? '0%'
                                : `${(currentIndex / (STEPS.length - 1)) * 100}%`,
                    }}
                />

                {STEPS.map((step, index) => {
                    const isDone    = index < currentIndex;
                    const isCurrent = index === currentIndex;

                    return (
                        <div key={step.key} className="relative flex flex-col items-center gap-2">
                            {/* Dot */}
                            <div
                                className={[
                                    'flex h-7 w-7 items-center justify-center rounded-full border transition-all duration-300 z-10',
                                    isDone
                                        ? 'border-[#0E9E8E] bg-[#0E9E8E]'
                                        : isCurrent
                                        ? 'border-[#0E9E8E] bg-[#111118]'
                                        : 'border-white/20 bg-[#111118]',
                                ].join(' ')}
                            >
                                {isDone ? (
                                    <svg className="h-3.5 w-3.5 text-[#0A0A0F]" viewBox="0 0 12 12" fill="none">
                                        <path
                                            d="M2 6l3 3 5-5"
                                            stroke="currentColor"
                                            strokeWidth="1.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                ) : (
                                    <span
                                        className={[
                                            'text-[10px] font-semibold',
                                            isCurrent ? 'text-[#0E9E8E]' : 'text-white/30',
                                        ].join(' ')}
                                    >
                                        {index + 1}
                                    </span>
                                )}
                            </div>

                            {/* Label */}
                            <span
                                className={[
                                    'absolute top-9 text-[10px] font-medium whitespace-nowrap transition-colors duration-300',
                                    isCurrent
                                        ? 'text-[#F5F0E8]'
                                        : isDone
                                        ? 'text-[#0E9E8E]'
                                        : 'text-white/30',
                                ].join(' ')}
                            >
                                {step.label}
                            </span>
                        </div>
                    );
                })}
            </div>

            {/* Spacer for labels */}
            <div className="h-7" />
        </div>
    );
};

export default ProgressBar;
