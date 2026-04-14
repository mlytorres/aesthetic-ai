<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuizDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizDefinition>
 */
class QuizDefinitionFactory extends Factory
{
    protected $model = QuizDefinition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'version' => 1,
            'is_active' => true,
            'questions' => [
                [
                    'id' => 'q_concerns',
                    'type' => 'multiselect',
                    'label' => 'What are your primary goals?',
                    'required' => true,
                    'options' => [
                        ['value' => 'volume', 'label' => 'More volume'],
                        ['value' => 'lift',   'label' => 'Lift and reshape'],
                    ],
                    'branches' => [],
                ],
                [
                    'id' => 'q_timeline',
                    'type' => 'select',
                    'label' => 'What is your timeline?',
                    'required' => true,
                    'options' => [
                        ['value' => 'asap',     'label' => 'As soon as possible'],
                        ['value' => '3_months', 'label' => 'Within 3 months'],
                    ],
                    'branches' => ['*' => ['next' => 'q_budget']],
                ],
                [
                    'id' => 'q_budget',
                    'type' => 'select',
                    'label' => 'What is your approximate budget?',
                    'required' => true,
                    'options' => [
                        ['value' => 'under_10k', 'label' => 'Under $10,000'],
                        ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                    ],
                    'branches' => ['*' => ['next' => 'q_referral']],
                ],
                [
                    'id' => 'q_referral',
                    'type' => 'select',
                    'label' => 'How did you hear about us?',
                    'required' => false,
                    'options' => [
                        ['value' => 'instagram', 'label' => 'Instagram'],
                        ['value' => 'google',    'label' => 'Google'],
                    ],
                    'branches' => [],
                ],
            ],
        ];
    }
}
