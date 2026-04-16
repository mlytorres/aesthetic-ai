import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Plus,
    Trash2,
    ChevronDown,
    ChevronUp,
    Save,
    CheckCircle,
    Clock,
    AlertCircle,
    History,
} from 'lucide-react';
import { useState, useCallback } from 'react';
import Heading from '@/components/heading';

import { Button } from '@/components/ui/button';

// ── Types ─────────────────────────────────────────────────────────────────────

type QuestionType = 'text' | 'boolean' | 'select' | 'multiselect';

interface QuizOption {
    value: string;
    label: string;
}

interface BranchTarget {
    next?: string;
}

interface Question {
    id: string;
    type: QuestionType;
    label: string;
    required: boolean;
    options?: QuizOption[];
    branches: Record<string, BranchTarget>;
}

interface QuizVersion {
    id: string;
    version: number;
    is_active: boolean;
    question_count: number;
    updated_at: string | null;
}

interface QuizDefinition {
    id: string;
    version: number;
    is_active: boolean;
    questions: Question[];
    updated_at: string | null;
}

interface ProcedureInfo {
    slug: string;
    label: string;
    category: string;
}

interface Props {
    procedure: ProcedureInfo;
    activeQuiz: QuizDefinition | null;
    allVersions: QuizVersion[];
}

interface FlashProps extends PageProps {
    flash: { success?: string; error?: string };
}

// ── Constants ─────────────────────────────────────────────────────────────────

const UNIVERSAL_KEYS = ['q_timeline', 'q_budget', 'q_concerns', 'q_referral'];

const TYPE_LABELS: Record<QuestionType, string> = {
    text: 'Free text',
    boolean: 'Yes / No',
    select: 'Single choice',
    multiselect: 'Multiple choice',
};

const TYPE_COLORS: Record<QuestionType, string> = {
    text: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
    boolean: 'bg-orange-500/10 text-orange-400 border-orange-500/20',
    select: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
    multiselect: 'bg-violet-500/10 text-violet-400 border-violet-500/20',
};

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeBlankQuestion(): Question {
    return {
        id: `q_${Date.now()}`,
        type: 'select',
        label: '',
        required: true,
        options: [{ value: '', label: '' }],
        branches: {},
    };
}

function needsOptions(type: QuestionType): boolean {
    return type === 'select' || type === 'multiselect';
}

// ── Sub-components ────────────────────────────────────────────────────────────

function FlashMessage() {
    const { flash } = usePage<FlashProps>().props;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div
            className={`flex items-center gap-2 rounded-lg border px-4 py-3 text-sm ${
                flash?.success
                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                    : 'border-red-500/30 bg-red-500/10 text-red-400'
            }`}
        >
            {flash?.success ? (
                <CheckCircle className="h-4 w-4 shrink-0" />
            ) : (
                <AlertCircle className="h-4 w-4 shrink-0" />
            )}
            {flash?.success ?? flash?.error}
        </div>
    );
}

function OptionEditor({
    options,
    onChange,
}: {
    options: QuizOption[];
    onChange: (opts: QuizOption[]) => void;
}) {
    const update = (idx: number, field: keyof QuizOption, val: string) => {
        const next = options.map((o, i) =>
            i === idx ? { ...o, [field]: val } : o,
        );
        onChange(next);
    };

    const add = () => onChange([...options, { value: '', label: '' }]);

    const remove = (idx: number) =>
        onChange(options.filter((_, i) => i !== idx));

    return (
        <div className="mt-2 space-y-1.5">
            {options.map((opt, idx) => (
                <div key={idx} className="flex items-center gap-2">
                    <input
                        type="text"
                        placeholder="value"
                        value={opt.value}
                        onChange={(e) => update(idx, 'value', e.target.value)}
                        className="w-28 rounded border border-sidebar-border/50 bg-background px-2 py-1 font-mono text-xs focus:border-[#0E9E8E]/50 focus:outline-none"
                    />
                    <input
                        type="text"
                        placeholder="Display label"
                        value={opt.label}
                        onChange={(e) => update(idx, 'label', e.target.value)}
                        className="flex-1 rounded border border-sidebar-border/50 bg-background px-2 py-1 text-xs focus:border-[#0E9E8E]/50 focus:outline-none"
                    />
                    <button
                        type="button"
                        onClick={() => remove(idx)}
                        disabled={options.length <= 1}
                        className="text-muted-foreground hover:text-red-400 disabled:opacity-30"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </div>
            ))}
            <button
                type="button"
                onClick={add}
                className="mt-1 flex items-center gap-1 text-xs text-[#0E9E8E] hover:text-[#0E9E8E]/80"
            >
                <Plus className="h-3 w-3" /> Add option
            </button>
        </div>
    );
}

