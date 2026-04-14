<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global lookup table for supported procedures.
 * No tenant_id — shared configuration managed by AestheticAI.
 */
class Procedure extends Model
{
    use HasFactory;

    protected $primaryKey = 'slug';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'slug',
        'label',
        'category',
        'photo_protocol',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'photo_protocol' => 'array',
            'active'         => 'boolean',
        ];
    }

    public function quizDefinitions(): HasMany
    {
        return $this->hasMany(QuizDefinition::class, 'procedure_slug', 'slug');
    }

    public function activeQuiz(): ?QuizDefinition
    {
        return $this->quizDefinitions()->where('is_active', true)->first();
    }

    /**
     * Returns the photo type that should be used as the primary input for AI simulation.
     *
     * Derived from the first required slot in the photo_protocol so it stays in sync
     * with the seeder automatically — no separate DB column needed.
     *
     * Face procedures  → 'front'  (face-forward shot)
     * Body full-body   → 'front'  (standing front shot)
     * Breast           → 'chest_front'
     * Abdomen          → 'front'  (full body used for proportion context)
     */
    public function simulationPhotoType(): string
    {
        $protocol = $this->photo_protocol ?? [];

        // Find the first required slot — that's the most diagnostically important view.
        foreach ($protocol as $slot) {
            if ($slot['required'] ?? false) {
                return $slot['type'];
            }
        }

        return 'front';
    }
}
