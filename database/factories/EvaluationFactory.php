<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    public function definition(): array
    {
        // Ensure 'rhinoplasty' procedure row exists — evaluations require a valid slug FK.
        Procedure::firstOrCreate(
            ['slug' => 'rhinoplasty'],
            [
                'label' => 'Rhinoplasty',
                'category' => 'face',
                'photo_protocol' => json_encode(['required' => ['front', 'left_profile', 'right_profile'], 'optional' => []]),
                'active' => true,
            ],
        );

        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'procedure_slug' => 'rhinoplasty',
            'status' => Evaluation::STATUS_SUBMITTED,
            'quiz_answers' => [
                'q_timeline' => 'asap',
                'q_budget' => '15k_25k',
                'q_concerns' => ['tip', 'bridge'],
                'q_referral' => 'google',
                'q_prior_surgery' => false,
                'q_breathing' => false,
            ],
            'analysis_data' => [],
        ];
    }

    /**
     * Evaluation with fully populated AI analysis data.
     */
    public function withAnalysis(): static
    {
        return $this->state(fn () => [
            'status' => Evaluation::STATUS_COMPLETE,
            'lead_score' => 78,
            'priority' => Evaluation::PRIORITY_HIGH,
            'analysis_data' => [
                'proportions' => [
                    'overall_harmony' => 72,
                    'facial_thirds' => ['upper' => 33.2, 'middle' => 33.5, 'lower' => 33.3],
                    'nasal_symmetry' => ['deviation_mm' => 1.2, 'symmetry_score' => 88],
                    '_avg_photo_quality' => 75,
                ],
                'recommendations' => [
                    ['category' => 'tip_refinement', 'confidence' => 'high', 'note' => 'Tip projection within normal range.'],
                ],
            ],
        ]);
    }
}
