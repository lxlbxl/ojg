<?php
/**
 * PcosProtocolGenerator (CycleSync) — 90-Day PCOS Protocol
 *
 * Extends AbstractProtocolGenerator. Keeps the existing PCOS clinical quality
 * but now routes through the abstract scaffold, is region-aware, and wires
 * its tracking_guidance to the structured member-area logging schema.
 */

require_once __DIR__ . '/AbstractProtocolGenerator.php';

class PcosProtocolGenerator extends AbstractProtocolGenerator
{
    private array $typeMap = [
        'insulin'      => 'Insulin-Resistant',
        'inflammatory' => 'Inflammatory',
        'adrenal'      => 'Adrenal',
        'postPill'     => 'Post-Pill',
        'post_pill'    => 'Post-Pill',
        'postpill'     => 'Post-Pill',
        'general'      => 'Insulin-Resistant',
    ];

    public function getCondition(): string { return 'pcos'; }

    public function getModuleManifest(): array
    {
        return [
            self::MODULE_MEAL_PLAN,
            self::MODULE_MOVEMENT,
            self::MODULE_HERBAL_PROTOCOL,
            self::MODULE_SUPPLEMENTS,
            self::MODULE_CYCLE_SYNC,
            self::MODULE_SLEEP_STRESS,
            self::MODULE_TRACKING,
            self::MODULE_PHASE_ARC,
        ];
    }

    protected function getPromptsDir(): string
    {
        return $this->promptsDir . '/pcos';
    }

    protected function resolveSubType(array $assessment): string
    {
        $code = $assessment['pcosType']['primary'] ?? $assessment['pcosType'] ?? $assessment['type'] ?? 'insulin';
        return $this->typeMap[strtolower((string) $code)] ?? 'Insulin-Resistant';
    }

    protected function buildUserPromptVars(array $assessment, string $name, array $regionProfile): array
    {
        $subType = $this->resolveSubType($assessment);
        return [
            'NAME'                => $name ?: 'Friend',
            'PCOS_TYPE'           => $subType,
            'AGE'                 => $assessment['age'] ?? 'Not specified',
            'SYMPTOMS'            => is_array($assessment['symptoms'] ?? null)
                ? implode(', ', $assessment['symptoms']) : ($assessment['symptoms'] ?? 'General hormonal imbalance'),
            'GOALS'               => is_array($assessment['goals'] ?? null)
                ? implode(', ', $assessment['goals']) : ($assessment['goals'] ?? 'Hormonal balance and symptom relief'),
            'BMI'                 => $assessment['bmi'] ?? $assessment['weight'] ?? 'Not specified',
            'CYCLE_STATUS'        => $assessment['cycleStatus'] ?? $assessment['menstrualCycle'] ?? 'Not specified',
            'DIETARY_RESTRICTIONS'=> is_array($assessment['dietaryRestrictions'] ?? null)
                ? implode(', ', $assessment['dietaryRestrictions']) : 'None specified',
            'MEDICATIONS'         => is_array($assessment['medications'] ?? null)
                ? implode(', ', $assessment['medications']) : 'None specified',
            'EXERCISE_LEVEL'      => $assessment['exerciseLevel'] ?? 'Not specified',
            'SLEEP_QUALITY'       => $assessment['sleepQuality'] ?? 'Not specified',
            'STRESS_LEVEL'        => $assessment['stressLevel'] ?? 'Not specified',
            'COUNTRY'             => $regionProfile['country'] ?? 'Nigeria',
            'MEASUREMENT_SYSTEM'  => $regionProfile['measurement_system'] ?? 'metric',
            'REGION_PROFILE'      => '{}', // replaced by base class if regionProfile provided
        ];
    }

