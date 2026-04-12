<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\QuizDefinition;
use Illuminate\Database\Seeder;

/**
 * Seeds the global procedures table and quiz definitions for all 5 MVP procedures.
 * Run automatically via DatabaseSeeder.
 *
 * Quiz design principles:
 *  - All quizzes share q_timeline, q_budget, q_concerns, q_referral keys so
 *    LeadScoringService works universally without procedure-specific branching.
 *  - Branching uses [] for linear flow, ['*' => ['next' => 'id']] for explicit
 *    jumps, and ['true'/'false' => [...]] for boolean branches.
 *  - 7–9 questions per procedure to keep mobile UX fast.
 */
class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Procedures ───────────────────────────────────────────────────────

        $procedures = [
            [
                'slug' => 'rhinoplasty',
                'label' => 'Rhinoplasty',
                'category' => 'face',
                'photo_protocol' => [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Face forward, neutral expression, good lighting'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Turn left 90° — full profile view'],
                    ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Turn right 90° — full profile view'],
                ],
                'active' => true,
            ],
            [
                'slug' => 'bbl',
                'label' => 'Brazilian Butt Lift',
                'category' => 'body',
                'photo_protocol' => [
                    ['type' => 'front',        'required' => true,  'guide_label' => 'Full body front view, form-fitting clothing'],
                    ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left side profile, full body'],
                    ['type' => 'additional',   'required' => true,  'guide_label' => 'Rear view, full body'],
                    ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right side profile, full body'],
                ],
                'active' => true,
            ],
            [
                'slug' => 'lipo_360',
                'label' => 'Liposuction 360',
                'category' => 'body',
                'photo_protocol' => [
                    ['type' => 'front',        'required' => true, 'guide_label' => 'Full body front view'],
                    ['type' => 'left_profile', 'required' => true, 'guide_label' => 'Left side profile'],
                    ['type' => 'right_profile', 'required' => true, 'guide_label' => 'Right side profile'],
                    ['type' => 'additional',   'required' => true, 'guide_label' => 'Rear view'],
                ],
                'active' => true,
            ],
            [
                'slug' => 'breast_augmentation',
                'label' => 'Breast Augmentation',
                'category' => 'body',
                'photo_protocol' => [
                    ['type' => 'front',        'required' => true,  'guide_label' => 'Front view, neutral position'],
                    ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left profile view'],
                    ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right profile view'],
                ],
                'active' => true,
            ],
            [
                'slug' => 'facelift',
                'label' => 'Facelift',
                'category' => 'face',
                'photo_protocol' => [
                    ['type' => 'front',        'required' => true, 'guide_label' => 'Face forward, relaxed expression'],
                    ['type' => 'left_profile', 'required' => true, 'guide_label' => 'Left profile'],
                    ['type' => 'right_profile', 'required' => true, 'guide_label' => 'Right profile'],
                ],
                'active' => true,
            ],
        ];

        // ─── All Additional Procedures ────────────────────────────────────────
        $newProcedures = [
            // Body sculpting
            ['slug' => 'tummy_tuck',             'label' => 'Tummy Tuck',              'category' => 'body'],
            ['slug' => 'abdominal_etching',       'label' => 'Abdominal Etching',       'category' => 'body'],
            ['slug' => 'mommy_makeover',          'label' => 'Mommy Makeover',          'category' => 'body'],
            ['slug' => 'skinny_bbl',              'label' => 'Skinny BBL',              'category' => 'body'],
            ['slug' => 'j_plasma',                'label' => 'J Plasma',                'category' => 'body'],
            ['slug' => 'arm_lipo_lift',           'label' => 'Arm Lipo & Lift',         'category' => 'body'],
            ['slug' => 'arm_thigh_lift',          'label' => 'Arm & Thigh Lift',        'category' => 'body'],
            ['slug' => 'back_liposuction_lift',   'label' => 'Back Liposuction & Lift', 'category' => 'body'],
            ['slug' => 'axillary_liposuction',    'label' => 'Axillary Liposuction',    'category' => 'body'],
            ['slug' => 'reverse_bbl',             'label' => 'Reverse BBL',             'category' => 'body'],
            ['slug' => 'liposuction',             'label' => 'Liposuction',             'category' => 'body'],
            ['slug' => 'scar_revision',           'label' => 'Scar Revision',           'category' => 'body'],
            // Breast
            ['slug' => 'breast_lift',             'label' => 'Breast Lift',             'category' => 'body'],
            ['slug' => 'breast_reduction',        'label' => 'Breast Reduction',        'category' => 'body'],
            ['slug' => 'gynecomastia',            'label' => 'Gynecomastia Correction', 'category' => 'body'],
            ['slug' => 'labiaplasty',             'label' => 'Labiaplasty',             'category' => 'body'],
            // Face & neck
            ['slug' => 'face_and_neck_lift',      'label' => 'Face and Neck Lift',      'category' => 'face'],
            ['slug' => 'chin_lipo',               'label' => 'Chin Lipo',               'category' => 'face'],
            ['slug' => 'eyelid_surgery',          'label' => 'Eyelid Surgery',          'category' => 'face'],
            ['slug' => 'bichectomy',              'label' => 'Bichectomy',              'category' => 'face'],
            ['slug' => 'otoplasty',               'label' => 'Otoplasty',               'category' => 'face'],
        ];

        // Photo protocol overrides for specific new procedures
        $photoProtocolOverrides = [
            'tummy_tuck' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Full body front, standing, form-fitting clothing'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left side profile, full body'],
                ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Right side profile, full body'],
                ['type' => 'additional',   'required' => false, 'guide_label' => 'Close-up of abdominal area'],
            ],
            'mommy_makeover' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Full body front, standing'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left side profile, full body'],
                ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Right side profile, full body'],
                ['type' => 'additional',   'required' => false, 'guide_label' => 'Rear view, full body'],
            ],
            'breast_lift' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Front view, neutral position'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left profile view'],
                ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right profile view'],
            ],
            'breast_reduction' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Front view, neutral position'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left profile view'],
                ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right profile view'],
            ],
            'skinny_bbl' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Full body front, form-fitting clothing'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left side profile'],
                ['type' => 'additional',   'required' => true,  'guide_label' => 'Rear view, full body'],
                ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right side profile'],
            ],
            'gynecomastia' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Chest front view, shirt off'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left chest profile'],
                ['type' => 'right_profile', 'required' => false, 'guide_label' => 'Right chest profile'],
            ],
            'eyelid_surgery' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Face forward, eyes open, neutral expression'],
                ['type' => 'left_profile', 'required' => false, 'guide_label' => 'Left profile'],
                ['type' => 'additional',   'required' => false, 'guide_label' => 'Eyes closed — upper lids visible'],
            ],
            'face_and_neck_lift' => [
                ['type' => 'front',        'required' => true,  'guide_label' => 'Face and neck forward, relaxed expression'],
                ['type' => 'left_profile', 'required' => true,  'guide_label' => 'Left profile — full neck visible'],
                ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Right profile — full neck visible'],
            ],
        ];

        foreach ($newProcedures as $newReq) {
            $procedures[] = [
                'slug' => $newReq['slug'],
                'label' => $newReq['label'],
                'category' => $newReq['category'],
                'photo_protocol' => $photoProtocolOverrides[$newReq['slug']] ?? [
                    ['type' => 'front',         'required' => true,  'guide_label' => 'Front view'],
                    ['type' => 'left_profile',  'required' => true,  'guide_label' => 'Left profile'],
                    ['type' => 'right_profile', 'required' => true,  'guide_label' => 'Right profile'],
                ],
                'active' => true,
            ];
        }

        foreach ($procedures as $data) {
            Procedure::updateOrCreate(['slug' => $data['slug']], $data);
        }

        // ─── Quiz: Rhinoplasty ─────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'rhinoplasty', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which concerns are you looking to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'tip',        'label' => 'Nasal tip shape'],
                            ['value' => 'bridge',     'label' => 'Dorsal hump / bridge'],
                            ['value' => 'nostrils',   'label' => 'Nostril size or shape'],
                            ['value' => 'asymmetry',  'label' => 'Asymmetry'],
                            ['value' => 'projection', 'label' => 'Projection (too long or short)'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had a previous rhinoplasty?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_breathing'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Tell us briefly about your previous rhinoplasty.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_breathing']],
                    ],
                    [
                        'id' => 'q_breathing',
                        'type' => 'boolean',
                        'label' => 'Do you experience any breathing difficulties through your nose?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_skin_thickness']],
                    ],
                    [
                        'id' => 'q_skin_thickness',
                        'type' => 'select',
                        'label' => 'How would you describe your skin type?',
                        'required' => true,
                        'options' => [
                            ['value' => 'thin',   'label' => 'Thin / fine'],
                            ['value' => 'medium', 'label' => 'Medium'],
                            ['value' => 'thick',  'label' => 'Thick / oily'],
                        ],
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Brazilian Butt Lift ─────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'bbl', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'volume',       'label' => 'Increase volume and fullness'],
                            ['value' => 'lift',         'label' => 'Lift and reshape'],
                            ['value' => 'hourglass',    'label' => 'Achieve an hourglass silhouette'],
                            ['value' => 'proportions',  'label' => 'Improve overall body proportions'],
                            ['value' => 'asymmetry',    'label' => 'Correct asymmetry'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_donor_areas',
                        'type' => 'multiselect',
                        'label' => 'Which areas would you like to use as donor sites for fat harvesting?',
                        'required' => true,
                        'options' => [
                            ['value' => 'abdomen',     'label' => 'Abdomen'],
                            ['value' => 'flanks',      'label' => 'Flanks / love handles'],
                            ['value' => 'back',        'label' => 'Back / bra rolls'],
                            ['value' => 'thighs',      'label' => 'Inner or outer thighs'],
                            ['value' => 'not_sure',    'label' => 'Not sure — surgeon\'s recommendation'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_weight_stable',
                        'type' => 'boolean',
                        'label' => 'Has your weight been stable for the past 6 or more months?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_timeline'],
                            'false' => ['next' => 'q_weight_note'],
                        ],
                    ],
                    [
                        'id' => 'q_weight_note',
                        'type' => 'text',
                        'label' => 'Weight stability is important for lasting BBL results. Please tell us more about your situation.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $20,000'],
                            ['value' => 'over_25k',  'label' => 'Over $20,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Liposuction 360 ─────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'lipo_360', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which areas are you looking to treat?',
                        'required' => true,
                        'options' => [
                            ['value' => 'upper_abdomen', 'label' => 'Upper abdomen'],
                            ['value' => 'lower_abdomen', 'label' => 'Lower abdomen / pouch'],
                            ['value' => 'flanks',        'label' => 'Flanks / love handles'],
                            ['value' => 'back',          'label' => 'Back / bra rolls'],
                            ['value' => 'inner_thighs',  'label' => 'Inner thighs'],
                            ['value' => 'outer_thighs',  'label' => 'Outer thighs'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_skin_laxity',
                        'type' => 'select',
                        'label' => 'How would you describe your skin elasticity in the treatment area(s)?',
                        'required' => true,
                        'options' => [
                            ['value' => 'excellent', 'label' => 'Excellent — firm and elastic'],
                            ['value' => 'mild',      'label' => 'Mild laxity — some looseness'],
                            ['value' => 'moderate',  'label' => 'Moderate laxity — noticeable looseness'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_weight_stable',
                        'type' => 'boolean',
                        'label' => 'Has your weight been stable for the past 12 months?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_timeline'],
                            'false' => ['next' => 'q_weight_note'],
                        ],
                    ],
                    [
                        'id' => 'q_weight_note',
                        'type' => 'text',
                        'label' => 'Liposuction works best when your weight is stable. Please tell us more about your situation.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Breast Augmentation ─────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_augmentation', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'size',        'label' => 'Increase size and volume'],
                            ['value' => 'shape',       'label' => 'Improve shape and projection'],
                            ['value' => 'restore',     'label' => 'Restore volume after pregnancy or weight loss'],
                            ['value' => 'asymmetry',   'label' => 'Correct asymmetry'],
                            ['value' => 'lift',        'label' => 'Achieve a lifted appearance'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_result_preference',
                        'type' => 'select',
                        'label' => 'What kind of result are you looking for?',
                        'required' => true,
                        'options' => [
                            ['value' => 'natural',    'label' => 'Natural enhancement — subtle change'],
                            ['value' => 'moderate',   'label' => 'Moderately fuller — noticeable but proportional'],
                            ['value' => 'significant', 'label' => 'Significantly fuller — dramatic improvement'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_size_goal',
                        'type' => 'select',
                        'label' => 'How much of a size increase are you considering?',
                        'required' => true,
                        'options' => [
                            ['value' => '1_cup',  'label' => '1 cup size'],
                            ['value' => '2_cups', 'label' => '2 cup sizes'],
                            ['value' => '3_plus', 'label' => '3 or more cup sizes'],
                            ['value' => 'unsure',  'label' => 'Not sure — surgeon\'s recommendation'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had a previous breast augmentation or breast implants?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_surgery_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_surgery_details',
                        'type' => 'text',
                        'label' => 'Please tell us about your previous augmentation (implant type, surgeon, any concerns).',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Facelift ────────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'facelift', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which concerns would you like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'jowls',         'label' => 'Jowling / lower face sagging'],
                            ['value' => 'neck',          'label' => 'Neck laxity / turkey neck'],
                            ['value' => 'nasolabial',    'label' => 'Deep nasolabial folds'],
                            ['value' => 'jaw_definition', 'label' => 'Loss of jaw definition'],
                            ['value' => 'overall_aging', 'label' => 'Overall facial aging'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_result_preference',
                        'type' => 'select',
                        'label' => 'What level of rejuvenation are you looking for?',
                        'required' => true,
                        'options' => [
                            ['value' => 'subtle',      'label' => 'Subtle and natural — refreshed appearance'],
                            ['value' => 'moderate',    'label' => 'Moderate — noticeably younger'],
                            ['value' => 'significant', 'label' => 'Significant transformation'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_smoker',
                        'type' => 'boolean',
                        'label' => 'Do you currently smoke, vape, or use tobacco products?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_smoker_note'],
                            'false' => ['next' => 'q_prior_surgery'],
                        ],
                    ],
                    [
                        'id' => 'q_smoker_note',
                        'type' => 'text',
                        'label' => 'Smoking significantly affects healing and outcomes. Please share any relevant context (e.g. planning to quit, occasional use).',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had any previous facial surgery (facelift, brow lift, eyelid surgery)?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_surgery_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_surgery_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe your previous facial surgery.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $15,000'],
                            ['value' => '10k_15k',   'label' => '$15,000 – $25,000'],
                            ['value' => '15k_25k',   'label' => '$25,000 – $40,000'],
                            ['value' => 'over_25k',  'label' => 'Over $40,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Tummy Tuck ──────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'tummy_tuck', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which concerns would you like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'excess_skin',   'label' => 'Excess loose skin on the abdomen'],
                            ['value' => 'muscle_repair', 'label' => 'Separated or weakened abdominal muscles'],
                            ['value' => 'belly_button',  'label' => 'Reshape or reposition belly button'],
                            ['value' => 'stretch_marks', 'label' => 'Stretch marks on lower abdomen'],
                            ['value' => 'overall_flat',  'label' => 'Overall flatter stomach contour'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_post_pregnancy',
                        'type' => 'boolean',
                        'label' => 'Is this related to changes from pregnancy or childbirth?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_future_pregnancy']],
                    ],
                    [
                        'id' => 'q_future_pregnancy',
                        'type' => 'boolean',
                        'label' => 'Are you planning to have more children in the future?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_diastasis']],
                    ],
                    [
                        'id' => 'q_diastasis',
                        'type' => 'boolean',
                        'label' => 'Have you been told or do you suspect you have diastasis recti (abdominal muscle separation)?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had any previous abdominal surgery (C-section, hernia repair, laparoscopy)?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_weight_stable'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe the previous abdominal surgery.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_weight_stable']],
                    ],
                    [
                        'id' => 'q_weight_stable',
                        'type' => 'boolean',
                        'label' => 'Has your weight been stable for the past 6 months or more?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Mommy Makeover ──────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'mommy_makeover', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which areas would you like to address in your Mommy Makeover?',
                        'required' => true,
                        'options' => [
                            ['value' => 'breast',  'label' => 'Breast (augmentation, lift, or both)'],
                            ['value' => 'abdomen', 'label' => 'Abdomen (tummy tuck)'],
                            ['value' => 'lipo',    'label' => 'Liposuction (flanks, waist, thighs)'],
                            ['value' => 'bbl',     'label' => 'Brazilian Butt Lift'],
                            ['value' => 'labia',   'label' => 'Labiaplasty'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_future_pregnancy',
                        'type' => 'boolean',
                        'label' => 'Are you planning to have more children in the future?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_breastfeeding']],
                    ],
                    [
                        'id' => 'q_breastfeeding',
                        'type' => 'boolean',
                        'label' => 'Are you currently breastfeeding or have you breastfed in the past 6 months?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_weight_stable']],
                    ],
                    [
                        'id' => 'q_weight_stable',
                        'type' => 'boolean',
                        'label' => 'Has your weight been stable for the past 6 months?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_timeline'],
                            'false' => ['next' => 'q_weight_note'],
                        ],
                    ],
                    [
                        'id' => 'q_weight_note',
                        'type' => 'text',
                        'label' => 'Mommy Makeover results are best when weight is stable. Please tell us more.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $15,000'],
                            ['value' => '10k_15k',   'label' => '$15,000 – $25,000'],
                            ['value' => '15k_25k',   'label' => '$25,000 – $40,000'],
                            ['value' => 'over_25k',  'label' => 'Over $40,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Breast Lift ─────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_lift', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'lift',        'label' => 'Lift and elevate the breast position'],
                            ['value' => 'reshape',     'label' => 'Improve overall shape and roundness'],
                            ['value' => 'areola',      'label' => 'Reduce or reshape the areola'],
                            ['value' => 'severe_droop', 'label' => 'Significant sagging (grade 2–3 ptosis)'],
                            ['value' => 'asymmetry',   'label' => 'Improve asymmetry between breasts'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_add_volume',
                        'type' => 'boolean',
                        'label' => 'Are you also interested in adding volume (implants or fat transfer) alongside the lift?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_breastfeeding']],
                    ],
                    [
                        'id' => 'q_breastfeeding',
                        'type' => 'boolean',
                        'label' => 'Have you breastfed in the past 6 months?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had a previous breast surgery?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe your previous breast surgery.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Breast Reduction ────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_reduction', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which symptoms or concerns are you experiencing?',
                        'required' => true,
                        'options' => [
                            ['value' => 'back_pain',        'label' => 'Chronic neck, back, or shoulder pain'],
                            ['value' => 'shoulder_grooving', 'label' => 'Bra strap shoulder grooving'],
                            ['value' => 'skin_rash',        'label' => 'Skin rash or irritation under breasts'],
                            ['value' => 'posture',          'label' => 'Poor posture due to breast weight'],
                            ['value' => 'activity_limit',   'label' => 'Difficulty exercising or physical activity'],
                            ['value' => 'aesthetic',        'label' => 'Aesthetic — proportion and appearance'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_breastfeeding',
                        'type' => 'boolean',
                        'label' => 'Have you breastfed in the past 6 months or are you currently breastfeeding?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had any previous breast surgery?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe your previous breast surgery.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Quiz: Skinny BBL ──────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'skinny_bbl', 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for Skinny BBL?',
                        'required' => true,
                        'options' => [
                            ['value' => 'subtle_projection', 'label' => 'Subtle increase in projection'],
                            ['value' => 'lift',              'label' => 'Lift and reshape'],
                            ['value' => 'athletic',          'label' => 'Athletic, toned look'],
                            ['value' => 'waist_definition',  'label' => 'Define the waist'],
                            ['value' => 'proportions',       'label' => 'Improve overall body proportions'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_bmi_range',
                        'type' => 'select',
                        'label' => 'How would you describe your current body type?',
                        'required' => true,
                        'options' => [
                            ['value' => 'low',    'label' => 'Very lean / athletic — little body fat'],
                            ['value' => 'medium', 'label' => 'Slender — moderate amount of body fat'],
                            ['value' => 'average', 'label' => 'Average frame with some body fat to harvest'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_donor_areas',
                        'type' => 'multiselect',
                        'label' => 'Which areas would you like to use as donor sites for fat harvesting?',
                        'required' => true,
                        'options' => [
                            ['value' => 'abdomen',  'label' => 'Abdomen'],
                            ['value' => 'flanks',   'label' => 'Flanks / love handles'],
                            ['value' => 'back',     'label' => 'Back'],
                            ['value' => 'thighs',   'label' => 'Inner or outer thighs'],
                            ['value' => 'not_sure', 'label' => 'Not sure — surgeon\'s recommendation'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_weight_stable',
                        'type' => 'boolean',
                        'label' => 'Has your weight been stable for the past 6 months or more?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_timeline'],
                            'false' => ['next' => 'q_weight_note'],
                        ],
                    ],
                    [
                        'id' => 'q_weight_note',
                        'type' => 'text',
                        'label' => 'Weight stability is especially important for lean patients. Please share more.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $20,000'],
                            ['value' => 'over_25k',  'label' => 'Over $20,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );

        // ─── Generate Generic Quizzes for remaining procedures ─────────────────

        $mvpSlugs = [
            'rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift',
            'tummy_tuck', 'mommy_makeover', 'breast_lift', 'breast_reduction', 'skinny_bbl',
        ];

        foreach ($procedures as $data) {
            if (! in_array($data['slug'], $mvpSlugs)) {
                $this->buildGenericQuiz($data['slug']);
            }
        }

        $this->command->info('✅ Procedures and quiz definitions completely seeded.');
    }

    private function buildGenericQuiz(string $slug): void
    {
        QuizDefinition::updateOrCreate(
            ['procedure_slug' => $slug, 'is_active' => true],
            [
                'version' => 1,
                'is_active' => true,
                'questions' => [
                    [
                        'id' => 'q_concerns',
                        'type' => 'text',
                        'label' => 'Please briefly describe your primary concerns and goals for this procedure.',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you previously had surgery in this specific area?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe your previous surgery.',
                        'required' => false,
                        'branches' => ['*' => ['next' => 'q_timeline']],
                    ],
                    [
                        'id' => 'q_timeline',
                        'type' => 'select',
                        'label' => 'What is your timeline for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'asap',        'label' => 'As soon as possible'],
                            ['value' => '3_months',    'label' => 'Within 3 months'],
                            ['value' => '6_months',    'label' => 'Within 6 months'],
                            ['value' => 'researching', 'label' => 'Still researching'],
                        ],
                        'branches' => ['*' => ['next' => 'q_budget']],
                    ],
                    [
                        'id' => 'q_budget',
                        'type' => 'select',
                        'label' => 'What is your approximate budget for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'under_10k', 'label' => 'Under $10,000'],
                            ['value' => '10k_15k',   'label' => '$10,000 – $15,000'],
                            ['value' => '15k_25k',   'label' => '$15,000 – $25,000'],
                            ['value' => 'over_25k',  'label' => 'Over $25,000'],
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
                            ['value' => 'google',    'label' => 'Google Search'],
                            ['value' => 'referral',  'label' => 'Friend or family'],
                            ['value' => 'tiktok',    'label' => 'TikTok'],
                            ['value' => 'other',     'label' => 'Other'],
                        ],
                        'branches' => [],
                    ],
                ],
            ]
        );
    }
}
