import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CheckCircle,
    XCircle,
    ChevronRight,
    Layers,
} from 'lucide-react';
import Heading from '@/components/heading';


// ── Types ─────────────────────────────────────────────────────────────────────

interface QuizSummary {
    id: string;
    version: number;
    is_active: boolean;
    question_count: number;
    updated_at: string | null;
}

interface ProcedureRow {
    slug: string;
    label: string;
    category: string;
    active: boolean;
    quiz: QuizSummary[];
}

interface Props {
    procedures: ProcedureRow[];
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function categoryLabel(cat: string): string {
    return cat === 'face' ? 'Face' : 'Body';
}

function categoryColor(cat: string): string {
    return cat === 'face'
        ? 'bg-violet-500/10 text-violet-400 border-violet-500/20'
        : 'bg-sky-500/10 text-sky-400 border-sky-500/20';
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function QuizzesIndex({ procedures }: Props) {
    const withQuiz = procedures.filter((p) => p.quiz.some((q) => q.is_active));
    const withoutQuiz = procedures.filter(
        (p) => !p.quiz.some((q) => q.is_active),
    );

    return (
        <>
            <Head title="Quiz Editor — Admin" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Heading
                        title="Quiz Editor"
                        description="Manage the intake quiz for each procedure. Changes apply globally to all clinics."
                    />
                </div>

                {/* Stats row */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        {
                            label: 'Total Procedures',
                            value: procedures.length,
                            color: 'text-foreground',
                        },
                        {
                            label: 'With Active Quiz',
                            value: withQuiz.length,
                            color: 'text-emerald-400',
                        },
                        {
                            label: 'Missing Quiz',
                            value: withoutQuiz.length,
                            color:
                                withoutQuiz.length > 0
                                    ? 'text-amber-400'
                                    : 'text-muted-foreground',
                        },
                    ].map((s) => (
                        <div
                            key={s.label}
                            className="rounded-lg border border-sidebar-border/50 bg-card p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {s.label}
                            </p>
                            <p className={`mt-1 text-3xl font-bold ${s.color}`}>
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-lg border border-sidebar-border/50 bg-card">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-sidebar-border/50 bg-sidebar/30">
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                    Procedure
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                    Category
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                    Quiz Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                    Questions
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">
                                    Last Updated
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/30">
                            {procedures.map((proc) => {
                                const active = proc.quiz.find(
                                    (q) => q.is_active,
                                );

                                return (
                                    <tr
                                        key={proc.slug}
                                        className="transition-colors hover:bg-sidebar/20"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-2">
                                                <BookOpen className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                {proc.label}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${categoryColor(proc.category)}`}
                                            >
                                                {categoryLabel(proc.category)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {active ? (
                                                <div className="flex items-center gap-1.5 text-emerald-400">
                                                    <CheckCircle className="h-4 w-4" />
                                                    <span>
                                                        Active (v
                                                        {active.version})
                                                    </span>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-1.5 text-amber-400">
                                                    <XCircle className="h-4 w-4" />
                                                    <span>No quiz</span>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {active ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <Layers className="h-3.5 w-3.5" />
                                                    {active.question_count}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {active?.updated_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/admin/quizzes/${proc.slug}`}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-[#0E9E8E] hover:text-[#0E9E8E]/80"
                                            >
                                                Edit
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