    protected function validateConditionContent(array $content): array
    {
        $errors = [];
        $required = [
            'phase_1_title', 'phase_1_focus', 'phase_1_description',
            'phase_2_title', 'phase_2_focus', 'phase_2_description',
            'phase_3_title', 'phase_3_focus', 'phase_3_description',
        ];
        foreach ($required as $field) {
            if (empty($content[$field]) || strlen($content[$field]) < 10) {
                $errors[] = "PCOS: missing $field";
            }
        }
        $requiredArrays = [
            'morning_routine', 'afternoon_routine', 'evening_routine',
            'meal_plan', 'supplements', 'herbal_protocols', 'lifestyle_tips', 'tracking_guidance',
            'phase_1_weekly_actions', 'phase_2_weekly_actions', 'phase_3_weekly_actions',
        ];
        foreach ($requiredArrays as $field) {
            if (empty($content[$field]) || !is_array($content[$field])) {
                $errors[] = "PCOS: missing array $field";
            }
        }
        if (isset($content['meal_plan']) && count($content['meal_plan']) < 7) {
            $errors[] = "PCOS: meal_plan needs 7 days";
        }
        // PCOS must NOT have skincare routine
        if (!empty($content['skincare_routine']) || !empty($content['am_routine'])) {
            $errors[] = "PCOS: forbidden skincare_routine block present";
        }
        return $errors;
    }

    protected function getPlanLabel(string $subType): string
    {
        return "90-Day CycleSync PCOS Protocol ({$subType} Type)";
    }

    protected function renderTemplate(callable $get, string $name, string $subType, array $assessment, array $regionProfile): string
    {
        $templatePath = $this->templatesDir . '/plan-template.html';
        $html = @file_get_contents($templatePath);
        if (!$html) throw new Exception('Could not load PCOS template: ' . $templatePath);

        $r = fn($ph, $val) => $html = str_replace($ph, $val ?? '', $html);
        // Rewrite to use a local variable properly
        $replace = function ($ph, $val) use (&$html) {
            $html = str_replace($ph, $val ?? '', $html);
        };

        $replace('{{NAME}}', $this->esc($name ?: 'Friend'));
        $replace('{{PCOS_TYPE}}', $this->esc($subType));
        $replace('{{CONDITION_LABEL}}', 'CycleSync — PCOS Protocol');
        $replace('{{AGE}}', $this->esc((string) ($assessment['age'] ?? 'N/A')));
        $replace('{{DATE}}', date('j F Y'));
        $replace('{{YEAR}}', date('Y'));
        $replace('{{COUNTRY}}', $this->esc($regionProfile['country'] ?? 'Nigeria'));

        $replace('{{SUMMARY}}', $this->esc($get('summary')));
        $replace('{{ROOT_CAUSE}}', $this->esc($get('root_cause')));
        $replace('{{GOALS}}', $this->renderGoals($get('goals')));

        for ($i = 1; $i <= 3; $i++) {
            $replace("{{PHASE_{$i}_TITLE}}", $this->esc($get("phase_{$i}_title")));
            $replace("{{PHASE_{$i}_FOCUS}}", $this->esc($get("phase_{$i}_focus")));
            $replace("{{PHASE_{$i}_DESCRIPTION}}", $this->esc($get("phase_{$i}_description")));
            $replace("{{PHASE_{$i}_WEEKS}}", $this->renderWeeklyActions($get("phase_{$i}_weekly_actions")));
        }

        $replace('{{MORNING_ROUTINE}}', $this->renderRoutine($get('morning_routine')));
        $replace('{{AFTERNOON_ROUTINE}}', $this->renderRoutine($get('afternoon_routine')));
        $replace('{{EVENING_ROUTINE}}', $this->renderRoutine($get('evening_routine')));

        $mealPlan = $get('meal_plan');
        if (is_array($mealPlan)) {
            $replace('{{MEAL_PLAN_DAYS_1_4}}', $this->renderMealDays(array_slice($mealPlan, 0, 4)));
            $replace('{{MEAL_PLAN_DAYS_5_7}}', $this->renderMealDays(array_slice($mealPlan, 4, 3)));
        } else {
            $replace('{{MEAL_PLAN_DAYS_1_4}}', '');
            $replace('{{MEAL_PLAN_DAYS_5_7}}', '');
        }

        $replace('{{SUPPLEMENTS}}', $this->renderSupplements($get('supplements')));
        $replace('{{HERBAL_PROTOCOLS}}', $this->renderHerbalProtocols($get('herbal_protocols')));
        $replace('{{LIFESTYLE_TIPS}}', $this->renderLifestyleTips($get('lifestyle_tips')));
        $replace('{{TRACKING_GUIDANCE}}', $this->renderTrackingGuidance($get('tracking_guidance')));
        $replace('{{QUICK_WIN}}', $this->renderQuickWin($get('quick_win')));
        $replace('{{SOURCING_GUIDE}}', $this->renderSourcingGuide($get('sourcing_guide'), $regionProfile));
        $replace('{{ENCOURAGEMENT}}', $this->renderEncouragement($get('encouragement')));

        return $html;
    }

