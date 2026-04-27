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
                    ['type' => 'front',        'required' => true,  'guide_label' => 'Full body front — form-fitting clothing, arms relaxed at sides'],
                    ['type' => 'left_side',    'required' => true,  'guide_label' => 'Left side — full body, natural posture'],
                    ['type' => 'back',         'required' => true,  'guide_label' => 'Rear view — full body, same clothing'],
                    ['type' => 'right_side',   'required' => false, 'guide_label' => 'Right side — full body, natural posture'],
                ],
                'active' => true,
            ],
            [
                'slug' => 'lipo_360',
                'label' => 'Liposuction 360',
                'category' => 'body',
                'photo_protocol' => [
                    ['type' => 'front',      'required' => true, 'guide_label' => 'Full body front — form-fitting clothing, arms relaxed'],
                    ['type' => 'left_side',  'required' => true, 'guide_label' => 'Left side — full body, natural posture'],
                    ['type' => 'right_side', 'required' => true, 'guide_label' => 'Right side — full body, natural posture'],
                    ['type' => 'back',       'required' => true, 'guide_label' => 'Rear view — full body, same clothing'],
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
                ['type' => 'front',         'required' => true,  'guide_label' => 'Full body front — standing, form-fitting clothing or swimsuit'],
                ['type' => 'left_side',     'required' => true,  'guide_label' => 'Left side — full body, natural posture'],
                ['type' => 'right_side',    'required' => true,  'guide_label' => 'Right side — full body, natural posture'],
                ['type' => 'abdomen_front', 'required' => false, 'guide_label' => 'Abdomen close-up — bare skin, relaxed posture'],
            ],
            'abdominal_etching' => [
                ['type' => 'front',         'required' => true,  'guide_label' => 'Full body front — standing, form-fitting clothing or bare'],
                ['type' => 'left_side',     'required' => true,  'guide_label' => 'Left side — full body, natural posture'],
                ['type' => 'abdomen_front', 'required' => true,  'guide_label' => 'Abdomen close-up — bare skin, relaxed posture'],
                ['type' => 'right_side',    'required' => false, 'guide_label' => 'Right side — full body, natural posture'],
            ],
            'mommy_makeover' => [
                ['type' => 'front',      'required' => true,  'guide_label' => 'Full body front — standing, form-fitting clothing'],
                ['type' => 'left_side',  'required' => true,  'guide_label' => 'Left side — full body, natural posture'],
                ['type' => 'right_side', 'required' => true,  'guide_label' => 'Right side — full body, natural posture'],
                ['type' => 'back',       'required' => false, 'guide_label' => 'Rear view — full body, same clothing'],
            ],
            'breast_lift' => [
                ['type' => 'chest_front',  'required' => true,  'guide_label' => 'Chest front — neutral position, no padded bra'],
                ['type' => 'left_side',    'required' => true,  'guide_label' => 'Left side — from waist up, natural posture'],
                ['type' => 'right_side',   'required' => false, 'guide_label' => 'Right side — from waist up, natural posture'],
            ],
            'breast_reduction' => [
                ['type' => 'chest_front',  'required' => true,  'guide_label' => 'Chest front — neutral position, no padded bra'],
                ['type' => 'left_side',    'required' => true,  'guide_label' => 'Left side — from waist up, natural posture'],
                ['type' => 'right_side',   'required' => false, 'guide_label' => 'Right side — from waist up, natural posture'],
            ],
            'breast_augmentation' => [
                ['type' => 'chest_front',  'required' => true,  'guide_label' => 'Chest front — neutral position, no padded bra'],
                ['type' => 'left_side',    'required' => true,  'guide_label' => 'Left side — from waist up, natural posture'],
                ['type' => 'right_side',   'required' => false, 'guide_label' => 'Right side — from waist up, natural posture'],
            ],
            'skinny_bbl' => [
                ['type' => 'front',      'required' => true,  'guide_label' => 'Full body front — form-fitting clothing, arms relaxed'],
                ['type' => 'left_side',  'required' => true,  'guide_label' => 'Left side — full body, natural posture'],
                ['type' => 'back',       'required' => true,  'guide_label' => 'Rear view — full body, same clothing'],
                ['type' => 'right_side', 'required' => false, 'guide_label' => 'Right side — full body, natural posture'],
            ],
            'gynecomastia' => [
                ['type' => 'chest_front', 'required' => true,  'guide_label' => 'Chest front — shirt off, neutral posture, arms at sides'],
                ['type' => 'left_side',   'required' => true,  'guide_label' => 'Left side — from waist up, natural posture'],
                ['type' => 'right_side',  'required' => false, 'guide_label' => 'Right side — from waist up, natural posture'],
            ],
            'eyelid_surgery' => [
                ['type' => 'front',       'required' => true,  'guide_label' => 'Face forward — eyes open, neutral expression, good lighting'],
                ['type' => 'eyes_closed', 'required' => true,  'guide_label' => 'Eyes closed — lids relaxed, face forward'],
                ['type' => 'left_profile', 'required' => false, 'guide_label' => 'Left profile — full view of eyelid'],
            ],
            'face_and_neck_lift' => [
                ['type' => 'front',        'required' => true, 'guide_label' => 'Face and neck forward — relaxed expression, hair back'],
                ['type' => 'left_profile', 'required' => true, 'guide_label' => 'Left profile — full neck and jaw visible'],
                ['type' => 'right_profile', 'required' => true, 'guide_label' => 'Right profile — full neck and jaw visible'],
            ],
            'arm_lipo_lift' => [
                ['type' => 'front',     'required' => true,  'guide_label' => 'Arms extended at sides — front view, palms forward'],
                ['type' => 'arm_front', 'required' => true,  'guide_label' => 'Arms close-up — both arms extended, front view'],
                ['type' => 'back',      'required' => false, 'guide_label' => 'Rear view — arms extended at sides'],
            ],
            'arm_thigh_lift' => [
                ['type' => 'front',     'required' => true,  'guide_label' => 'Full body front — arms extended at sides'],
                ['type' => 'arm_front', 'required' => true,  'guide_label' => 'Arms close-up — both arms extended, front view'],
                ['type' => 'back',      'required' => false, 'guide_label' => 'Rear view — arms extended at sides'],
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
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Brazilian Butt Lift ─────────────────────────────────────────
        // Pattern demo: the universal + buttock-injection helpers are prepended
        // to the procedure-specific questions. Apply this same array_merge to
        // other procedure quizzes to roll out the universal medical-safety block.

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'bbl', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge(
                    $this->universalSafetyQuestions(),
                    $this->buttockInjectionQuestions(),
                    [
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
                    ]
                ),
            ]
        );

        // ─── Quiz: Liposuction 360 ─────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'lipo_360', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge(
                    $this->universalSafetyQuestions(),
                    $this->buttockInjectionQuestions(),
                    [
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
                    ]
                ),
            ]
        );

        // ─── Quiz: Breast Augmentation ─────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_augmentation', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Facelift ────────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'facelift', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Tummy Tuck ──────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'tummy_tuck', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge(
                    $this->universalSafetyQuestions(),
                    [
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
                    ]
                ),
            ]
        );

        // ─── Quiz: Mommy Makeover ──────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'mommy_makeover', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Breast Lift ─────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_lift', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Breast Reduction ────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'breast_reduction', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Skinny BBL ──────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'skinny_bbl', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), $this->buttockInjectionQuestions(), [
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
                ]),
            ]
        );

        // ─── Quiz: Gynecomastia ────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'gynecomastia', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'reduce_size',    'label' => 'Reduce chest size and fullness'],
                            ['value' => 'flat_chest',     'label' => 'Achieve a flatter, more masculine chest'],
                            ['value' => 'remove_skin',    'label' => 'Remove excess or sagging skin'],
                            ['value' => 'definition',     'label' => 'Improve chest muscle definition'],
                            ['value' => 'asymmetry',      'label' => 'Correct asymmetry between sides'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_tissue_type',
                        'type' => 'select',
                        'label' => 'How would you describe the tissue in your chest?',
                        'required' => true,
                        'options' => [
                            ['value' => 'glandular', 'label' => 'Firm tissue behind the nipple (glandular)'],
                            ['value' => 'fatty',     'label' => 'Soft, fatty tissue throughout the chest'],
                            ['value' => 'mixed',     'label' => 'Both firm and fatty tissue'],
                            ['value' => 'unsure',    'label' => 'Not sure'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_cause',
                        'type' => 'select',
                        'label' => 'Do you know the likely cause of your gynecomastia?',
                        'required' => false,
                        'options' => [
                            ['value' => 'puberty',     'label' => 'Developed during puberty'],
                            ['value' => 'steroids',    'label' => 'Anabolic steroid or supplement use'],
                            ['value' => 'medication',  'label' => 'Prescription medication side-effect'],
                            ['value' => 'weight',      'label' => 'Weight fluctuation'],
                            ['value' => 'unknown',     'label' => 'Unknown / no clear cause'],
                        ],
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
                            ['value' => 'under_10k', 'label' => 'Under $6,000'],
                            ['value' => '10k_15k',   'label' => '$6,000 – $10,000'],
                            ['value' => '15k_25k',   'label' => '$10,000 – $15,000'],
                            ['value' => 'over_25k',  'label' => 'Over $15,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Abdominal Etching ───────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'abdominal_etching', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for abdominal etching?',
                        'required' => true,
                        'options' => [
                            ['value' => 'six_pack',       'label' => 'Define a six-pack appearance'],
                            ['value' => 'waist',          'label' => 'Slim and define the waist'],
                            ['value' => 'athletic',       'label' => 'Achieve an athletic, sculpted look'],
                            ['value' => 'linea_alba',     'label' => 'Define the linea alba (central line)'],
                            ['value' => 'obliques',       'label' => 'Highlight oblique muscles'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_fitness_level',
                        'type' => 'select',
                        'label' => 'How would you describe your current fitness level?',
                        'required' => true,
                        'options' => [
                            ['value' => 'athletic',  'label' => 'Athletic — train 4+ days per week'],
                            ['value' => 'active',    'label' => 'Active — exercise regularly'],
                            ['value' => 'moderate',  'label' => 'Moderate — occasional exercise'],
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
                        'label' => 'Etching results are best when weight is stable. Please share more.',
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
                            ['value' => 'under_10k', 'label' => 'Under $6,000'],
                            ['value' => '10k_15k',   'label' => '$6,000 – $10,000'],
                            ['value' => '15k_25k',   'label' => '$10,000 – $15,000'],
                            ['value' => 'over_25k',  'label' => 'Over $15,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Liposuction (general) ───────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'liposuction', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which areas would you like to treat?',
                        'required' => true,
                        'options' => [
                            ['value' => 'abdomen',     'label' => 'Abdomen'],
                            ['value' => 'flanks',      'label' => 'Flanks / love handles'],
                            ['value' => 'back',        'label' => 'Back / bra rolls'],
                            ['value' => 'inner_thighs', 'label' => 'Inner thighs'],
                            ['value' => 'outer_thighs', 'label' => 'Outer thighs'],
                            ['value' => 'arms',        'label' => 'Arms'],
                            ['value' => 'chin_neck',   'label' => 'Chin / neck'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_skin_laxity',
                        'type' => 'select',
                        'label' => 'How would you describe your skin elasticity in the target area(s)?',
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
                        'label' => 'Liposuction results are best when weight is stable. Please tell us more.',
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
                            ['value' => 'under_10k', 'label' => 'Under $5,000'],
                            ['value' => '10k_15k',   'label' => '$5,000 – $10,000'],
                            ['value' => '15k_25k',   'label' => '$10,000 – $20,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Reverse BBL ─────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'reverse_bbl', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), $this->buttockInjectionQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for a Reverse BBL?',
                        'required' => true,
                        'options' => [
                            ['value' => 'reduce_volume',  'label' => 'Reduce excess buttock volume'],
                            ['value' => 'reshape',        'label' => 'Reshape and improve contour'],
                            ['value' => 'waist_hip',      'label' => 'Improve waist-to-hip ratio'],
                            ['value' => 'upper_body',     'label' => 'Transfer fat to enhance upper body or hips'],
                            ['value' => 'flatten',        'label' => 'Create a flatter, more athletic silhouette'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_prior_bbl',
                        'type' => 'boolean',
                        'label' => 'Have you previously had a Brazilian Butt Lift (BBL)?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_weight_stable'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please tell us about your previous BBL (when, surgeon, any concerns).',
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
                            ['value' => 'under_10k', 'label' => 'Under $8,000'],
                            ['value' => '10k_15k',   'label' => '$8,000 – $15,000'],
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
                ]),
            ]
        );

        // ─── Quiz: J Plasma ────────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'j_plasma', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which areas would you like to tighten?',
                        'required' => true,
                        'options' => [
                            ['value' => 'arms',     'label' => 'Arms / underarm skin'],
                            ['value' => 'abdomen',  'label' => 'Abdomen / stomach'],
                            ['value' => 'thighs',   'label' => 'Inner or outer thighs'],
                            ['value' => 'neck',     'label' => 'Neck / chin area'],
                            ['value' => 'back',     'label' => 'Back / flanks'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_prior_lipo',
                        'type' => 'boolean',
                        'label' => 'Have you previously had liposuction in the target area(s)?',
                        'required' => true,
                        'branches' => ['*' => ['next' => 'q_skin_laxity']],
                    ],
                    [
                        'id' => 'q_skin_laxity',
                        'type' => 'select',
                        'label' => 'How would you describe the skin laxity in the target area(s)?',
                        'required' => true,
                        'options' => [
                            ['value' => 'mild',     'label' => 'Mild — slightly loose or crepey'],
                            ['value' => 'moderate', 'label' => 'Moderate — noticeably loose skin'],
                            ['value' => 'severe',   'label' => 'Significant — very loose or hanging skin'],
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
                            ['value' => 'under_10k', 'label' => 'Under $5,000'],
                            ['value' => '10k_15k',   'label' => '$5,000 – $10,000'],
                            ['value' => '15k_25k',   'label' => '$10,000 – $20,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Arm Lipo & Lift / Arm & Thigh Lift / Back Lipo & Lift ──────

        foreach (['arm_lipo_lift', 'arm_thigh_lift', 'back_liposuction_lift'] as $slug) {
            $areaLabel = match ($slug) {
                'arm_lipo_lift' => 'arms',
                'arm_thigh_lift' => 'arms and thighs',
                'back_liposuction_lift' => 'back',
            };

            QuizDefinition::updateOrCreate(
                ['procedure_slug' => $slug, 'is_active' => true],
                [
                    'version' => 2,
                    'is_active' => true,
                    'questions' => array_merge($this->universalSafetyQuestions(), [
                        [
                            'id' => 'q_concerns',
                            'type' => 'multiselect',
                            'label' => "What are your primary goals for your {$areaLabel}?",
                            'required' => true,
                            'options' => [
                                ['value' => 'loose_skin',   'label' => 'Remove loose or sagging skin'],
                                ['value' => 'remove_fat',   'label' => 'Remove excess fat'],
                                ['value' => 'definition',   'label' => 'Improve contour and definition'],
                                ['value' => 'post_wl',      'label' => 'Address post-weight-loss changes'],
                                ['value' => 'asymmetry',    'label' => 'Correct asymmetry'],
                            ],
                            'branches' => [],
                        ],
                        [
                            'id' => 'q_weight_loss',
                            'type' => 'boolean',
                            'label' => 'Have you experienced significant weight loss (20+ lbs) in the past 2 years?',
                            'required' => true,
                            'branches' => ['*' => ['next' => 'q_weight_stable']],
                        ],
                        [
                            'id' => 'q_weight_stable',
                            'type' => 'boolean',
                            'label' => 'Is your weight currently stable (past 6 months)?',
                            'required' => true,
                            'branches' => [
                                'true' => ['next' => 'q_timeline'],
                                'false' => ['next' => 'q_weight_note'],
                            ],
                        ],
                        [
                            'id' => 'q_weight_note',
                            'type' => 'text',
                            'label' => 'Lift procedures are best performed once weight is stable. Please tell us more.',
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
                                ['value' => 'under_10k', 'label' => 'Under $8,000'],
                                ['value' => '10k_15k',   'label' => '$8,000 – $15,000'],
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
                    ]),
                ]
            );
        }

        // ─── Quiz: Axillary Liposuction ────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'axillary_liposuction', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'armpit_fat',    'label' => 'Reduce excess fat in the armpit area'],
                            ['value' => 'bra_bulge',     'label' => 'Eliminate bra or underarm bulge'],
                            ['value' => 'fit_clothing',  'label' => 'Improve fit of clothing and swimwear'],
                            ['value' => 'definition',    'label' => 'Improve overall arm and chest contour'],
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
                        'label' => 'Liposuction results are best when weight is stable. Please tell us more.',
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
                            ['value' => 'under_10k', 'label' => 'Under $4,000'],
                            ['value' => '10k_15k',   'label' => '$4,000 – $7,000'],
                            ['value' => '15k_25k',   'label' => '$7,000 – $12,000'],
                            ['value' => 'over_25k',  'label' => 'Over $12,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Labiaplasty ─────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'labiaplasty', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary reasons for considering this procedure?',
                        'required' => true,
                        'options' => [
                            ['value' => 'discomfort',    'label' => 'Physical discomfort during activity or clothing'],
                            ['value' => 'aesthetic',     'label' => 'Aesthetic concerns — size or shape'],
                            ['value' => 'asymmetry',     'label' => 'Asymmetry between sides'],
                            ['value' => 'post_childbirth', 'label' => 'Changes following childbirth'],
                            ['value' => 'hygiene',       'label' => 'Hygiene or irritation concerns'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_functional',
                        'type' => 'select',
                        'label' => 'Is your concern primarily functional, aesthetic, or both?',
                        'required' => true,
                        'options' => [
                            ['value' => 'functional',  'label' => 'Primarily functional — discomfort or interference with activity'],
                            ['value' => 'aesthetic',   'label' => 'Primarily aesthetic'],
                            ['value' => 'both',        'label' => 'Both functional and aesthetic'],
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
                            ['value' => 'under_10k', 'label' => 'Under $3,000'],
                            ['value' => '10k_15k',   'label' => '$3,000 – $6,000'],
                            ['value' => '15k_25k',   'label' => '$6,000 – $10,000'],
                            ['value' => 'over_25k',  'label' => 'Over $10,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Scar Revision ───────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'scar_revision', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What type of scar are you looking to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'hypertrophic', 'label' => 'Raised / thickened scar (hypertrophic)'],
                            ['value' => 'keloid',       'label' => 'Keloid — scar that grew beyond the wound'],
                            ['value' => 'surgical',     'label' => 'Surgical scar (from prior procedure)'],
                            ['value' => 'contracture',  'label' => 'Tight or contracture scar limiting movement'],
                            ['value' => 'aesthetic',    'label' => 'Visible scar affecting appearance'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_scar_age',
                        'type' => 'select',
                        'label' => 'How old is the scar you would like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'recent',    'label' => 'Less than 1 year old'],
                            ['value' => '1_3_years', 'label' => '1–3 years old'],
                            ['value' => 'older',     'label' => 'More than 3 years old'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_prior_treatment',
                        'type' => 'boolean',
                        'label' => 'Have you previously had any treatment for this scar (steroid injections, laser, silicone sheets)?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe the previous treatment(s) and results.',
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
                            ['value' => 'under_10k', 'label' => 'Under $2,000'],
                            ['value' => '10k_15k',   'label' => '$2,000 – $5,000'],
                            ['value' => '15k_25k',   'label' => '$5,000 – $10,000'],
                            ['value' => 'over_25k',  'label' => 'Over $10,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Face and Neck Lift ──────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'face_and_neck_lift', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which concerns would you like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'neck_laxity',  'label' => 'Neck laxity / "turkey neck"'],
                            ['value' => 'jowling',      'label' => 'Jowling / lower face sagging'],
                            ['value' => 'chin_fat',     'label' => 'Excess fat under the chin'],
                            ['value' => 'jaw',          'label' => 'Loss of jaw definition'],
                            ['value' => 'overall',      'label' => 'Overall facial and neck aging'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_result_preference',
                        'type' => 'select',
                        'label' => 'What level of rejuvenation are you looking for?',
                        'required' => true,
                        'options' => [
                            ['value' => 'subtle',      'label' => 'Subtle — refreshed, natural look'],
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
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_smoker_note',
                        'type' => 'text',
                        'label' => 'Smoking significantly affects healing. Please share any relevant context.',
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
                ]),
            ]
        );

        // ─── Quiz: Eyelid Surgery (Blepharoplasty) ─────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'eyelid_surgery', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'Which concerns would you like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'upper_sagging',  'label' => 'Sagging or drooping upper eyelids'],
                            ['value' => 'upper_hooding',  'label' => 'Excess skin hooding over upper lids'],
                            ['value' => 'lower_bags',     'label' => 'Under-eye bags or puffiness'],
                            ['value' => 'lower_hollows',  'label' => 'Under-eye hollowing or dark circles'],
                            ['value' => 'asymmetry',      'label' => 'Asymmetry between eyes'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_area',
                        'type' => 'select',
                        'label' => 'Which eyelids are you looking to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'upper_only', 'label' => 'Upper eyelids only'],
                            ['value' => 'lower_only', 'label' => 'Lower eyelids only'],
                            ['value' => 'both',       'label' => 'Both upper and lower'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_vision_impact',
                        'type' => 'boolean',
                        'label' => 'Do the drooping eyelids affect your field of vision?',
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
                            ['value' => 'under_10k', 'label' => 'Under $4,000'],
                            ['value' => '10k_15k',   'label' => '$4,000 – $8,000'],
                            ['value' => '15k_25k',   'label' => '$8,000 – $15,000'],
                            ['value' => 'over_25k',  'label' => 'Over $15,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Chin Lipo ───────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'chin_lipo', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for chin / neck liposuction?',
                        'required' => true,
                        'options' => [
                            ['value' => 'double_chin', 'label' => 'Eliminate double chin'],
                            ['value' => 'jaw_define',  'label' => 'Define the jawline'],
                            ['value' => 'neck_slim',   'label' => 'Slim the neck profile'],
                            ['value' => 'profile',     'label' => 'Improve side profile appearance'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_skin_laxity',
                        'type' => 'select',
                        'label' => 'How would you describe the skin in the chin / neck area?',
                        'required' => true,
                        'options' => [
                            ['value' => 'firm',     'label' => 'Firm and elastic'],
                            ['value' => 'mild',     'label' => 'Mildly loose'],
                            ['value' => 'moderate', 'label' => 'Noticeably loose or saggy'],
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
                            ['value' => 'under_10k', 'label' => 'Under $3,000'],
                            ['value' => '10k_15k',   'label' => '$3,000 – $6,000'],
                            ['value' => '15k_25k',   'label' => '$6,000 – $10,000'],
                            ['value' => 'over_25k',  'label' => 'Over $10,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Bichectomy ──────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'bichectomy', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What are your primary goals for bichectomy?',
                        'required' => true,
                        'options' => [
                            ['value' => 'slim_face',    'label' => 'Slim and contour the face'],
                            ['value' => 'cheekbones',   'label' => 'Enhance cheekbone definition'],
                            ['value' => 'less_round',   'label' => 'Reduce facial roundness'],
                            ['value' => 'symmetry',     'label' => 'Improve facial symmetry'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_result_preference',
                        'type' => 'select',
                        'label' => 'What degree of change are you looking for?',
                        'required' => true,
                        'options' => [
                            ['value' => 'subtle',      'label' => 'Subtle — slight refinement'],
                            ['value' => 'moderate',    'label' => 'Moderate — noticeable contour change'],
                            ['value' => 'significant', 'label' => 'Significant — dramatic slimming'],
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
                            ['value' => 'under_10k', 'label' => 'Under $3,000'],
                            ['value' => '10k_15k',   'label' => '$3,000 – $5,000'],
                            ['value' => '15k_25k',   'label' => '$5,000 – $8,000'],
                            ['value' => 'over_25k',  'label' => 'Over $8,000'],
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
                ]),
            ]
        );

        // ─── Quiz: Otoplasty ───────────────────────────────────────────────────

        QuizDefinition::updateOrCreate(
            ['procedure_slug' => 'otoplasty', 'is_active' => true],
            [
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
                    [
                        'id' => 'q_concerns',
                        'type' => 'multiselect',
                        'label' => 'What concerns would you like to address?',
                        'required' => true,
                        'options' => [
                            ['value' => 'prominent',   'label' => 'Prominent or protruding ears'],
                            ['value' => 'asymmetry',   'label' => 'Asymmetry between ears'],
                            ['value' => 'size',        'label' => 'Ear size — too large'],
                            ['value' => 'shape',       'label' => 'Ear shape or missing folds'],
                            ['value' => 'post_trauma', 'label' => 'Post-trauma or injury correction'],
                        ],
                        'branches' => [],
                    ],
                    [
                        'id' => 'q_area',
                        'type' => 'select',
                        'label' => 'Which ear(s) require correction?',
                        'required' => true,
                        'options' => [
                            ['value' => 'both',  'label' => 'Both ears'],
                            ['value' => 'right', 'label' => 'Right ear only'],
                            ['value' => 'left',  'label' => 'Left ear only'],
                        ],
                        'branches' => ['*' => ['next' => 'q_prior_surgery']],
                    ],
                    [
                        'id' => 'q_prior_surgery',
                        'type' => 'boolean',
                        'label' => 'Have you had any previous ear surgery?',
                        'required' => true,
                        'branches' => [
                            'true' => ['next' => 'q_prior_details'],
                            'false' => ['next' => 'q_timeline'],
                        ],
                    ],
                    [
                        'id' => 'q_prior_details',
                        'type' => 'text',
                        'label' => 'Please briefly describe your previous ear surgery.',
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
                            ['value' => 'under_10k', 'label' => 'Under $4,000'],
                            ['value' => '10k_15k',   'label' => '$4,000 – $7,000'],
                            ['value' => '15k_25k',   'label' => '$7,000 – $12,000'],
                            ['value' => 'over_25k',  'label' => 'Over $12,000'],
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
                ]),
            ]
        );

        // ─── Generate Generic Quizzes for remaining procedures ─────────────────

        $mvpSlugs = [
            'rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift',
            'tummy_tuck', 'mommy_makeover', 'breast_lift', 'breast_reduction', 'skinny_bbl',
            'gynecomastia', 'abdominal_etching', 'liposuction', 'reverse_bbl', 'j_plasma',
            'arm_lipo_lift', 'arm_thigh_lift', 'back_liposuction_lift', 'axillary_liposuction',
            'labiaplasty', 'scar_revision', 'face_and_neck_lift', 'eyelid_surgery',
            'chin_lipo', 'bichectomy', 'otoplasty',
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
                'version' => 2,
                'is_active' => true,
                'questions' => array_merge($this->universalSafetyQuestions(), [
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
                ]),
            ]
        );
    }

    /**
     * Universal medical-safety questions prepended to every procedure quiz.
     * Returns 7 questions covering age, body type, smoking, pregnancy,
     * medical history, medications, and allergies.
     *
     * @return array<int, array<string, mixed>>
     */
    private function universalSafetyQuestions(): array
    {
        return [
            [
                'id' => 'q_age_range',
                'type' => 'select',
                'label' => 'What is your age range?',
                'required' => true,
                'options' => [
                    ['value' => '18_24', 'label' => '18-24'],
                    ['value' => '25_34', 'label' => '25-34'],
                    ['value' => '35_44', 'label' => '35-44'],
                    ['value' => '45_54', 'label' => '45-54'],
                    ['value' => '55_64', 'label' => '55-64'],
                    ['value' => '65_plus', 'label' => '65+'],
                ],
                'branches' => [],
            ],
            [
                'id' => 'q_body_type',
                'type' => 'select',
                'label' => 'How would you describe your body type?',
                'required' => true,
                'options' => [
                    ['value' => 'lean', 'label' => 'Lean / slim'],
                    ['value' => 'average', 'label' => 'Average'],
                    ['value' => 'curvy', 'label' => 'Curvy / athletic'],
                    ['value' => 'heavier', 'label' => 'Heavier set'],
                    ['value' => 'unsure', 'label' => 'Not sure'],
                ],
                'branches' => [],
            ],
            [
                'id' => 'q_smoking',
                'type' => 'select',
                'label' => 'Do you smoke or use nicotine products?',
                'required' => true,
                'options' => [
                    ['value' => 'never', 'label' => 'Never'],
                    ['value' => 'quit_past', 'label' => 'Quit more than 6 months ago'],
                    ['value' => 'quit_recent', 'label' => 'Quit within the last 6 months'],
                    ['value' => 'social', 'label' => 'Occasionally / socially'],
                    ['value' => 'regular', 'label' => 'Regularly'],
                ],
                'branches' => [],
            ],
            [
                'id' => 'q_pregnancy_status',
                'type' => 'select',
                'label' => 'Are you currently pregnant, breastfeeding, or have you recently been pregnant?',
                'required' => true,
                'options' => [
                    ['value' => 'none', 'label' => 'None of the above'],
                    ['value' => 'pregnant', 'label' => 'Currently pregnant'],
                    ['value' => 'breastfeeding', 'label' => 'Currently breastfeeding'],
                    ['value' => 'recent_birth', 'label' => 'Gave birth within the last 6 months'],
                    ['value' => 'recent_loss', 'label' => 'Recent pregnancy loss'],
                ],
                'branches' => [],
            ],
            [
                'id' => 'q_medical_history',
                'type' => 'multiselect',
                'label' => 'Do you have any of the following medical conditions? (Select all that apply)',
                'required' => true,
                'options' => [
                    ['value' => 'none', 'label' => 'None'],
                    ['value' => 'diabetes', 'label' => 'Diabetes'],
                    ['value' => 'thyroid', 'label' => 'Thyroid disorder'],
                    ['value' => 'hypertension', 'label' => 'High blood pressure'],
                    ['value' => 'heart_lung', 'label' => 'Heart or lung disease'],
                    ['value' => 'clotting', 'label' => 'Blood clotting disorder'],
                    ['value' => 'bleeding', 'label' => 'Bleeding disorder'],
                    ['value' => 'autoimmune', 'label' => 'Autoimmune disease'],
                    ['value' => 'seizures', 'label' => 'Seizures / epilepsy'],
                    ['value' => 'stroke', 'label' => 'History of stroke'],
                    ['value' => 'sickle_cell', 'label' => 'Sickle cell disease or trait'],
                ],
                'branches' => [],
            ],
            [
                'id' => 'q_medications',
                'type' => 'text',
                'label' => 'List any medications, supplements, or herbal products you currently take. (Type "none" if none.)',
                'required' => true,
                'branches' => [],
            ],
            [
                'id' => 'q_allergies',
                'type' => 'text',
                'label' => 'List any drug, food, or material allergies. (Type "none" if none.)',
                'required' => true,
                'branches' => [],
            ],
        ];
    }

    /**
     * Buttock-injection safety gate prepended to BBL-family procedures
     * (BBL, Lipo 360, Skinny BBL, Reverse BBL). Asks if the patient has
     * had prior buttock injections (silicone, biopolymers, etc.) and
     * branches to a follow-up text question if yes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buttockInjectionQuestions(): array
    {
        return [
            [
                'id' => 'q_buttock_injections',
                'type' => 'boolean',
                'label' => 'Have you ever had injections to your buttocks (silicone, biopolymers, PMMA, hydrogel, or any non-fat filler)?',
                'required' => true,
                'branches' => [
                    'true' => ['next' => 'q_buttock_injection_details'],
                    'false' => ['next' => 'q_concerns'],
                ],
            ],
            [
                'id' => 'q_buttock_injection_details',
                'type' => 'text',
                'label' => 'Please describe what was injected, when, and by whom (if known).',
                'required' => false,
                'branches' => ['*' => ['next' => 'q_concerns']],
            ],
        ];
    }
}
