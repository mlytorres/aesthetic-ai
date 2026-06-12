<?php

declare(strict_types=1);

return [

    'enabled' => (bool) env('HELP_CENTER_ENABLED', true),

    'handbook_path' => base_path('handbook'),

    'chapters' => [
        [
            'slug' => 'getting-started',
            'file' => '00-Getting-Started.md',
            'title' => 'Getting started',
            'description' => 'Sign in, roles, and how an evaluation flows through the platform.',
            'sort' => 0,
        ],
        [
            'slug' => 'evaluations',
            'file' => '01-Evaluations.md',
            'title' => 'Evaluations',
            'description' => 'The queue, statuses, lead scores, notes, and clinical briefs.',
            'sort' => 10,
        ],
        [
            'slug' => 'simulations-consultations',
            'file' => '02-Simulations-Consultations.md',
            'title' => 'Simulations & consultations',
            'description' => 'AI result previews and built-in video consultations.',
            'sort' => 20,
        ],
        [
            'slug' => 'intake-widget',
            'file' => '03-Intake-Widget.md',
            'title' => 'Intake widget & patient experience',
            'description' => 'Embedding the wizard, photos, reports, and the patient portal.',
            'sort' => 30,
        ],
        [
            'slug' => 'affiliates',
            'file' => '04-Affiliates.md',
            'title' => 'Affiliates',
            'description' => 'Partners, campaigns, payouts, and fraud review.',
            'sort' => 40,
        ],
        [
            'slug' => 'analytics',
            'file' => '05-Analytics.md',
            'title' => 'Analytics',
            'description' => 'Funnel and conversion metrics.',
            'sort' => 50,
        ],
        [
            'slug' => 'clinic-admin',
            'file' => '06-Clinic-Admin.md',
            'title' => 'Clinic admin',
            'description' => 'Settings, team, integrations, billing, and security.',
            'sort' => 60,
        ],
        [
            'slug' => 'troubleshooting',
            'file' => '07-Troubleshooting.md',
            'title' => 'Troubleshooting & FAQ',
            'description' => 'Quick fixes for intake, sync, and access issues.',
            'sort' => 70,
        ],
    ],

];
