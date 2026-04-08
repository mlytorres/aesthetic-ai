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
            'photo_protocol' => $this->photo_protocol,
            'quiz'           => $this->whenLoaded('quizDefinitions', function () {
                $active = $this->quizDefinitions->firstWhere('is_active', true);
                return $active ? [
                    'id'        => $active->id,
                    'version'   => $active->version,
                    'questions' => $active->questions,
                ] : null;
            }),
        ];
    }
}
