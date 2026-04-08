<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\QuizDefinition;
use Illuminate\Database\Seeder;

/**
 * Seeds the global procedures table and MVP quiz definition for Rhinoplasty.
 * Run automatically via DatabaseSeeder.
 */
class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Procedures ───────────────────────────────────────────────────────

        $procedures = [
            [
                'slug'           => 'rhinoplasty',
                'label'          => 'Rhinoplasty',
                'category'       => 'face',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Face forward, neutral expression, good lighting'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Turn left 90° — full profile view'],
                    ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Turn right 90° — full profile view'],
                ],
                'active'         => true,
            ],
            [
                'slug'           => 'bbl',
                'label'          => 'Brazilian Butt Lift',
                'category'       => 'body',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Full body front view, form-fitting clothing'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Left side profile, full body'],
                    ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right side profile, full body'],
                    ['type' => 'additional',    'required' => true,  'guide_label' => 'Rear view, full body'],
                ],
                'active'         => true,
            ],
            [
                'slug'           => 'lipo_360',
                'label'          => 'Liposuction 360',
                'category'       => 'body',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true, 'guide_label' => 'Full body front view'],
                    ['type' => 'left_profile',  'required' => true, 'guide_label' => 'Left side profile'],
                    ['type' => 'right_profile', 'required' => true, 'guide_label' => 'Right side profile'],
                    ['type' => 'additional',    'required' => true, 'guide_label' => 'Rear view'],
                ],
                'active'         => true,
            ],
            [
                'slug'           => 'breast_augmentation',
                'label'          => 'Breast Augmentation',
                'category'       => 'body',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Front view, neutral position'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Left profile view'],
                    ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right profile view'],
                ],
                'active'         => true,
            ],
            [
                'slug'           => 'facelift',
                'label'          => 'Facelift',
                'category'       => 'face',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Face forward, relaxed expression'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Left profile'],
                    ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Right profile'],
                ],
                'active'         => true,
            ],
        ];

        foreach ($procedures as $data) {
            Procedure::updateOrCreate(['slug' => $data['slug']], $data);
        }

        // ─── MVP Quiz Definition: Rhinoplasty ─────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'rhinoplasty', 'is_active' => true],
            [
                'version'   => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id'       => 'q_concerns',
                        'type'     => 'multiselect',
                        'label'    => 'Which concerns are you looking to address?',
                        'required' => true,
                        'options'  => [
                            ['value' => 'tip',        'label' => 'Nasal tip shape'],
                            ['value' => 'bridge',     'label' => 'Dorsal hump / bridge'],
                            ['value' => 'nostrils',   'label' => 'Nostril size or shape'],
                            ['value' => 'asymmetry',  'label' => 'Asymmetry'],
                            ['value' => 'projection', 'label' => 'Projection (too long or short)'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id'       => 'q_prior_surgery',
                        'type'     => 'boolean',
                        'label'    => 'Have you had a previous rhinoplasty?',
                        'required' => true,
                        'branches' => [
                            'true'  => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_breathing'],
                        ],
                    ],
                    [
                        'id'       => 'q_prior_details',
                        'type'     => 'text',
                        'label'    => 'Tell us briefly about your previous rhinoplasty.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_breathing']],
                    ],
                    [
                        'id'       => 'q_breathing',
                        'type'     => 'boolean',
                        'label'    => 'Do you experience any breathing difficulties through your nose?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_skin_thickness']],
                    ],
                    [
                        'id'       => 'q_skin_thickness',
                        'type'     => 'select',
                        'label'    => 'How would you describe your skin type?',
                        'required' => true,
                        'options'  => [
                            ['value' => 'thin',   'label' => 'Thin / fine'],
                            ['value' => 'medium', 'label' => 'Medium'],
                            ['value' => 'thick',  'label' => 'Thick / oily'],
                        ],
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id'       => 'q_timeline',
                        'type'     => 'select',
                        'label'    => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options'  => [
                            ['value' => 'asap',          'label' => 'As soon as possible'],
                            ['value' => '3_months',      'label' => 'Within 3 months'],
                            ['value' => '6_months',      'label' => 'Within 6 months'],
                            ['value' => 'researching',   'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id'       => 'q_budget',
                        'type'     => 'select',
                        'label'    => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options'  => [
                            ['value' => 'under_10k',   'label' => 'Under $10,000'],
                            ['value' => '10k_15k',     'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',     'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',    'label' => 'Over $25,000'],
                        ],
                        'branches' => ['*' => ['next' => 'q_referral']],
                    ],
                    [
                        'id'       => 'q_referral',
                        'type'     => 'select',
                        'label'    => 'How did you hear about us?',
                        'required' => false,
                        'options'  => [
                            ['value' => 'instagram',  'label' => 'Instagram'],
                            ['value' => 'google',     'label' => 'Google Search'],
                            ['value' => 'referral',   'label' => 'Friend or family'],
                            ['value' => 'tiktok',     'label' => 'TikTok'],
                            ['value' => 'other',      'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        $this->command->info('✅ Procedures and Rhinoplasty quiz seeded.');
    }
}