function QuestionCard({
    question,
    index,
    total,
    onChange,
    onRemove,
    onMove,
}: {
    question: Question;
    index: number;
    total: number;
    onChange: (q: Question) => void;
    onRemove: () => void;
    onMove: (dir: 'up' | 'down') => void;
}) {
    const [expanded, setExpanded] = useState(true);
    const isUniversal = UNIVERSAL_KEYS.includes(question.id);

    const setField = <K extends keyof Question>(key: K, value: Question[K]) => {
        onChange({ ...question, [key]: value });
    };

    const handleTypeChange = (type: QuestionType) => {
        const next: Question = { ...question, type };

        if (needsOptions(type) && !next.options?.length) {
            next.options = [{ value: '', label: '' }];
        }

        if (!needsOptions(type)) {
            delete next.options;
        }

        onChange(next);
    };

    return (
        <div
            className={`rounded-lg border ${isUniversal ? 'border-[#0E9E8E]/30 bg-[#0E9E8E]/5' : 'border-sidebar-border/50 bg-card'}`}
        >
            {/* Card header */}
            <div className="flex items-center gap-3 px-4 py-3">
                {/* Reorder */}
                <div className="flex shrink-0 flex-col gap-0.5">
                    <button
                        type="button"
                        onClick={() => onMove('up')}
                        disabled={index === 0}
                        className="text-muted-foreground hover:text-foreground disabled:opacity-20"
                    >
                        <ChevronUp className="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onMove('down')}
                        disabled={index === total - 1}
                        className="text-muted-foreground hover:text-foreground disabled:opacity-20"
                    >
                        <ChevronDown className="h-3.5 w-3.5" />
                    </button>
                </div>

                {/* Index */}
                <span className="w-5 shrink-0 font-mono text-xs text-muted-foreground">
                    {index + 1}
                </span>

                {/* ID badge */}
                <span
                    className={`inline-flex items-center rounded px-1.5 py-0.5 font-mono text-xs ${isUniversal ? 'bg-[#0E9E8E]/20 text-[#0E9E8E]' : 'bg-sidebar/50 text-muted-foreground'}`}
                >
                    {question.id}
                    {isUniversal && (
                        <span className="ml-1 text-[10px]">universal</span>
                    )}
                </span>

                {/* Type badge */}
                <span
                    className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs ${TYPE_COLORS[question.type]}`}
                >
                    {TYPE_LABELS[question.type]}
                </span>

                {/* Required */}
                <span
                    className={`text-xs ${question.required ? 'text-foreground' : 'text-muted-foreground'}`}
                >
                    {question.required ? 'required' : 'optional'}
                </span>

                <div className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                    {question.label || <em className="opacity-40">no label</em>}
                </div>

                {/* Actions */}
                <div className="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setExpanded((v) => !v)}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        {expanded ? (
                            <ChevronUp className="h-4 w-4" />
                        ) : (
                            <ChevronDown className="h-4 w-4" />
                        )}
                    </button>
                    {!isUniversal && (
                        <button
                            type="button"
                            onClick={onRemove}
                            className="text-muted-foreground hover:text-red-400"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>

            {/* Expanded body */}
            {expanded && (
                <div className="space-y-4 border-t border-sidebar-border/30 px-4 py-4">
                    {/* ID + Type + Required row */}
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="mb-1 block text-xs text-muted-foreground">
                                Question ID
                            </label>
                            <input
                                type="text"
                                value={question.id}
                                readOnly={isUniversal}
                                onChange={(e) => setField('id', e.target.value)}
                                className={`w-full rounded border border-sidebar-border/50 bg-background px-2.5 py-1.5 font-mono text-sm focus:border-[#0E9E8E]/50 focus:outline-none ${isUniversal ? 'cursor-not-allowed opacity-60' : ''}`}
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs text-muted-foreground">
                                Type
                            </label>
                            <select
                                value={question.type}
                                disabled={isUniversal}
                                onChange={(e) =>
                                    handleTypeChange(
                                        e.target.value as QuestionType,
                                    )
                                }
                                className={`w-full rounded border border-sidebar-border/50 bg-background px-2.5 py-1.5 text-sm focus:border-[#0E9E8E]/50 focus:outline-none ${isUniversal ? 'cursor-not-allowed opacity-60' : ''}`}
                            >
                                {(
                                    Object.entries(TYPE_LABELS) as [
                                        QuestionType,
                                        string,
                                    ][]
                                ).map(([val, lbl]) => (
                                    <option key={val} value={val}>
                                        {lbl}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs text-muted-foreground">
                                Required
                            </label>
                            <select
                                value={question.required ? 'true' : 'false'}
                                onChange={(e) =>
                                    setField(
                                        'required',
                                        e.target.value === 'true',
                                    )
                                }
                                className="w-full rounded border border-sidebar-border/50 bg-background px-2.5 py-1.5 text-sm focus:border-[#0E9E8E]/50 focus:outline-none"
                            >
                                <option value="true">Required</option>
                                <option value="false">Optional</option>
                            </select>
                        </div>
                    </div>

                    {/* Label */}
                    <div>
                        <label className="mb-1 block text-xs text-muted-foreground">
                            Question label (shown to patient)
                        </label>
                        <input
                            type="text"
                            value={question.label}
                            readOnly={isUniversal}
                            onChange={(e) => setField('label', e.target.value)}
                            placeholder="e.g. What are your primary goals for this procedure?"
                            className={`w-full rounded border border-sidebar-border/50 bg-background px-2.5 py-1.5 text-sm focus:border-[#0E9E8E]/50 focus:outline-none ${isUniversal ? 'cursor-not-allowed opacity-60' : ''}`}
                        />
                    </div>

                    {/* Options */}
                    {needsOptions(question.type) && (
                        <div>
                            <label className="mb-1 block text-xs text-muted-foreground">
                                Answer options
                            </label>
                            {isUniversal ? (
                                <div className="space-y-1">
                                    {question.options?.map((opt, i) => (
                                        <div
                                            key={i}
                                            className="flex gap-2 text-xs opacity-60"
                                        >
                                            <span className="w-24 truncate font-mono">
                                                {opt.value}
                                            </span>
                                            <span>{opt.label}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <OptionEditor
                                    options={question.options ?? []}
                                    onChange={(opts) =>
                                        setField('options', opts)
                                    }
                                />
                            )}
                        </div>
                    )}

                    {/* Branches — show as read-only JSON for now */}
                    <div>
                        <label className="mb-1 block text-xs text-muted-foreground">
                            Branching logic{' '}
                            <span className="opacity-50">
                                (JSON — use * for unconditional, true/false for
                                boolean, or option value)
                            </span>
                        </label>
                        <textarea
                            rows={3}
                            value={JSON.stringify(question.branches, null, 2)}
                            onChange={(e) => {
                                try {
                                    const parsed = JSON.parse(
                                        e.target.value,
                                    ) as Record<string, BranchTarget>;
                                    setField('branches', parsed);
                                } catch {
                                    /* let user keep typing */
                                }
                            }}
                            className="w-full resize-y rounded border border-sidebar-border/50 bg-background px-2.5 py-1.5 font-mono text-xs focus:border-[#0E9E8E]/50 focus:outline-none"
                        />
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Examples:{' '}
                            <code className="opacity-70">
                                {'{"*":{"next":"q_timeline"}}'}
                            </code>{' '}
                            — always go to next question.{' '}
                            <code className="opacity-70">
                                {
                                    '{"true":{"next":"q_details"},"false":{"next":"q_timeline"}}'
                                }
                            </code>{' '}
                            — yes/no branch.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Version history sidebar ────────────────────────────────────────────────────

function VersionHistory({
    versions,
    procedureSlug,
}: {
    versions: QuizVersion[];
    procedureSlug: string;
}) {
    const activate = (id: string) => {
        if (!confirm('Switch the active quiz to this version?')) {
            return;
        }

        router.post(`/admin/quizzes/${procedureSlug}/versions/${id}/activate`);
    };

    return (
        <div className="space-y-3 rounded-lg border border-sidebar-border/50 bg-card p-4">
            <div className="flex items-center gap-2 text-sm font-medium">
                <History className="h-4 w-4 text-muted-foreground" />
                Version history
            </div>
            {versions.map((v) => (
                <div
                    key={v.id}
                    className={`flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs ${v.is_active ? 'border border-[#0E9E8E]/20 bg-[#0E9E8E]/10' : 'bg-sidebar/30'}`}
                >
                    <div>
                        <span className="font-medium">v{v.version}</span>
                        <span className="ml-2 text-muted-foreground">
                            {v.question_count} questions
                        </span>
                        {v.is_active && (
                            <span className="ml-2 text-[#0E9E8E]">
                                ● active
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2 text-muted-foreground">
                        {v.updated_at && (
                            <span>{v.updated_at.slice(0, 10)}</span>
                        )}
                        {!v.is_active && (
                            <button
                                type="button"
                                onClick={() => activate(v.id)}
                                className="font-medium text-[#0E9E8E] hover:text-[#0E9E8E]/80"
                            >
                                Activate
                            </button>
                        )}
                    </div>
                </div>
            ))}
            {versions.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    No versions yet.
                </p>
            )}
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function QuizShow({
    procedure,
    activeQuiz,
    allVersions,
}: Props) {
    const [questions, setQuestions] = useState<Question[]>(
        activeQuiz?.questions ?? [],
    );
    const [saving, setSaving] = useState(false);

    const updateQuestion = useCallback((idx: number, q: Question) => {
        setQuestions((prev) => prev.map((old, i) => (i === idx ? q : old)));
    }, []);

    const removeQuestion = useCallback((idx: number) => {
        setQuestions((prev) => prev.filter((_, i) => i !== idx));
    }, []);

    const moveQuestion = useCallback((idx: number, dir: 'up' | 'down') => {
        setQuestions((prev) => {
            const next = [...prev];
            const target = dir === 'up' ? idx - 1 : idx + 1;

            if (target < 0 || target >= next.length) {
                return prev;
            }

            [next[idx], next[target]] = [next[target], next[idx]];

            return next;
        });
    }, []);

    const addQuestion = () => {
        setQuestions((prev) => [...prev, makeBlankQuestion()]);
    };

    const handleSave = () => {
        setSaving(true);
        router.patch(
            `/admin/quizzes/${procedure.slug}`,
            { questions } as unknown as Record<string, any>,
            {
                onFinish: () => setSaving(false),
            },
        );
    };

    const universalCount = questions.filter((q) =>
        UNIVERSAL_KEYS.includes(q.id),
    ).length;
    const missingUniversal = UNIVERSAL_KEYS.filter(
        (k) => !questions.some((q) => q.id === k),
    );

    return (
        <>
            <Head title={`Quiz: ${procedure.label} — Admin`} />

            <div className="space-y-6">
                {/* Breadcrumb + title */}
                <div className="flex items-center gap-3">
                    <Link
                        href="/admin/quizzes"
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <Heading
                        title={`Quiz: ${procedure.label}`}
                        description={`${procedure.category === 'face' ? 'Face' : 'Body'} procedure · editing active quiz`}
                    />
                </div>

                <FlashMessage />

                {/* Missing universal keys warning */}
                {missingUniversal.length > 0 && (
                    <div className="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-400">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p className="font-medium">
                                Missing universal quiz keys
                            </p>
                            <p className="mt-0.5 text-xs opacity-80">
                                The following required keys are absent:{' '}
                                <span className="font-mono">
                                    {missingUniversal.join(', ')}
                                </span>
                                . LeadScoringService expects all four universal
                                questions to be present.
                            </p>
                        </div>
                    </div>
                )}

                {/* Two-column layout */}
                <div className="grid grid-cols-[1fr_280px] items-start gap-6">
                    {/* Questions editor */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                {questions.length} question
                                {questions.length !== 1 ? 's' : ''} ·{' '}
                                {universalCount} universal (shared scoring keys)
                            </p>
                            <Button
                                type="button"
                                onClick={handleSave}
                                disabled={saving}
                                className="gap-2 bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                            >
                                <Save className="h-4 w-4" />
                                {saving ? 'Saving…' : 'Save (new version)'}
                            </Button>
                        </div>

                        {questions.length === 0 && (
                            <div className="rounded-lg border border-dashed border-sidebar-border/50 px-6 py-10 text-center text-sm text-muted-foreground">
                                No questions yet. Add the first question below.
                            </div>
                        )}

                        {questions.map((q, idx) => (
                            <QuestionCard
                                key={q.id + idx}
                                question={q}
                                index={idx}
                                total={questions.length}
                                onChange={(updated) =>
                                    updateQuestion(idx, updated)
                                }
                                onRemove={() => removeQuestion(idx)}
                                onMove={(dir) => moveQuestion(idx, dir)}
                            />
                        ))}

                        <button
                            type="button"
                            onClick={addQuestion}
                            className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-sidebar-border/50 px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-[#0E9E8E]/40 hover:text-[#0E9E8E]"
                        >
                            <Plus className="h-4 w-4" />
                            Add question
                        </button>

                        {/* Save button at bottom too */}
                        {questions.length > 3 && (
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    onClick={handleSave}
                                    disabled={saving}
                                    className="gap-2 bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                                >
                                    <Save className="h-4 w-4" />
                                    {saving ? 'Saving…' : 'Save (new version)'}
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Info card */}
                        <div className="space-y-3 rounded-lg border border-sidebar-border/50 bg-card p-4 text-sm">
                            <p className="font-medium">About this quiz</p>
                            <div className="space-y-1.5 text-xs text-muted-foreground">
                                <p>
                                    Saving creates a{' '}
                                    <strong className="text-foreground">
                                        new version
                                    </strong>{' '}
                                    and deactivates the previous one. Old
                                    versions are kept for audit.
                                </p>
                                <p>
                                    Universal keys (
                                    <code className="text-[#0E9E8E]">
                                        q_timeline
                                    </code>
                                    ,{' '}
                                    <code className="text-[#0E9E8E]">
                                        q_budget
                                    </code>
                                    ,{' '}
                                    <code className="text-[#0E9E8E]">
                                        q_concerns
                                    </code>
                                    ,{' '}
                                    <code className="text-[#0E9E8E]">
                                        q_referral
                                    </code>
                                    ) are required for lead scoring to work
                                    correctly.
                                </p>
                                <p>
                                    Branching logic is stored as JSON. Use{' '}
                                    <code>*</code> for unconditional next,
                                    option values for conditional branches.
                                </p>
                            </div>
                            {activeQuiz && (
                                <div className="flex items-center gap-1.5 border-t border-sidebar-border/30 pt-2 text-xs text-muted-foreground">
                                    <Clock className="h-3.5 w-3.5" />
                                    Last saved:{' '}
                                    {activeQuiz.updated_at?.slice(0, 10) ?? '—'}
                                </div>
                            )}
                        </div>

                        <VersionHistory
                            versions={allVersions}
                            procedureSlug={procedure.slug}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
