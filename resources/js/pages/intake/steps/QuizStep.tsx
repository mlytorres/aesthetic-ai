import { useState, type FC } from 'react';
import { type QuizQuestion, type WizardState, type WizardAction } from '@/types/intake';

interface Props {
    questions: QuizQuestion[];
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    onNext: () => void;
    onBack: () => void;
}

const QuizStep: FC<Props> = ({ questions, state, dispatch, onNext, onBack }) => {
    const [activeIndex, setActiveIndex] = useState(0);

    const question = questions[activeIndex];

    if (!question) {
        // All questions answered — show submit button
        return (
            <QuizComplete
                total={questions.length}
                loading={state.loading}
                error={state.error}
                onSubmit={onNext}
                onBack={() => setActiveIndex(questions.length - 1)}
            />
        );
    }

    const currentAnswer = state.quizAnswers[question.key] ?? null;

    const advance = (): void => {
        // ── Boolean branching ─────────────────────────────────────────────────
        if (question.type === 'boolean') {
            if (currentAnswer === true && question.skipToOnTrue !== undefined) {
                setActiveIndex(question.skipToOnTrue);
                return;
            }
            if (currentAnswer === false && question.skipToOnFalse !== undefined) {
                setActiveIndex(question.skipToOnFalse);
                return;
            }
        }

        // ── Single-choice option-level branching ──────────────────────────────
        if (question.type === 'single' && question.options) {
            const selected = question.options.find((o) => o.value === currentAnswer);
            if (selected?.skipToEnd) {
                setActiveIndex(questions.length); // jump to completion screen
                return;
            }
            if (selected?.skipTo !== undefined) {
                setActiveIndex(selected.skipTo);
                return;
            }
        }

        // ── Wildcard / text branching ─────────────────────────────────────────
        if (question.skipToAlways !== undefined) {
            setActiveIndex(question.skipToAlways);
            return;
        }

        // ── Default: linear advance ───────────────────────────────────────────
        setActiveIndex((i) => i + 1);
    };

    const canAdvance =
        !question.required ||
        (question.type === 'multi'
            ? Array.isArray(currentAnswer) && (currentAnswer as string[]).length > 0
            : currentAnswer !== null && currentAnswer !== '');

    return (
        <div className="py-6">
            {/* Progress within quiz */}
            <div className="mb-6 flex items-center justify-between">
                <span className="text-xs font-medium text-[#9B9B8E]">
                    Question {activeIndex + 1} of {questions.length}
                </span>
                <div className="h-1 flex-1 mx-4 rounded-full bg-white/10 overflow-hidden">
                    <div
                        className="h-full rounded-full bg-[#C9A84C] transition-all duration-500"
                        style={{ width: `${((activeIndex + 1) / questions.length) * 100}%` }}
                    />
                </div>
            </div>

            {/* Question */}
            <h2 className="text-xl font-bold text-[#F5F0E8]">{question.label}</h2>

            <div className="mt-6">
                {question.type === 'single' && question.options && (
                    <SingleChoice
                        options={question.options}
                        value={typeof currentAnswer === 'string' ? currentAnswer : ''}
                        onChange={(v) =>
                            dispatch({ type: 'SET_QUIZ_ANSWER', key: question.key, value: v })
                        }
                    />
                )}

                {question.type === 'multi' && question.options && (
                    <MultiChoice
                        options={question.options}
                        value={Array.isArray(currentAnswer) ? currentAnswer : []}
                        onChange={(v) =>
                            dispatch({ type: 'SET_QUIZ_ANSWER', key: question.key, value: v })
                        }
                    />
                )}

                {question.type === 'boolean' && (
                    <BooleanChoice
                        value={typeof currentAnswer === 'boolean' ? currentAnswer : null}
                        onChange={(v) =>
                            dispatch({ type: 'SET_QUIZ_ANSWER', key: question.key, value: v })
                        }
                    />
                )}

                {question.type === 'text' && (
                    <textarea
                        className="mt-2 w-full rounded-xl border border-white/10 bg-[#111118] px-4 py-3 text-sm text-[#F5F0E8] placeholder-white/25 focus:border-[#C9A84C]/60 focus:outline-none focus:ring-1 focus:ring-[#C9A84C]/30 transition-colors resize-none"
                        rows={4}
                        placeholder={question.placeholder ?? 'Your answer…'}
                        value={typeof currentAnswer === 'string' ? currentAnswer : ''}
                        onChange={(e) =>
                            dispatch({
                                type: 'SET_QUIZ_ANSWER',
                                key: question.key,
                                value: e.target.value,
                            })
                        }
                    />
                )}
            </div>

            {state.error && (
                <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                    {state.error}
                </p>
            )}

            {/* Navigation */}
            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={() => {
                        if (activeIndex === 0) {
                            onBack();
                        } else {
                            setActiveIndex((i) => i - 1);
                        }
                    }}
                    className="flex-1 rounded-xl border border-white/10 bg-transparent px-6 py-3.5 text-sm font-medium text-[#9B9B8E] transition-colors hover:border-white/20 hover:text-[#F5F0E8]"
                >
                    ← Back
                </button>

                <button
                    type="button"
                    onClick={advance}
                    disabled={!canAdvance}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        canAdvance
                            ? 'bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    Next →
                </button>
            </div>
        </div>
    );
};