    protected function getFallbackContent(string $subType, string $name): array
    {
        // Structured tracking_guidance (new machine-readable schema)
        $trackingGuidance = [
            ['key' => 'cycle_day',   'label' => 'Cycle Day',         'type' => 'number',  'frequency' => 'daily',  'how' => 'Note day of cycle and any spotting',        'why' => 'Shows whether ovulation is returning', 'chart' => 'streak'],
            ['key' => 'mood',        'label' => 'Mood (1–10)',        'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate morning and evening mood',             'why' => 'Progesterone and cortisol are reflected in mood', 'chart' => 'trend'],
            ['key' => 'energy',      'label' => 'Energy (1–10)',      'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate energy at 8 AM and 3 PM',              'why' => 'First indicator of insulin and cortisol improvement', 'chart' => 'trend'],
            ['key' => 'cravings',    'label' => 'Sugar Cravings',     'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Log whether cravings occurred',             'why' => 'Craving reduction = improving insulin sensitivity', 'chart' => 'streak'],
            ['key' => 'symptom_flare', 'label' => 'Symptom Flare',   'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Note any new or worsening symptoms',        'why' => 'Tracks protocol response over time', 'chart' => 'none'],
            ['key' => 'weight',      'label' => 'Weight',             'type' => 'number',  'frequency' => 'weekly', 'how' => 'Same morning conditions, before eating',    'why' => 'Weekly trend shows body composition progress', 'chart' => 'trend'],
        ];

        $base = [
            'Insulin-Resistant' => [
                'summary'    => "$name, your body is showing signs of insulin resistance — the most common driver of PCOS, affecting about 70% of women with this condition. This 90-day CycleSync protocol restores insulin sensitivity through strategic nutrition, targeted supplements, and lifestyle changes.",
                'root_cause' => "Insulin-resistant PCOS develops when cells become less responsive to insulin. Excess insulin signals the ovaries to produce more testosterone — disrupting your cycle. The good news: this is the most treatable type.\n\nWith the right protocol, many women see improvements in energy and cravings within 7–14 days.",
                'goals'      => ['Restore insulin sensitivity and stabilise blood sugar', 'Reduce excess androgen levels', 'Re-establish regular menstrual cycles', 'Reduce belly fat and inflammation', 'Build sustainable hormonal-health habits'],
            ],
            'Inflammatory' => [
                'summary'    => "$name, your symptoms point to inflammatory PCOS — driven by chronic low-grade inflammation disrupting your entire hormonal system. Our protocol targets inflammation at its root.",
                'root_cause' => "Inflammatory PCOS is driven by chronic immune activation that disrupts ovulation and drives androgen production. Healing the gut and removing inflammatory triggers is the key.",
                'goals'      => ['Reduce systemic inflammation', 'Heal gut lining and microbiome', 'Eliminate dietary triggers', 'Reduce androgen overproduction', 'Restore regular ovulation'],
            ],
            'Adrenal' => [
                'summary'    => "$name, your profile suggests adrenal PCOS driven by chronic stress overwhelming your adrenal glands. Your androgens come primarily from the adrenals, not the ovaries.",
                'root_cause' => "Adrenal PCOS is driven by chronic stress. Deep stress recovery, gentle movement, adaptogenic herbs, and rebuilding your sleep rhythm are the cornerstones of healing.",
                'goals'      => ['Restore healthy cortisol rhythm', 'Reduce DHEA-S to normal range', 'Rebuild deep restorative sleep', 'Calm the nervous system', 'Re-establish regular cycles'],
            ],
            'Post-Pill' => [
                'summary'    => "$name, your symptoms align with post-pill PCOS — a temporary disruption after stopping hormonal contraceptives. This type is the most responsive to targeted support.",
                'root_cause' => "Post-pill PCOS occurs when natural hormone production — suppressed by contraceptives — struggles to restart. Liver support and cycle-restoring herbs are key.",
                'goals'      => ["Support the body's natural hormone restart", 'Clear residual synthetic hormones via liver support', 'Reduce temporary androgen surge', 'Restore natural ovulation within 90 days', 'Rebuild healthy menstrual cycles'],
            ],
        ];

        $typeContent = $base[$subType] ?? $base['Insulin-Resistant'];

        return array_merge($typeContent, [
            'phase_1_title'          => 'Foundation & Reset',
            'phase_1_focus'          => 'Building the base',
            'phase_1_description'    => 'The first 30 days reset your baseline — eliminate triggers, establish core habits, start your supplement protocol.',
            'phase_1_weekly_actions' => [
                ['week' => 1, 'focus' => 'Clean Start',   'actions' => ['Remove processed sugar and refined carbs', 'Start morning hydration ritual', 'Begin sleep hygiene protocol', 'Set up tracking journal'], 'milestone' => 'Established new morning routine'],
                ['week' => 2, 'focus' => 'Habits',        'actions' => ['Introduce first supplement', 'Start gentle daily movement', 'Meal prep for the week', 'Practice evening wind-down'], 'milestone' => 'Consistent daily routine'],
                ['week' => 3, 'focus' => 'Deepening',     'actions' => ['Add herbal tea protocol', 'Increase vegetable intake', 'Begin stress-reduction practice', 'Track energy patterns'], 'milestone' => 'Noticing energy improvements'],
                ['week' => 4, 'focus' => 'Consolidation', 'actions' => ['Full supplement stack active', 'Consistent meal timing', 'Regular movement pattern', 'Review and adjust'], 'milestone' => 'Foundation habits locked in'],
            ],
            'phase_2_title'          => 'Acceleration & Healing',
            'phase_2_focus'          => 'Deepening the healing',
            'phase_2_description'    => 'Phase 2 intensifies the protocol. Your body is adapted and you can push deeper into hormonal restoration. Most women see visible changes here.',
            'phase_2_weekly_actions' => [
                ['week' => 5, 'focus' => 'Intensification', 'actions' => ['Introduce advanced herbal protocol', 'Increase movement intensity slightly', 'Optimise meal timing for hormones', 'Deeper stress management'], 'milestone' => 'Visible symptom improvement'],
                ['week' => 6, 'focus' => 'Optimisation',    'actions' => ['Fine-tune supplement dosages', 'Add cycle-syncing awareness', 'Increase anti-inflammatory foods', 'Sleep optimisation'], 'milestone' => 'Energy noticeably better'],
                ['week' => 7, 'focus' => 'Expansion',       'actions' => ['Try new hormone-friendly recipes', 'Increase daily movement duration', 'Deepen mindfulness practice', 'Social support'], 'milestone' => 'Feeling stronger and more confident'],
                ['week' => 8, 'focus' => 'Integration',     'actions' => ['All protocols running smoothly', 'Adjust based on tracked data', 'Plan Phase 3 goals', 'Celebrate progress'], 'milestone' => 'Protocols feel natural'],
            ],
            'phase_3_title'          => 'Transformation & Sustainability',
            'phase_3_focus'          => 'Locking in results',
            'phase_3_description'    => 'The final phase locks in results and builds habits that last beyond Day 90. Your new normal.',
            'phase_3_weekly_actions' => [
                ['week' => 9,  'focus' => 'Mastery',       'actions' => ['Advanced meal planning', 'Peak supplement optimisation', 'Consistent exercise routine', 'Stress resilience'], 'milestone' => 'Confident in the protocol'],
                ['week' => 10, 'focus' => 'Fine-Tuning',   'actions' => ['Personalise based on results', 'Adjust herbs for maintenance', 'Build accountability', 'Long-term planning'], 'milestone' => 'Protocol feels like lifestyle'],
                ['week' => 11, 'focus' => 'Sustainability','actions' => ['Create maintenance plan', 'Identify what works best', 'Build backup plans', 'Prepare for beyond 90 days'], 'milestone' => 'Independence from strict protocol'],
                ['week' => 12, 'focus' => 'Celebration',   'actions' => ['Full progress review', 'Compare Day 1 vs Day 90', 'Set next 90-day goals', 'Share your transformation'], 'milestone' => 'Transformed and empowered!'],
            ],
            'morning_routine' => [
                ['time' => '6:00 AM', 'action' => 'Warm water with lemon and cinnamon',    'why' => 'Kickstarts metabolism and supports insulin sensitivity'],
                ['time' => '6:15 AM', 'action' => '5 minutes deep breathing or meditation', 'why' => 'Lowers cortisol before the day begins'],
                ['time' => '6:30 AM', 'action' => 'Morning supplements with a light snack', 'why' => 'Better absorption with food'],
                ['time' => '7:00 AM', 'action' => 'Protein-rich breakfast',                 'why' => 'Stabilises blood sugar for the morning'],
                ['time' => '7:15 AM', 'action' => '10-minute gentle walk or stretch',       'why' => 'Gets blood flowing and improves mood'],
            ],
            'afternoon_routine' => [
                ['time' => '12:00 PM', 'action' => 'Balanced lunch with protein, fibre, and healthy fat', 'why' => 'Prevents afternoon energy crash'],
                ['time' => '12:30 PM', 'action' => 'Short walk after lunch',                              'why' => 'Dramatically improves post-meal blood sugar'],
                ['time' => '2:00 PM',  'action' => 'Herbal tea break',                                    'why' => 'Therapeutic herbs work best with consistent timing'],
            ],
            'evening_routine' => [
                ['time' => '6:00 PM', 'action' => 'Dinner — largest vegetable portion',     'why' => 'Anti-inflammatory nutrients support overnight repair'],
                ['time' => '7:00 PM', 'action' => 'Evening supplements (magnesium, etc.)',  'why' => 'Magnesium supports sleep and hormone production'],
                ['time' => '8:30 PM', 'action' => 'Screens off — dim lights',               'why' => 'Blue light disrupts melatonin and all downstream hormones'],
                ['time' => '9:30 PM', 'action' => 'Journaling or gratitude, then sleep',    'why' => 'Reduces cortisol, improves sleep quality'],
            ],
            'meal_plan' => [
                ['day' => 1, 'breakfast' => ['meal' => 'Boiled Plantain with Egg Sauce', 'description' => 'Ripe plantain with scrambled eggs in tomato-pepper sauce', 'benefit' => 'Protein-first breakfast stabilises blood sugar'], 'lunch' => ['meal' => 'Beans Porridge with Plantain', 'description' => 'Honey beans with palm oil, peppers, and green plantain', 'benefit' => 'High fibre and plant protein'], 'dinner' => ['meal' => 'Grilled Fish with Efo Riro', 'description' => 'Fresh tilapia with spinach stew and small amala', 'benefit' => 'Omega-3 from fish reduces inflammation'], 'snack' => ['meal' => 'Tiger Nuts and Coconut', 'description' => 'Handful of tiger nuts with dried coconut chips', 'benefit' => 'Healthy fats and prebiotic fibre']],
                ['day' => 2, 'breakfast' => ['meal' => 'Moi Moi', 'description' => 'Steamed bean pudding with boiled eggs', 'benefit' => 'High-protein, low-glycaemic start'], 'lunch' => ['meal' => 'Ofada Rice with Ayamase', 'description' => 'Small portion of ofada rice with designer pepper sauce', 'benefit' => 'Unrefined grain with metabolism-boosting peppers'], 'dinner' => ['meal' => 'Pepper Soup with Goat Meat', 'description' => 'Light spicy broth with lean goat meat and herbs', 'benefit' => 'Anti-inflammatory spices support healing'], 'snack' => ['meal' => 'Garden Eggs with Peanut Butter', 'description' => 'Fresh garden eggs with natural groundnut paste', 'benefit' => 'Low-calorie, high-nutrient snack']],
                ['day' => 3, 'breakfast' => ['meal' => 'Oats with Banana and Groundnuts', 'description' => 'Rolled oats topped with sliced banana and crushed groundnuts', 'benefit' => 'Slow-releasing carbs with healthy fats'], 'lunch' => ['meal' => 'Vegetable Yam Porridge', 'description' => 'Yam porridge loaded with spinach, ugwu, and crayfish', 'benefit' => 'Iron-rich vegetables support energy'], 'dinner' => ['meal' => 'Grilled Chicken with Salad', 'description' => 'Seasoned chicken breast with fresh vegetable salad', 'benefit' => 'Lean protein supports hormone production'], 'snack' => ['meal' => 'Roasted Groundnuts', 'description' => 'A small handful of roasted groundnuts', 'benefit' => 'Magnesium-rich, hormone-supporting snack']],
                ['day' => 4, 'breakfast' => ['meal' => 'Akara with Pap', 'description' => 'Bean cakes with light millet pap', 'benefit' => 'Traditional protein-rich breakfast'], 'lunch' => ['meal' => 'Jollof Rice with Vegetables', 'description' => 'Tomato jollof with mixed vegetables and grilled chicken', 'benefit' => 'Lycopene from tomatoes fights inflammation'], 'dinner' => ['meal' => 'Ukwa (Breadfruit)', 'description' => 'Cooked breadfruit with palm oil and spices', 'benefit' => 'High fibre, supports gut health'], 'snack' => ['meal' => 'Watermelon and Seeds', 'description' => 'Fresh watermelon with pumpkin seeds', 'benefit' => 'Hydration plus zinc']],
                ['day' => 5, 'breakfast' => ['meal' => 'Boiled Eggs with Sweet Potato', 'description' => 'Two boiled eggs with roasted sweet potato', 'benefit' => 'Balanced macros, slow-releasing energy'], 'lunch' => ['meal' => 'Gbegiri with Ewedu and Amala', 'description' => 'Bean soup with jute leaf soup and small amala', 'benefit' => 'Rich in fibre and protein'], 'dinner' => ['meal' => 'Baked Fish with Steamed Vegetables', 'description' => 'Whole baked mackerel with steamed cabbage and carrots', 'benefit' => 'Omega-3 supports hormone balance'], 'snack' => ['meal' => 'Fresh Coconut', 'description' => 'Fresh coconut pieces', 'benefit' => 'MCT fats support metabolism']],
                ['day' => 6, 'breakfast' => ['meal' => 'Smoothie Bowl', 'description' => 'Banana, spinach, groundnut smoothie with seeds', 'benefit' => 'Nutrient-dense, easy to digest'], 'lunch' => ['meal' => 'Fried Rice with Grilled Turkey', 'description' => 'Vegetable fried rice with lean turkey', 'benefit' => 'Balanced meal with protein and vegetables'], 'dinner' => ['meal' => 'Ogbono Soup with Fish', 'description' => 'Light ogbono soup with stockfish and vegetables', 'benefit' => 'Healthy fats and gut-friendly mucilage'], 'snack' => ['meal' => 'Dates and Almonds', 'description' => 'Three dates with almonds', 'benefit' => 'Natural sweetness with healthy fats']],
                ['day' => 7, 'breakfast' => ['meal' => 'Plantain Pancakes', 'description' => 'Mashed ripe plantain blended with eggs and lightly fried', 'benefit' => 'Gluten-free nutrient-rich breakfast'], 'lunch' => ['meal' => 'Coconut Rice with Chicken', 'description' => 'Rice in coconut milk with grilled chicken', 'benefit' => 'Healthy coconut fats support satiety'], 'dinner' => ['meal' => 'Efo Riro with Assorted Meat', 'description' => 'Rich spinach stew with lean beef and stockfish', 'benefit' => 'Iron and protein-rich for rebuilding'], 'snack' => ['meal' => 'Fruit Salad', 'description' => 'Mixed fruits — papaya, pineapple, watermelon', 'benefit' => 'Enzymes support digestion']],
            ],
            'supplements' => [
                ['name' => 'Inositol (Myo + D-Chiro)', 'dosage' => '2000mg Myo + 50mg D-Chiro daily', 'timing' => 'Morning and evening, split dose', 'benefit' => '#1 evidence-based supplement for PCOS — improves insulin sensitivity and ovulation', 'note' => 'Take consistently for at least 3 months'],
                ['name' => 'Vitamin D3', 'dosage' => '2000–4000 IU daily', 'timing' => 'Morning with a fatty meal', 'benefit' => 'Most women with PCOS are deficient; supports immune function and hormone production', 'note' => 'Get blood levels tested if possible'],
                ['name' => 'Magnesium Glycinate', 'dosage' => '300–400mg daily', 'timing' => 'Evening before bed', 'benefit' => 'Supports sleep, reduces anxiety, helps insulin sensitivity', 'note' => 'Start with 200mg and increase gradually'],
                ['name' => 'Omega-3 Fish Oil', 'dosage' => '1000–2000mg EPA+DHA daily', 'timing' => 'With meals', 'benefit' => 'Powerful anti-inflammatory; supports brain health and hormone balance', 'note' => 'Choose a quality brand tested for mercury'],
                ['name' => 'Zinc', 'dosage' => '15–30mg daily', 'timing' => 'With dinner', 'benefit' => 'Supports immune function, reduces androgens, helps with skin', 'note' => 'Take with food to avoid nausea'],
            ],
            'herbal_protocols' => [
                ['herb' => 'Bitter Leaf', 'local_name' => 'Ewuro (Yoruba)', 'preparation' => 'Squeeze leaves, drink juice or brew as tea', 'dosage' => '1 cup 2–3 times per week', 'benefit' => 'Blood sugar regulation and liver detox', 'caution' => 'Very bitter — dilute with water'],
                ['herb' => 'Moringa', 'local_name' => 'Ewe Igbale (Yoruba)', 'preparation' => 'Dried powder in smoothies or teas', 'dosage' => '1–2 teaspoons daily', 'benefit' => 'Rich in antioxidants; supports blood sugar and inflammation', 'caution' => 'Start with small amounts'],
                ['herb' => 'Fenugreek Seeds', 'local_name' => 'Hulba / Ewedu seed', 'preparation' => 'Soak overnight, drink water and chew seeds in morning', 'dosage' => '1 tablespoon soaked seeds daily', 'benefit' => 'Improves insulin sensitivity', 'caution' => 'Can cause mild digestive discomfort initially'],
                ['herb' => 'Turmeric', 'local_name' => 'Ata-ile pupa (Yoruba)', 'preparation' => 'Grate into warm water with black pepper or add to soups', 'dosage' => '1–2 teaspoons daily with black pepper', 'benefit' => 'Powerful anti-inflammatory — reduces CRP and supports liver', 'caution' => 'May interact with blood thinners'],
            ],
            'lifestyle_tips' => [
                ['category' => 'Sleep',       'tip' => 'Go to bed by 10 PM every night', 'detail' => 'Growth hormone and reproductive hormones are produced during deep sleep between 10 PM and 2 AM.'],
                ['category' => 'Stress',      'tip' => 'Practice 5-minute deep breathing twice daily', 'detail' => 'Box breathing (inhale 4s, hold 4s, exhale 4s, hold 4s) lowers cortisol reliably.'],
                ['category' => 'Movement',    'tip' => 'Walk 30 minutes after your largest meal', 'detail' => 'Post-meal walking reduces blood sugar spikes by up to 30%.'],
                ['category' => 'Hydration',   'tip' => 'Drink 2–3 litres of water daily', 'detail' => 'Dehydration concentrates hormones and toxins.'],
                ['category' => 'Environment', 'tip' => 'Switch to natural cleaning and beauty products', 'detail' => 'Commercial products often contain xenoestrogens that worsen PCOS.'],
                ['category' => 'Cycle',       'tip' => 'Begin tracking your menstrual cycle', 'detail' => 'Even if irregular, noting spotting, cramps, and mood changes helps you see patterns.'],
            ],
            'tracking_guidance' => $trackingGuidance,
            'quick_win' => [
                'title'  => 'Drink warm lemon water within 5 minutes of waking — TODAY',
                'detail' => 'This single habit sets your cortisol rhythm and kickstarts liver detox. Do it before coffee or food. Most women notice better morning energy within 3 days.',
            ],
            'encouragement' => "$name, you've already done something powerful — you've decided to take control of your health.\n\nThe next 90 days won't always be easy. In the hard moments, remember: your body WANTS to heal. Every step you take sends the signal that it's safe to restore balance.\n\nAt Day 90, imagine waking with steady energy, clearer skin, and a cycle finding its rhythm. That future is not a dream — it's what happens when you show up consistently. You are stronger than your PCOS.",
        ]);
    }
}
