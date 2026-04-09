<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcedureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug'           => $this->slug,
            'label'          => $this->label,
            'category'       => $this->category,

            // Transform DB array [{type, required, guide_label}] → {required: [], optional: []}
            'photo_protocol' => $this->transformPhotoProtocol($this->photo_protocol ?? []),

            'quiz' => $this->whenLoaded('quizDefinitions', function () {
                $active = $this->quizDefinitions->firstWhere('is_active', true);

                if (! $active) {
                    return null;
                }

                // Build question ID → array index map for branch resolution
                $questionIds = collect($active->questions)
                    ->pluck('id')
                    ->flip() // [id => 0-based index]
                    ->all();

                return [
                    'id'        => $active->id,
                    'version'   => $active->version,
                    // Normalise each question so the frontend contract is met:
                    //  - DB stores 'id', frontend expects 'key'
                    //  - DB stores 'select'/'multiselect', frontend expects 'single'/'multi'
                    'questions' => collect($active->questions)
                        ->map(function (array $q) use ($questionIds) {
                            return [
                                'key'         => $q['id'],
                                'label'       => $q['label'],
                                'type'        => $this->normaliseQuestionType($q['type']),
                                'options'     => isset($q['options'])
                                    ? collect($q['options'])
                                        ->map(fn (array $o) => [
                                            'value' => $o['value'],
                                            'label' => $o['label'],
                                        ])
                                        ->values()
                                        ->all()
                                    : null,
                                'required'    => $q['required'] ?? true,
                                'placeholder' => $q['placeholder'] ?? null,
                                // Branching: pre-resolve question IDs → array indices so the frontend
                                // can use simple integer jumps without knowing question IDs.
                                'skipToOnTrue'   => $this->resolveBranchIndex($q, 'true', $questionIds),
                                'skipToOnFalse'  => $this->resolveBranchIndex($q, 'false', $questionIds),
                                'skipToAlways'   => $this->resolveBranchIndex($q, '*', $questionIds),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            }),
        ];
    }

    /**
     * Convert DB photo_protocol array into the shape the frontend wizard expects:
     *   { required: PhotoType[], optional: PhotoType[] }
     *
     * @param  array<int, array{type: string, required: bool, guide_label?: string}>  $protocol
     * @return array{required: string[], optional: string[]}
     */
    private function transformPhotoProtocol(array $protocol): array
    {
        $required = [];
        $optional = [];

        foreach ($protocol as $step) {
            if ($step['required'] ?? false) {
                $required[] = $step['type'];
            } else {
                $optional[] = $step['type'];
            }
        }

        return [
            'required' => $required,
            'optional' => $optional,
        ];
    }

    /**
     * Map DB question type values → frontend type literals.
     *
     * DB values  : 'select', 'multiselect', 'boolean', 'text'
     * Frontend   : 'single', 'multi',       'boolean', 'text'
     */
    private function normaliseQuestionType(string $type): string
    {
        return match ($type) {
            'select'      => 'single',
            'multiselect' => 'multi',
            default       => $type,  // 'boolean', 'text' pass through unchanged
        };
    }

    /**
     * Resolve a branch key ('true', 'false', '*') to the target question's array index.
     *
     * @param  array<string, mixed>  $question  Raw question from JSONB
     * @param  string  $branchKey             'true' | 'false' | '*'
     * @param  array<string, int>  $questionIds  Map of question ID → array index
     */
    private function resolveBranchIndex(array $question, string $branchKey, array $questionIds): ?int
    {
        $nextId = $question['branches'][$branchKey]['next'] ?? null;
        if ($nextId === null) {
            return null;
        }
        return $questionIds[$nextId] ?? null;
    }
}