// ─── Sub-components ───────────────────────────────────────────────────────────

interface SingleChoiceProps {
    options: { value: string; label: string }[];
    value: string;
    onChange: (v: string) => void;
}

const SingleChoice: FC<SingleChoiceProps> = ({ options, value, onChange }) => (
    <div className="space-y-2">
        {options.map((opt) => {
            const isSelected = opt.value === value;
            return (
                <button
                    key={opt.value}
                    type="button"
                    onClick={() => onChange(opt.value)}
                    className={[
                        'w-full rounded-xl border px-5 py-3.5 text-left text-sm font-medium transition-all duration-150',
                        isSelected
                            ? 'border-[#C9A84C] bg-[#C9A84C]/10 text-[#C9A84C]'
                            : 'border-white/10 bg-[#111118] text-[#F5F0E8] hover:border-white/25',
                    ].join(' ')}
                >
                    {opt.label}
                </button>
            );
        })}
    </div>
);

interface MultiChoiceProps {
    options: { value: string; label: string }[];
    value: string[];
    onChange: (v: string[]) => void;
}

const MultiChoice: FC<MultiChoiceProps> = ({ options, value, onChange }) => {
    const toggle = (v: string): void => {
        onChange(value.includes(v) ? value.filter((x) => x !== v) : [...value, v]);
    };

    return (
        <div className="space-y-2">
            <p className="mb-3 text-xs text-[#9B9B8E]">Select all that apply</p>
            {options.map((opt) => {
                const isSelected = value.includes(opt.value);
                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => toggle(opt.value)}
                        className={[
                            'flex w-full items-center gap-3 rounded-xl border px-5 py-3.5 text-left text-sm font-medium transition-all duration-150',
                            isSelected
                                ? 'border-[#C9A84C] bg-[#C9A84C]/10 text-[#C9A84C]'
                                : 'border-white/10 bg-[#111118] text-[#F5F0E8] hover:border-white/25',
                        ].join(' ')}
                    >
                        <span
                            className={[
                                'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                                isSelected ? 'border-[#C9A84C] bg-[#C9A84C]' : 'border-white/30',
                            ].join(' ')}
                        >
                            {isSelected && (
                                <svg className="h-2.5 w-2.5 text-[#0A0A0F]" viewBox="0 0 10 10" fill="none">
                                    <path
                                        d="M1.5 5l2.5 2.5 4.5-4.5"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            )}
                        </span>
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
};

interface BooleanChoiceProps {
    value: boolean | null;
    onChange: (v: boolean) => void;
}

const BooleanChoice: FC<BooleanChoiceProps> = ({ value, onChange }) => (
    <div className="flex gap-3">
        {(['Yes', 'No'] as const).map((label) => {
            const boolVal = label === 'Yes';
            const isSelected = value === boolVal;
            return (
                <button
                    key={label}
                    type="button"
                    onClick={() => onChange(boolVal)}
                    className={[
                        'flex-1 rounded-xl border py-4 text-sm font-semibold transition-all duration-150',
                        isSelected
                            ? 'border-[#C9A84C] bg-[#C9A84C]/10 text-[#C9A84C]'
                            : 'border-white/10 bg-[#111118] text-[#F5F0E8] hover:border-white/25',
                    ].join(' ')}
                >
                    {label}
                </button>
            );
        })}
    </div>
);

interface QuizCompleteProps {
    total: number;
    loading: boolean;
    error: string | null;
    onSubmit: () => void;
    onBack: () => void;
}

const QuizComplete: FC<QuizCompleteProps> = ({ total, loading, error, onSubmit, onBack }) => (
    <div className="py-6 text-center">
        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-[#C9A84C]/15 ring-1 ring-[#C9A84C]/30">
            <svg className="h-8 w-8 text-[#C9A84C]" viewBox="0 0 24 24" fill="none">
                <path
                    d="M5 13l4 4L19 7"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </div>

        <h2 className="text-xl font-bold text-[#F5F0E8]">All {total} questions answered</h2>
        <p className="mt-2 text-sm text-[#9B9B8E]">
            Ready to continue? We'll now guide you through uploading your photos.
        </p>

        {error && (
            <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                {error}
            </p>
        )}

        <div className="mt-8 flex gap-3">
            <button
                type="button"
                onClick={onBack}
                className="flex-1 rounded-xl border border-white/10 bg-transparent px-6 py-3.5 text-sm font-medium text-[#9B9B8E] hover:border-white/20 hover:text-[#F5F0E8] transition-colors"
            >
                ← Review
            </button>
            <button
                type="button"
                onClick={onSubmit}
                disabled={loading}
                className={[
                    'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                    loading
                        ? 'cursor-not-allowed bg-white/10 text-white/30'
                        : 'bg-[#C9A84C] text-[#0A0A0F] hover:bg-[#a8883e] active:scale-[0.98]',
                ].join(' ')}
            >
                {loading ? 'Saving…' : 'Continue to Photos →'}
            </button>
        </div>
    </div>
);

export default QuizStep;
