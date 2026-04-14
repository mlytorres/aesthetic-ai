<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\QuizDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuizAdminController extends Controller
{
    // ─── Index: all procedures with quiz status ───────────────────────────────

    public function index(): Response
    {
        $procedures = Procedure::with(['quizDefinitions' => fn ($q) => $q->orderByDesc('version')])
            ->orderByRaw("CASE WHEN category = 'face' THEN 1 ELSE 0 END")
            ->orderBy('label')
            ->get();

        return Inertia::render('admin/quizzes/index', [
            'procedures' => $procedures->map(fn (Procedure $p) => [
                'slug'     => $p->slug,
                'label'    => $p->label,
                'category' => $p->category,
                'active'   => $p->active,
                'quiz'     => $p->quizDefinitions->map(fn (QuizDefinition $q) => [
                    'id'             => $q->id,
                    'version'        => $q->version,
                    'is_active'      => $q->is_active,
                    'question_count' => count($q->questions ?? []),
                    'updated_at'     => $q->updated_at?->toDateString(),
                ])->values(),
            ]),
        ]);
    }

    // ─── Show / edit a single quiz definition ────────────────────────────────

    public function show(string $procedureSlug): Response
    {
        $procedure = Procedure::findOrFail($procedureSlug);

        $definitions = QuizDefinition::where('procedure_slug', $procedureSlug)
            ->orderByDesc('version')
            ->get();

        $active = $definitions->firstWhere('is_active', true);

        return Inertia::render('admin/quizzes/show', [
            'procedure'   => [
                'slug'     => $procedure->slug,
                'label'    => $procedure->label,
                'category' => $procedure->category,
            ],
            'activeQuiz'  => $active ? $this->serializeDefinition($active) : null,
            'allVersions' => $definitions->map(fn (QuizDefinition $q) => [
                'id'             => $q->id,
                'version'        => $q->version,
                'is_active'      => $q->is_active,
                'question_count' => count($q->questions ?? []),
                'updated_at'     => $q->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    // ─── Save updated questions for the active quiz ───────────────────────────
    //
    // The quiz_definitions table has a unique constraint on (procedure_slug, is_active),
    // which means at most ONE active and ONE inactive record per procedure.
    // Strategy: delete any existing archived row, archive the current active one,
    // then insert the new active version — preserving exactly one previous version.

    public function update(Request $request, string $procedureSlug): RedirectResponse
    {
        $procedure = Procedure::findOrFail($procedureSlug);

        $validated = $request->validate([
            'questions'                   => ['required', 'array', 'min:1'],
            'questions.*.id'              => ['required', 'string', 'max:64'],
            'questions.*.type'            => ['required', Rule::in(['text', 'boolean', 'select', 'multiselect'])],
            'questions.*.label'           => ['required', 'string', 'max:300'],
            'questions.*.required'        => ['required', 'boolean'],
            'questions.*.options'         => ['nullable', 'array'],
            'questions.*.options.*.value' => ['required_with:questions.*.options', 'string', 'max:100'],
            'questions.*.options.*.label' => ['required_with:questions.*.options', 'string', 'max:200'],
            'questions.*.branches'        => ['nullable', 'array'],
        ]);

        $existing = QuizDefinition::where('procedure_slug', $procedureSlug)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            $newVersion = $existing->version + 1;

            // Purge any existing archived record so the unique constraint allows archiving the current one.
            QuizDefinition::where('procedure_slug', $procedureSlug)
                ->where('is_active', false)
                ->delete();

            // Archive the current active version.
            $existing->update(['is_active' => false]);

            QuizDefinition::create([
                'procedure_slug' => $procedureSlug,
                'version'        => $newVersion,
                'is_active'      => true,
                'questions'      => $validated['questions'],
            ]);
        } else {
            QuizDefinition::create([
                'procedure_slug' => $procedureSlug,
                'version'        => 1,
                'is_active'      => true,
                'questions'      => $validated['questions'],
            ]);
        }

        return redirect()
            ->route('admin.quizzes.show', $procedureSlug)
            ->with('success', "Quiz for \"{$procedure->label}\" saved (new version created).");
    }

    // ─── Restore an archived version as the active one ───────────────────────
    //
    // The unique(procedure_slug, is_active) constraint means at most one row per
    // (slug, true) and one row per (slug, false). To swap active/archived we must
    // delete the archived record first (freeing the false slot), then flip the
    // active record to false, then insert the restored version as the new active.

    public function activate(Request $request, string $procedureSlug, string $definitionId): RedirectResponse
    {
        $procedure = Procedure::findOrFail($procedureSlug);

        $target = QuizDefinition::where('procedure_slug', $procedureSlug)
            ->findOrFail($definitionId);

        if ($target->is_active) {
            return redirect()->route('admin.quizzes.show', $procedureSlug);
        }

        // Snapshot attributes before the record is deleted.
        $restoredVersion   = $target->version;
        $restoredQuestions = $target->questions;

        // Step 1: delete the archived record, freeing the is_active=false slot.
        $target->delete();

        // Step 2: archive the currently-active record (slot is now free).
        QuizDefinition::where('procedure_slug', $procedureSlug)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Step 3: insert the restored version as the new active record.
        QuizDefinition::create([
            'procedure_slug' => $procedureSlug,
            'version'        => $restoredVersion,
            'is_active'      => true,
            'questions'      => $restoredQuestions,
        ]);

        return redirect()
            ->route('admin.quizzes.show', $procedureSlug)
            ->with('success', "Version {$restoredVersion} is now active for \"{$procedure->label}\".");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function serializeDefinition(QuizDefinition $q): array
    {
        return [
            'id'         => $q->id,
            'version'    => $q->version,
            'is_active'  => $q->is_active,
            'questions'  => $q->questions ?? [],
            'updated_at' => $q->updated_at?->toIso8601String(),
        ];
    }
}
