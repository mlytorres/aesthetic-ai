<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);
        $slug = Str::slug($label, '_');

        return [
            'slug' => $slug,
            'label' => ucwords($label),
            'category' => $this->faker->randomElement(['face', 'body']),
            'photo_protocol' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Face forward, neutral expression'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Turn left 90°'],
                ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Turn right 90°'],
            ],
            'active' => true,
        ];
    }
}
