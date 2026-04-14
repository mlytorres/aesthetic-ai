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

            // Transform DB array [{type, required, guide_label}] → {required: PhotoSlot[], optional: PhotoSlot[], category}
            'photo_protocol' => $this->transformPhotoProtocol($this->photo_protocol ?? [], $this->category),

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
     * Convert DB photo_protocol array into rich slot objects the frontend wizard uses.
     *
     * DB shape  : [{type, required, guide_label}]
     * Output    : {required: PhotoSlot[], optional: PhotoSlot[], category: string}
     *
     * Each PhotoSlot carries its own label and tip so the frontend never needs
     * a static lookup table — every slot is self-describing.
     *
     * @param  array<int, array{type: string, required: bool, guide_label?: string}>  $protocol
     * @param  string|null  $category  'face' | 'body'
     * @return array{required: array<int, array{type: string, label: string, tip: string}>, optional: array<int, array{type: string, label: string, tip: string}>, category: string}
     */
    private function transformPhotoProtocol(array $protocol, ?string $category = null): array
    {
        $required = [];
        $optional = [];

        foreach ($protocol as $step) {
            $slot = [
                'type'  => $step['type'],
                'label' => $this->labelForType($step['type'], $step['guide_label'] ?? null),
                'tip'   => $step['guide_label'] ?? $this->defaultTipForType($step['type']),
            ];

            if ($step['required'] ?? false) {
                $required[] = $slot;
            } else {
                $optional[] = $slot;
            }
        }

        return [
            'required' => $required,
            'optional' => $optional,
            'category' => $category ?? 'face',
        ];
    }

    /**
     * Human-readable label for a photo type.
     * The guide_label from the seeder is used as the tip (instruction); the label
     * is a short title shown in the slot header.
     */
    private function labelForType(string $type, ?string $guideLabel = null): string
    {
        return match ($type) {
            'front'         => 'Front View',
            'left_profile'  => 'Left Profile',
            'right_profile' => 'Right Profile',
            'back'          => 'Rear View',
            'left_side'     => 'Left Side',
            'right_side'    => 'Right Side',
            'abdomen_front' => 'Abdomen — Front',
            'abdomen_side'  => 'Abdomen — Side',
            'chest_front'   => 'Chest — Front',
            'eyes_closed'   => 'Eyes Closed',
            default         => $guideLabel ? ucwords(str_replace('_', ' ', $type)) : 'Additional Photo',
        };
    }

    /**
     * Fallback tip when guide_label is not set in the seeder.
     */
    private function defaultTipForType(string $type): string
    {
        return match ($type) {
            'front'         => 'Face the camera directly. Neutral expression, hair pulled back.',
            'left_profile'  => 'Turn 90° to your left. Keep chin level.',
            'right_profile' => 'Turn 90° to your right. Keep chin level.',
            'back'          => 'Turn to face away from the camera. Natural posture.',
            'left_side'     => 'Turn 90° to your left. Full body, natural posture.',
            'right_side'    => 'Turn 90° to your right. Full body, natural posture.',
            'abdomen_front' => 'Focus on the abdominal area. Form-fitting clothing or bare.',
            'abdomen_side'  => 'Side view of the abdominal area. Natural posture.',
            'chest_front'   => 'Front view of the chest area.',
            'eyes_closed'   => 'Close your eyes gently. Face the camera directly.',
            default         => 'Any additional angle relevant to your procedure.',
        };
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
