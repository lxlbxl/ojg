<?php
/**
 * OJG Conditions Registry
 * Defines member experience per funnel/condition
 */

return [
    'pcos' => [
        'brand_name' => 'CycleSync',
        'theme' => [
            'primary' => 'rose', // Tailwind color family
            'accent' => 'pink'
        ],
        'hero_metric' => 'cycle_regularity',
        'metrics_label' => 'Cycle & Symptoms',
        'persona' => 'aisha_pcos_specialist',
        'terminology' => [
            'condition_name' => 'PCOS',
            'type_name' => 'PCOS Type',
            'dashboard_welcome' => 'Tailored for your PCOS type',
            'daily_log' => 'Cycle & Symptom Log'
        ],
        'profile_types' => [
            [
                'value' => 'Insulin Resistant',
                'label' => 'Insulin Resistant',
                'desc' => 'Weight gain, fatigue, sugar cravings.'
            ],
            [
                'value' => 'Adrenal',
                'label' => 'Adrenal Driven',
                'desc' => 'High stress, anxiety, sleep issues.'
            ],
            [
                'value' => 'Inflammatory',
                'label' => 'Inflammatory',
                'desc' => 'Acne, headaches, joint pain.'
            ],
            [
                'value' => 'Post-Pill',
                'label' => 'Post-Pill',
                'desc' => 'Symptoms flared after birth control.'
            ]
        ],
        'modules' => [
            'cycle_tracker',
            'phase_synced_meals',
            'herbal_protocol',
            'phase_movement',
            'symptom_tracker'
        ],
        'milestones' => [
            'first_cycle_logged',
            'first_full_cycle',
            'first_regular_cycle',
            '90_day_transformation'
        ]
    ],
    'acne' => [
        'brand_name' => 'GlowClear',
        'theme' => [
            'primary' => 'teal',
            'accent' => 'emerald'
        ],
        'hero_metric' => 'skin_clarity',
        'metrics_label' => 'Skin Clarity & Triggers',
        'persona' => 'dr_sarah_dermatology',
        'terminology' => [
            'condition_name' => 'Acne',
            'type_name' => 'Skin Profile',
            'dashboard_welcome' => 'Tailored for your skin profile',
            'daily_log' => 'Skin & Trigger Log'
        ],
        'profile_types' => [
            [
                'value' => 'Oily',
                'label' => 'Oily Skin',
                'desc' => 'Excess sebum, enlarged pores, frequent breakouts.'
            ],
            [
                'value' => 'Dry',
                'label' => 'Dry / Dehydrated Skin',
                'desc' => 'Flakiness, tightness, easily irritated.'
            ],
            [
                'value' => 'Combination',
                'label' => 'Combination Skin',
                'desc' => 'Oily T-zone, dry cheeks, occasional breakouts.'
            ],
            [
                'value' => 'Sensitive',
                'label' => 'Sensitive Skin',
                'desc' => 'Redness, burning sensation, reacts easily to products.'
            ]
        ],
        'modules' => [
            'skin_log',
            'photo_timeline',
            'trigger_tracker',
            'topical_routine',
            'anti_inflammatory_meals'
        ],
        'milestones' => [
            'baseline_photo',
            '2_week_adherence',
            'first_flare_free_week',
            'cleared_skin'
        ]
    ],
    'weight' => [
        'brand_name' => 'LeanFlow',
        'theme' => [
            'primary' => 'blue',
            'accent' => 'indigo'
        ],
        'hero_metric' => 'weight_trend',
        'metrics_label' => 'Metabolic Progress',
        'persona' => 'coach_michael_metabolic',
        'terminology' => [
            'condition_name' => 'Weight Loss',
            'type_name' => 'Metabolic Profile',
            'dashboard_welcome' => 'Tailored for your metabolic profile',
            'daily_log' => 'Habits & NSV Log'
        ],
        'profile_types' => [
            [
                'value' => 'Sluggish Metabolism',
                'label' => 'Sluggish Metabolism',
                'desc' => 'Difficulty losing weight despite calorie deficit, constant fatigue.'
            ],
            [
                'value' => 'Stress-Induced',
                'label' => 'Stress-Induced / Cortisol',
                'desc' => 'Midsection weight gain, sleep issues, emotional eating.'
            ],
            [
                'value' => 'Insulin Resistant',
                'label' => 'Insulin Resistant',
                'desc' => 'Brain fog after meals, waist-to-hip ratio, intense sugar cravings.'
            ]
        ],
        'modules' => [
            'weight_tracker',
            'metabolic_meals',
            'movement_plan',
            'habit_tracker',
            'plateau_protocol'
        ],
        'milestones' => [
            'first_week_logged',
            'first_2_percent_loss',
            'first_plateau_broken',
            'goal_progress_band'
        ]
    ],
    'mens' => [
        'brand_name' => 'Vitale',
        'theme' => [
            'primary' => 'slate',
            'accent' => 'orange'
        ],
        'hero_metric' => 'vitality_score',
        'metrics_label' => 'Performance & Vitality',
        'persona' => 'coach_david_performance',
        'terminology' => [
            'condition_name' => 'Vitality',
            'type_name' => 'Performance Profile',
            'dashboard_welcome' => 'Tailored for your peak performance',
            'daily_log' => 'Energy & Vitality Log'
        ],
        'profile_types' => [
            [
                'value' => 'Low Energy',
                'label' => 'Low Energy / Vitality',
                'desc' => 'Mid-day slump, brain fog, decreased motivation.'
            ],
            [
                'value' => 'High Stress',
                'label' => 'High Stress / Cortisol',
                'desc' => 'Insomnia, muscle tension, mental burnout.'
            ],
            [
                'value' => 'Metabolic Support',
                'label' => 'Metabolic & Muscle Support',
                'desc' => 'Difficulty building muscle, slow metabolism, recovery lag.'
            ]
        ],
        'modules' => [
            'vitality_tracker',
            't_support_meals',
            'strength_protocol',
            'sleep_tracker',
            'supplement_protocol'
        ],
        'milestones' => [
            'baseline_logged',
            'first_streak_week',
            'energy_improvement',
            '90_day_vitality'
        ]
    ]
];
