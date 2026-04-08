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
}
