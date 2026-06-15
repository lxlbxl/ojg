<?php
/**
 * MensProtocolGenerator (Vitale) — 90-Day Men's Vitality Protocol
 *
 * Module manifest: vitality nutrition + strength/movement (CORE) + sleep & recovery (CORE)
 *                  + herbs + supplements + vitality/energy logging.
 * No skincare routine. No cycle sync.
 */

require_once __DIR__ . '/AbstractProtocolGenerator.php';

class MensProtocolGenerator extends AbstractProtocolGenerator
{
    private array $typeMap = [
        'energy'         => 'Low Energy/Fatigue',
        'fatigue'        => 'Low Energy/Fatigue',
        'testosterone'   => 'Low-T Markers',
        'low_t'          => 'Low-T Markers',
        'libido'         => 'Low-T Markers',
        'stress'         => 'Stress/Burnout',
        'burnout'        => 'Stress/Burnout',
        'performance'    => 'Body Composition/Performance',
        'composition'    => 'Body Composition/Performance',
        'body'           => 'Body Composition/Performance',
        'general'        => 'Low Energy/Fatigue',
    ];

    public function getCondition(): string { return 'mens'; }

    public function getModuleManifest(): array
    {
        return [
            self::MODULE_MEAL_PLAN,
            self::MODULE_MOVEMENT,       // strength/recovery is CORE
            self::MODULE_HERBAL_PROTOCOL,
            self::MODULE_SUPPLEMENTS,
            self::MODULE_SLEEP_STRESS,   // sleep & recovery is CORE for men's
            self::MODULE_TRACKING,
            self::MODULE_PHASE_ARC,
        ];
    }

    protected function getPromptsDir(): string
    {
        return $this->promptsDir . '/mens';
    }

    protected function resolveSubType(array $assessment): string
    {
        $code = $assessment['mensType'] ?? $assessment['vitaleType'] ?? $assessment['type'] ?? $assessment['subType'] ?? 'general';
        return $this->typeMap[strtolower((string) $code)] ?? 'Low Energy/Fatigue';
    }

    protected function buildUserPromptVars(array $assessment, string $name, array $regionProfile): array
    {
        $subType = $this->resolveSubType($assessment);
        $age = (int) ($assessment['age'] ?? 30);
        $weight = (float) ($assessment['weight'] ?? 80); // kg
        $activityLevel = strtolower($assessment['activityLevel'] ?? $assessment['exerciseLevel'] ?? 'sedentary');
        $units = ($regionProfile['measurement_system'] ?? 'metric') === 'imperial' ? 'imperial' : 'metric';

        return [
            'NAME'                 => $name ?: 'Friend',
            'VITALE_TYPE'          => $subType,
            'AGE'                  => $age,
            'WEIGHT'               => $weight . ($units === 'imperial' ? ' lbs' : ' kg'),
            'ACTIVITY_LEVEL'       => $activityLevel,
            'PRIMARY_CONCERN'      => $assessment['primaryConcern'] ?? $subType,
            'SYMPTOMS'             => is_array($assessment['symptoms'] ?? null)
                ? implode(', ', $assessment['symptoms']) : ($assessment['symptoms'] ?? 'Low energy, fatigue'),
            'GOALS'                => is_array($assessment['goals'] ?? null)
                ? implode(', ', $assessment['goals']) : ($assessment['goals'] ?? 'More energy, strength, and vitality'),
            'DIETARY_RESTRICTIONS' => is_array($assessment['dietaryRestrictions'] ?? null)
                ? implode(', ', $assessment['dietaryRestrictions']) : 'None',
            'MEDICATIONS'          => is_array($assessment['medications'] ?? null)
                ? implode(', ', $assessment['medications']) : 'None',
            'SLEEP_QUALITY'        => $assessment['sleepQuality'] ?? 'Not specified',
            'STRESS_LEVEL'         => $assessment['stressLevel'] ?? 'Not specified',
            'SLEEP_HOURS'          => $assessment['sleepHours'] ?? 'Not specified',
            'MORNING_ENERGY'       => $assessment['morningEnergy'] ?? 'Not specified',
            'MEASUREMENT_SYSTEM'   => $units,
            'COUNTRY'              => $regionProfile['country'] ?? 'Nigeria',
            'REGION_PROFILE'       => '{}',
        ];
    }

    protected function validateConditionContent(array $content): array
    {
        $errors = [];

        if (empty($content['meal_plan']) || !is_array($content['meal_plan'])) {
            $errors[] = 'Mens: missing meal_plan';
        }
        if (empty($content['movement_plan']) && empty($content['strength_protocol']) && empty($content['exercise_plan'])) {
            $errors[] = 'Mens: missing movement/strength protocol (core module)';
        }
        if (empty($content['sleep_recovery_protocol']) && empty($content['sleep_protocol'])) {
            $errors[] = 'Mens: missing sleep & recovery protocol (core module)';
        }

        // MUST have vitality tracking
        $hasVitalityTracking = false;
        if (!empty($content['tracking_guidance']) && is_array($content['tracking_guidance'])) {
            foreach ($content['tracking_guidance'] as $t) {
                if (in_array($t['key'] ?? '', ['energy', 'vitality']) ||
                    str_contains(strtolower($t['what'] ?? ''), 'energy')) {
                    $hasVitalityTracking = true;
                    break;
                }
            }
        }
        if (!$hasVitalityTracking) {
            $errors[] = 'Mens: tracking_guidance must include energy/vitality logging';
        }

        // Must NOT have skincare routine or cycle content
        if (!empty($content['skincare_routine']) || !empty($content['cycle_sync'])) {
            $errors[] = 'Mens: forbidden skincare/cycle content present';
        }

        return $errors;
    }

    protected function getPlanLabel(string $subType): string
    {
        return "90-Day Vitale Men's Protocol ({$subType})";
    }

    protected function renderTemplate(callable $get, string $name, string $subType, array $assessment, array $regionProfile): string
    {
        $templatePath = $this->templatesDir . '/plan-template.html';
        $html = @file_get_contents($templatePath);
        if (!$html) throw new Exception('Could not load template: ' . $templatePath);

        $replace = function ($ph, $val) use (&$html) {
            $html = str_replace($ph, $val ?? '', $html);
        };

        $replace('{{NAME}}', $this->esc($name ?: 'Friend'));
        $replace('{{PCOS_TYPE}}', $this->esc($subType));
        $replace('{{CONDITION_LABEL}}', "Vitale — Men's Protocol");
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

        // Strength/movement protocol
        $strength = $get('movement_plan') ?: $get('strength_protocol') ?: $get('exercise_plan');
        $replace('{{MOVEMENT_PLAN}}', $this->renderMovementProtocol($strength ?: []));

        // Sleep & recovery (core for men's)
        $sleepProtocol = $get('sleep_recovery_protocol') ?: $get('sleep_protocol');
        $replace('{{SLEEP_RECOVERY}}', $this->renderSleepRecovery($sleepProtocol));

        $replace('{{SUPPLEMENTS}}', $this->renderSupplements($get('supplements')));
        $replace('{{HERBAL_PROTOCOLS}}', $this->renderHerbalProtocols($get('herbal_protocols')));
        $replace('{{LIFESTYLE_TIPS}}', $this->renderLifestyleTips($get('lifestyle_tips')));
        $replace('{{TRACKING_GUIDANCE}}', $this->renderTrackingGuidance($get('tracking_guidance')));
        $replace('{{QUICK_WIN}}', $this->renderQuickWin($get('quick_win')));
        $replace('{{SOURCING_GUIDE}}', $this->renderSourcingGuide($get('sourcing_guide'), $regionProfile));
        $replace('{{ENCOURAGEMENT}}', $this->renderEncouragement($get('encouragement')));

        return $html;
    }

    private function renderSleepRecovery($protocol): string
    {
        if (!is_array($protocol)) return is_string($protocol) ? '<p>' . $this->esc($protocol) . '</p>' : '';
        $html = '<div class="sleep-recovery">';
        if (!empty($protocol['overview'])) {
            $html .= '<p>' . $this->esc($protocol['overview']) . '</p>';
        }
        if (!empty($protocol['steps']) && is_array($protocol['steps'])) {
            $html .= '<ol>';
            foreach ($protocol['steps'] as $step) {
                $html .= '<li><strong>' . $this->esc($step['action'] ?? '') . '</strong>'
                    . (!empty($step['why']) ? ': <span>' . $this->esc($step['why']) . '</span>' : '')
                    . '</li>';
            }
            $html .= '</ol>';
        }
        $html .= '</div>';
        return $html;
    }

    protected function getFallbackContent(string $subType, string $name): array
    {
        $trackingGuidance = [
            ['key' => 'energy',          'label' => 'Energy (1–10)',              'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate energy at 8 AM and 3 PM',                          'why' => 'Primary vitality marker — tracks T and cortisol balance', 'chart' => 'trend'],
            ['key' => 'libido',          'label' => 'Libido (1–10)',              'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate libido/sexual interest each morning',              'why' => 'Most sensitive testosterone marker — improves weeks before blood tests', 'chart' => 'trend'],
            ['key' => 'focus',           'label' => 'Mental Focus (1–10)',        'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate concentration and mental clarity',                  'why' => 'Brain-T connection — low T impairs cognition and drive', 'chart' => 'trend'],
            ['key' => 'sleep_hours',     'label' => 'Sleep Hours',                'type' => 'number',  'frequency' => 'daily',  'how' => 'Log hours slept each night',                            'why' => '80% of daily testosterone is produced during sleep — sleep IS recovery', 'chart' => 'trend'],
            ['key' => 'sleep_quality',   'label' => 'Sleep Quality (1–10)',       'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate sleep quality upon waking',                        'why' => 'Quality matters more than quantity — deep sleep = testosterone production', 'chart' => 'trend'],
            ['key' => 'strength_done',   'label' => 'Strength Session Complete',  'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Did you complete your planned strength session?',       'why' => 'Resistance training is the most potent natural testosterone stimulus', 'chart' => 'streak'],
            ['key' => 'mood',            'label' => 'Mood (1–10)',                'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate overall mood and emotional resilience',             'why' => 'T and cortisol balance directly affects mood stability', 'chart' => 'trend'],
        ];

        $typeDescriptions = [
            'Low Energy/Fatigue' => [
                'summary'    => "$name, your pattern — afternoon energy crashes, slow recovery, difficulty waking, and persistent fatigue — signals that your energy systems need a fundamental reset. Whether driven by cortisol dysregulation, suboptimal testosterone, poor sleep, or blood-sugar instability, the Vitale protocol addresses all the root mechanisms simultaneously.",
                'root_cause' => "Low energy in men rarely has a single cause. The most common pattern: cortisol disrupts sleep architecture, reducing the deep-sleep stages when testosterone is produced. Low testosterone then reduces motivation, recovery, and metabolic efficiency — creating a fatigue cycle that feels impossible to escape.\n\nBlood sugar instability is often the trigger: the afternoon crash is typically a blood-glucose drop after a carbohydrate-heavy lunch. This spike-crash cycle strains the adrenal glands and keeps cortisol elevated all day.\n\nThe Vitale protocol addresses all three legs: sleep restoration (where T production happens), blood-sugar stability (which stabilises energy), and targeted nutrients that support testosterone production.",
                'goals'      => ['Restore consistent morning energy within 4 weeks', 'Improve sleep quality and recovery', 'Eliminate afternoon energy crashes', 'Optimise testosterone production through nutrition and lifestyle', 'Build sustainable daily energy that doesn\'t rely on caffeine'],
            ],
            'Low-T Markers' => [
                'summary'    => "$name, your symptoms — low energy, reduced libido, mood changes, difficulty building muscle, and increased belly fat — strongly suggest suboptimal testosterone. Before starting supplements, please get labs done (Total T, Free T, LH, FSH, SHBG, prolactin). The Vitale protocol uses proven lifestyle interventions that can increase testosterone 20–30% without medication.",
                'root_cause' => "Testosterone decline in men under 50 is increasingly common and driven by lifestyle factors: chronic stress (cortisol is antagonistic to T), poor sleep (80% of T is produced during sleep), excessive body fat (adipose tissue converts T to oestrogen), nutritional deficiencies (zinc, vitamin D, magnesium are T cofactors), and sedentary lifestyle.\n\nIMPORTANT: Please request blood tests before supplementing with testosterone-related herbs. The Vitale protocol focuses exclusively on safe, evidence-based lifestyle interventions — not prescriptions or exogenous hormones.\n\nCardiac risk note: if you are over 40 or have cardiovascular history, please consult your doctor before beginning intense exercise protocols.",
                'goals'      => ['Get baseline testosterone labs done in Week 1', 'Increase free testosterone through sleep, strength, and nutrition', 'Reduce SHBG-binding (boron, vitamin D) to increase free T availability', 'Build lean muscle mass (most effective natural T signal)', 'Restore libido, mood, and cognitive drive within 60 days'],
            ],
            'Stress/Burnout' => [
                'summary'    => "$name, the wired-but-tired feeling, poor sleep despite exhaustion, irritability, and declining performance are classic burnout markers — the result of chronic HPA-axis dysregulation. Your cortisol rhythm has inverted: high at night (preventing sleep) and low in the morning (causing the morning fatigue). The Vitale recovery protocol is designed specifically for this state.",
                'root_cause' => "Burnout is a physiological state — not a mindset issue. Chronic stress drives the HPA (hypothalamic-pituitary-adrenal) axis into a pattern of cortisol dysregulation: cortisol that stays elevated into the evening preventing restorative sleep, and adrenal output that declines during the day making mornings feel impossible.\n\nThis chronic cortisol elevation directly suppresses testosterone, impairs memory (cortisol is neurotoxic to the hippocampus), and promotes abdominal fat storage. The wired-tired feeling is biochemically real.\n\nThe Vitale stress protocol prioritises HPA-axis restoration: adaptogenic herbs (ashwagandha has clinical evidence for cortisol reduction), targeted sleep hygiene, and gentler movement that doesn't add additional stress load.",
                'goals'      => ['Restore healthy cortisol diurnal rhythm (high morning, low evening)', 'Improve sleep onset and sleep quality within 2 weeks', 'Reduce stress reactivity through adaptogens and practices', 'Rebuild testosterone through restored sleep and reduced cortisol', 'Return to baseline performance and resilience within 60 days'],
            ],
            'Body Composition/Performance' => [
                'summary'    => "$name, your goal — improving body composition, building functional strength, and optimising performance — is best achieved through a system that works with your hormonal environment. The Vitale performance protocol maximises anabolic hormones (testosterone, IGF-1, growth hormone) through strategic nutrition, progressive resistance training, and recovery optimisation.",
                'root_cause' => "Muscle gain and fat loss are fundamentally hormonal processes. The primary anabolic hormones — testosterone, growth hormone, and IGF-1 — are directly optimised through: resistance training (stimulus), protein intake (building blocks), sleep (production), and specific nutrients (cofactors).\n\nMost men fail at body recomposition because they prioritise training while neglecting sleep and nutrition. The truth: 80% of muscle growth happens outside the gym during recovery. The Vitale performance protocol treats recovery as primary, training as the stimulus.",
                'goals'      => ['Build functional strength with progressive overload', 'Optimise muscle protein synthesis through nutrition timing', 'Reduce body fat through hormonal optimisation (not just calorie restriction)', 'Improve VO2 max and physical performance markers', 'Reach target body composition milestone by Day 90'],
            ],
        ];

        $typeContent = $typeDescriptions[$subType] ?? $typeDescriptions['Low Energy/Fatigue'];

        return array_merge($typeContent, [
            'phase_1_title'          => 'Restore & Reset',
            'phase_1_focus'          => 'Foundation: sleep, nutrition, and baseline strength',
            'phase_1_description'    => 'The first 30 days focus on what you cannot train your way out of: sleep quality, nutritional foundation, and hormonal baseline. Every physical result in this protocol is downstream of sleep and nutrition. We build here first.',
            'phase_1_weekly_actions' => [
                ['week' => 1, 'focus' => 'Sleep & Foundations',  'actions' => ['Establish consistent sleep and wake times (non-negotiable)', 'Begin protein-first nutrition: 1.6–2g protein per kg bodyweight', 'Start zinc, magnesium, and vitamin D supplementation', 'Take baseline: energy, libido, and mood scores (1–10)'], 'milestone' => 'Sleep is consistent; supplements started; baseline logged'],
                ['week' => 2, 'focus' => 'Movement Foundation',  'actions' => ['Begin 3× per week resistance training (compound movements only: squat, hinge, press, pull)', 'Add daily 20-minute morning walk', 'Reduce caffeine after 12 PM', 'Morning cold exposure: 30 seconds cold at end of shower'], 'milestone' => 'Strength training is consistent; sleep improving'],
                ['week' => 3, 'focus' => 'Herbal & Recovery',    'actions' => ['Add ashwagandha (or regional equivalent) to evening routine', 'Post-workout nutrition: protein within 30 minutes', 'Add 5-minute morning breathwork (activates parasympathetic)', 'Review energy score trend — first improvements typically appear now'], 'milestone' => 'Energy scores trending upward; recovery improving'],
                ['week' => 4, 'focus' => 'Consolidation',        'actions' => ['Full protocol active: training + nutrition + sleep + supplements', 'Assess: energy, libido, mood — compare to Week 1 baseline', 'Fine-tune meal timing for training performance', 'Prepare for Phase 2 intensity increase'], 'milestone' => 'Foundational habits locked in; baseline improvements confirmed'],
            ],
            'phase_2_title'          => 'Build & Optimise',
            'phase_2_focus'          => 'Progressive strength and hormonal optimisation',
            'phase_2_description'    => 'With your foundation set, Phase 2 progressively loads the training stimulus, intensifies the nutritional protocol, and adds advanced recovery techniques. This is where testosterone-supportive adaptations peak.',
            'phase_2_weekly_actions' => [
                ['week' => 5, 'focus' => 'Progressive Overload', 'actions' => ['Increase training volume: 4× strength sessions', 'Add resistance/weight to all exercises', 'Introduce creatine (most evidence-based performance supplement)', 'Review sleep quality — deep sleep is when T production peaks'], 'milestone' => 'Strength gains measurable; physique changes visible'],
                ['week' => 6, 'focus' => 'Hormonal Peak',        'actions' => ['Maximum training stimulus: 4× strength + 2× conditioning', 'Nutrition optimisation: carb timing around training', 'Ashwagandha reaches full effect (~6 weeks)', 'Mid-protocol energy, libido, and strength assessment'], 'milestone' => 'Peak protocol engagement; best energy to date'],
                ['week' => 7, 'focus' => 'Recovery Priority',    'actions' => ['Active recovery day: walking and stretching only', 'Deload week planning (slight volume reduction to prevent overtraining)', 'Sleep protocol audit: are you hitting 7–9 hours?', 'Social and stress management review'], 'milestone' => 'Recovery as skilled as training'],
                ['week' => 8, 'focus' => 'Peak Month 2',         'actions' => ['2-month assessment: energy, libido, focus, strength', 'Progress photos (if relevant to goals)', 'Plan Phase 3 performance goals', 'Celebrate Month 2 transformation'], 'milestone' => 'Maximum 60-day improvement'],
            ],
            'phase_3_title'          => 'Peak & Sustain',
            'phase_3_focus'          => 'Locking in vitality as your permanent baseline',
            'phase_3_description'    => 'The final phase locks in your new vitality baseline and builds the systems that maintain it permanently — without the intensity of Phase 2. This is the transition from protocol to lifestyle.',
            'phase_3_weekly_actions' => [
                ['week' => 9,  'focus' => 'Maintenance Training', 'actions' => ['Identify minimum effective training dose (usually 3× strength/week)', 'Implement long-term programming structure', 'Focus on strength mastery and form refinement', 'Add sport or recreational activity (increases adherence 80%)'], 'milestone' => 'Sustainable training structure identified'],
                ['week' => 10, 'focus' => 'Lifestyle Integration', 'actions' => ['Protocol works during travel, social events, and high-stress periods', 'Long-term supplement strategy (what to maintain, what to cycle)', 'Mentor a friend or partner in the protocol', 'Plan annual health checks (T levels, metabolic markers)'], 'milestone' => 'Protocol is lifestyle, not routine'],
                ['week' => 11, 'focus' => 'Future Planning',      'actions' => ['Create personalised off-season/maintenance plan', 'Seasonal training adjustments', 'Stress-proof your recovery: emergency protocols for high-demand periods', 'Long-term performance goals'], 'milestone' => 'Self-sufficient vitality management'],
                ['week' => 12, 'focus' => 'Transformation',       'actions' => ['90-day full assessment: energy, libido, focus, strength, body composition', 'Compare to Week 1 baseline scores', 'Write your personal performance story', 'Set 6-month and 1-year vitality goals'], 'milestone' => 'Transformed — permanent new baseline'],
            ],
            'morning_routine' => [
                ['time' => '6:00 AM', 'action' => 'Wake at consistent time — no snoozing', 'why' => 'Consistent wake time anchors cortisol rhythm; T peaks in morning — don\'t miss it'],
                ['time' => '6:05 AM', 'action' => '30 seconds cold water at end of shower', 'why' => 'Cold exposure triggers norepinephrine release and increases morning T acutely'],
                ['time' => '6:15 AM', 'action' => 'Morning sunlight exposure: 5–10 minutes outside', 'why' => 'Sunlight synchronises cortisol rhythm and is the primary vitamin D trigger'],
                ['time' => '6:30 AM', 'action' => 'High-protein breakfast (30–40g protein)', 'why' => 'Morning protein maximises muscle protein synthesis; skipping breakfast lowers T'],
                ['time' => '7:00 AM', 'action' => 'Morning supplement stack (zinc, vitamin D, vitamin C)', 'why' => 'Zinc is an essential T cofactor; vitamin D is a steroid precursor'],
            ],
            'afternoon_routine' => [
                ['time' => '12:30 PM', 'action' => 'Protein-first lunch: meat/fish + vegetables + moderate starch', 'why' => 'Prevents afternoon energy crash; protein supports muscle repair from morning training'],
                ['time' => '1:00 PM',  'action' => '10-minute walk or movement break', 'why' => 'Prevents post-lunch blood glucose crash that causes the 2 PM slump'],
                ['time' => '3:00 PM',  'action' => 'If training today: pre-workout snack 30–60 min before', 'why' => 'Carbohydrates before training prevent cortisol elevation from energy depletion'],
            ],
            'evening_routine' => [
                ['time' => '5:30 PM',  'action' => 'Strength session (if afternoon training)', 'why' => 'Testosterone peaks between 4–6 PM — optimal training window for T response'],
                ['time' => '7:00 PM',  'action' => 'Post-workout: protein + carbohydrates within 30 minutes', 'why' => 'Anabolic window: protein synthesis is elevated 2× for 30 minutes post-training'],
                ['time' => '8:00 PM',  'action' => 'Evening supplements: magnesium glycinate + ashwagandha', 'why' => 'Magnesium deepens sleep quality; ashwagandha reduces evening cortisol by 28%'],
                ['time' => '9:00 PM',  'action' => 'Screens off — no blue light after 9 PM', 'why' => 'Blue light suppresses melatonin, delaying deep sleep when T is produced'],
                ['time' => '9:30 PM',  'action' => 'Wind down: reading, journaling, or relaxation', 'why' => 'Activation of parasympathetic system shifts body from fight-or-flight to repair mode'],
                ['time' => '10:00 PM', 'action' => 'Sleep: 7–9 hours minimum', 'why' => '80% of daily T is produced during sleep — this is your testosterone factory'],
            ],
            'meal_plan' => [
                ['day' => 1, 'breakfast' => ['meal' => 'Egg and Mackerel Omelette', 'description' => 'Three eggs with canned mackerel, onions, and peppers', 'benefit' => '45g protein; mackerel provides omega-3 and zinc — both T cofactors'], 'lunch' => ['meal' => 'Grilled Beef with Brown Rice and Vegetables', 'description' => 'Lean beef steak with 100g brown rice and mixed roasted vegetables', 'benefit' => 'Beef contains zinc, iron, and CLA — all support testosterone and muscle'], 'dinner' => ['meal' => 'Egusi Soup with Goat Meat', 'description' => 'Rich egusi stew with lean goat meat and spinach', 'benefit' => 'High protein, zinc from egusi seeds, iron from leafy greens'], 'snack' => ['meal' => 'Brazil Nuts and Dark Chocolate', 'description' => '3 Brazil nuts with 2 squares (20g) dark chocolate (85%+)', 'benefit' => 'Brazil nuts: selenium for T production. Dark chocolate: magnesium and mood support']],
                ['day' => 2, 'breakfast' => ['meal' => 'Protein Smoothie with Oats', 'description' => 'Protein powder, banana, oats, almond butter, and milk/plant milk', 'benefit' => 'Convenient high-protein start; oats contain zinc and avenacosides (may raise free T)'], 'lunch' => ['meal' => 'Chicken Pepper Soup', 'description' => 'Rich chicken broth with traditional spices and vegetables', 'benefit' => 'Anti-inflammatory spices; lean protein; hydrating'], 'dinner' => ['meal' => 'Jollof Rice with Double Chicken', 'description' => 'Tomato jollof with extra grilled chicken breast (protein priority)', 'benefit' => 'Carbohydrates replenish muscle glycogen after training; protein supports repair'], 'snack' => ['meal' => 'Mixed Nuts (30g)', 'description' => 'Almonds, walnuts, cashews — unsalted', 'benefit' => 'Omega-3 from walnuts, arginine from almonds, magnesium from cashews']],
                ['day' => 3, 'breakfast' => ['meal' => 'Moi Moi with Boiled Eggs', 'description' => 'Bean pudding with three boiled eggs', 'benefit' => 'Complete protein combination; eggs are the richest whole-food source of cholesterol (T precursor)'], 'lunch' => ['meal' => 'Tuna and Bean Salad', 'description' => 'Canned tuna with mixed beans, avocado, and olive oil dressing', 'benefit' => 'Omega-3 from tuna; plant protein from beans; healthy fats from avocado'], 'dinner' => ['meal' => 'Lamb Stew with Vegetables', 'description' => 'Slow-cooked lean lamb with root vegetables and herbs', 'benefit' => 'Lamb is the richest red meat source of zinc — essential for testosterone synthesis'], 'snack' => ['meal' => 'Greek Yoghurt with Pumpkin Seeds', 'description' => '200g plain full-fat Greek yoghurt with 2 tablespoons pumpkin seeds', 'benefit' => 'Probiotics + magnesium + zinc from pumpkin seeds']],
                ['day' => 4, 'breakfast' => ['meal' => 'Sweet Potato and Egg Scramble', 'description' => 'Diced sweet potato roasted with scrambled eggs, peppers, and spinach', 'benefit' => 'Complex carbohydrates support training energy; eggs provide cholesterol for T'], 'lunch' => ['meal' => 'Beef and Vegetable Stir-Fry with Brown Rice', 'description' => 'Lean beef strips with broccoli, peppers, and brown rice', 'benefit' => 'Broccoli contains DIM — reduces oestrogen and relatively increases T'], 'dinner' => ['meal' => 'Baked Whole Fish with Plantain', 'description' => 'Baked tilapia with steamed green plantain and salad', 'benefit' => 'Fish omega-3 reduces SHBG (which binds T making it inactive)'], 'snack' => ['meal' => 'Banana with Almond Butter', 'description' => 'Medium banana with 2 tablespoons almond butter', 'benefit' => 'Pre-training fuel; potassium from banana supports muscle function']],
                ['day' => 5, 'breakfast' => ['meal' => 'Akara with Scrambled Eggs', 'description' => 'Bean cakes with two scrambled eggs and tomato sauce', 'benefit' => 'High plant protein from beans; egg yolks are rich in vitamin D and cholesterol'], 'lunch' => ['meal' => 'Oxtail Soup (lean cut)', 'description' => 'Rich oxtail broth with leafy greens and yam', 'benefit' => 'Collagen for joint recovery; minerals for testosterone'], 'dinner' => ['meal' => 'Grilled Chicken with Quinoa and Broccoli', 'description' => 'Herb-seasoned chicken with quinoa and steamed broccoli', 'benefit' => 'Complete protein in quinoa; broccoli DIM reduces oestrogen'], 'snack' => ['meal' => 'Boiled Eggs (2)', 'description' => 'Two hard-boiled eggs', 'benefit' => 'Most bioavailable protein source; one egg has 186mg cholesterol (T precursor)']],
                ['day' => 6, 'breakfast' => ['meal' => 'Chia Pudding with Protein', 'description' => 'Coconut milk chia pudding with protein powder and banana', 'benefit' => 'Omega-3 from chia; easy digestion pre-morning training'], 'lunch' => ['meal' => 'Efo Riro with Assorted Meat', 'description' => 'Spinach stew with beef, chicken, and stockfish', 'benefit' => 'Iron from spinach combats fatigue; multiple protein sources for muscle'], 'dinner' => ['meal' => 'Salmon with Sweet Potato and Greens', 'description' => 'Baked salmon fillet with roasted sweet potato and steamed greens', 'benefit' => 'Highest omega-3 meal of the week; vitamin D from salmon'], 'snack' => ['meal' => 'Pumpkin Seeds', 'description' => 'Handful of roasted pumpkin seeds (no salt)', 'benefit' => 'Richest whole-food source of zinc after oysters']],
                ['day' => 7, 'breakfast' => ['meal' => 'Full Nigerian Breakfast', 'description' => 'Two fried eggs, beans (no sugar), one plantain, tomato sauce — no bread', 'benefit' => 'High protein, moderate carbs, anti-inflammatory tomato lycopene'], 'lunch' => ['meal' => 'Coconut Rice with Grilled Beef', 'description' => 'Coconut milk rice with seasoned grilled beef and salad', 'benefit' => 'MCT fats from coconut support testosterone production; zinc from beef'], 'dinner' => ['meal' => 'Pepper Soup with Fish', 'description' => 'Spiced fish broth with traditional herbs', 'benefit' => 'Anti-inflammatory; omega-3; low calorie — ideal recovery dinner'], 'snack' => ['meal' => 'Dark Chocolate (20g) with Walnuts', 'description' => '85%+ dark chocolate with 5 walnuts', 'benefit' => 'Magnesium from chocolate; omega-3 from walnuts; mood support']],
            ],
            'movement_plan' => [
                'overview' => 'Vitale strength protocol: compound movements, progressive overload, and strategic recovery. Testosterone production peaks with compound lifts (squat, deadlift, bench press) — not isolation exercises.',
                'weeks' => [
                    ['week' => '1–2',  'focus' => 'Movement Mastery',    'sessions' => '3×/week: full-body compound (squat, hinge, press, row)', 'progression' => 'Form mastery before load'],
                    ['week' => '3–4',  'focus' => 'Building Load',       'sessions' => '3–4×/week: upper/lower split', 'progression' => '5–10% load increase when you hit top of rep range'],
                    ['week' => '5–8',  'focus' => 'Progressive Overload','sessions' => '4×/week: push/pull/legs + conditioning', 'progression' => 'Systematic volume and intensity progression'],
                    ['week' => '9–12', 'focus' => 'Peak Performance',    'sessions' => '4–5×/week + active recovery', 'progression' => 'Peak strength; introduce periodisation'],
                ],
            ],
            'sleep_recovery_protocol' => [
                'overview' => 'Sleep is the single most powerful testosterone intervention available without medication. 80% of daily testosterone is produced during sleep — specifically during deep sleep stages 3 and 4. These steps maximise deep sleep.',
                'steps' => [
                    ['action' => 'Consistent sleep and wake time (±30 min)', 'why' => 'Circadian rhythm anchors testosterone release'],
                    ['action' => 'Room temperature: 18–20°C (cool room)', 'why' => 'Core temperature drop triggers deep sleep'],
                    ['action' => 'Blackout curtains or sleep mask', 'why' => 'Light suppresses melatonin even through closed eyelids'],
                    ['action' => 'No screens 60 minutes before sleep', 'why' => 'Blue light shifts melatonin onset 90 minutes later'],
                    ['action' => 'Magnesium glycinate 300mg before bed', 'why' => 'Magnesium is the most evidence-backed sleep supplement'],
                    ['action' => 'Ashwagandha 300–600mg at night', 'why' => 'Reduces evening cortisol, directly improving sleep quality'],
                    ['action' => 'Avoid alcohol (including moderate amounts)', 'why' => 'Even 2 units reduces REM sleep and next-day testosterone by 20%'],
                ],
            ],
            'supplements' => [
                ['name' => 'Zinc (Picolinate or Bisglycinate)', 'dosage' => '25–30mg daily', 'timing' => 'With dinner', 'benefit' => 'Zinc is required for testosterone synthesis — deficiency directly reduces T levels', 'note' => 'Do not exceed 40mg/day. Take with food to avoid nausea.'],
                ['name' => 'Ashwagandha (KSM-66 or Sensoril)', 'dosage' => '300–600mg daily', 'timing' => 'Evening before bed', 'benefit' => 'Reduces cortisol by 28% and increases testosterone by 17% in clinical trials (KSM-66)', 'note' => 'Do not exceed 600mg/day. Avoid if thyroid conditions without doctor consult.'],
                ['name' => 'Magnesium Glycinate',               'dosage' => '300–400mg daily', 'timing' => 'Evening 30–60 minutes before sleep', 'benefit' => 'Improves deep sleep quality — which is when 80% of daily T is produced. Also reduces cortisol.', 'note' => 'Glycinate form is most bioavailable and least laxative'],
                ['name' => 'Vitamin D3 + K2',                   'dosage' => '3000–5000 IU D3 + 100mcg K2 daily', 'timing' => 'Morning with a fatty meal', 'benefit' => 'Vitamin D is a steroid hormone precursor — deficiency is linked to 65% lower testosterone', 'note' => 'K2 ensures calcium goes to bones not arteries. Test levels if possible.'],
                ['name' => 'Creatine Monohydrate',              'dosage' => '3–5g daily', 'timing' => 'Any time — consistency matters more than timing', 'benefit' => 'Increases strength, muscle mass, and DHT (active testosterone form). Most evidence-based supplement.', 'note' => 'Load with 20g/day for 5 days optionally, then 5g maintenance'],
                ['name' => 'Omega-3 Fish Oil',                  'dosage' => '2000–3000mg EPA+DHA daily', 'timing' => 'With meals', 'benefit' => 'Reduces SHBG (sex hormone binding globulin) — increases FREE testosterone availability', 'note' => 'Choose quality brand tested for heavy metals'],
            ],
            'herbal_protocols' => [
                ['herb' => 'Ashwagandha', 'local_name' => 'Winter cherry / Withania somnifera (available globally)', 'preparation' => 'Capsule preferred; powder can be mixed into warm milk', 'dosage' => '300–600mg standardised extract (KSM-66 or Sensoril) daily', 'benefit' => 'Clinical evidence: reduces cortisol 28%, increases T 17%, improves sleep quality, increases muscle strength', 'caution' => 'Do not exceed 600mg/day; avoid in thyroid disorders without medical supervision; avoid in pregnancy'],
                ['herb' => 'Moringa',     'local_name' => 'Ewe Igbale (Yoruba) / Zogale (Hausa) / Ben oil tree', 'preparation' => '1 teaspoon powder in smoothie, water, or food daily', 'dosage' => '1–2 teaspoons daily', 'benefit' => 'Zinc, vitamin C, iron — all T cofactors. Reduces inflammation and oxidative stress.', 'caution' => 'Very safe at food doses; high doses not studied'],
                ['herb' => 'Bitter Kola (Garcinia kola)', 'local_name' => 'Orogbo (Yoruba) / Namijin goro (Hausa)', 'preparation' => 'Chew 1–2 seeds or brew as tea', 'dosage' => '1–2 seeds per day (not more)', 'benefit' => 'Traditional vitality herb; contains kolaviron with anti-inflammatory and antioxidant properties', 'caution' => 'Limit to 1–2 seeds daily; avoid if on anticoagulants; do not use as libido drug substitute'],
                ['herb' => 'Fenugreek Seeds', 'local_name' => 'Hulba (Arabic/Hausa) / Methi (widely available)', 'preparation' => 'Soak overnight and drink water, or take 500mg capsule', 'dosage' => '500mg standardised extract daily (or 1 tbsp soaked seeds)', 'benefit' => 'Some evidence for increasing free testosterone by reducing SHBG binding; improves libido in clinical trials', 'caution' => 'Can cause maple-syrup odour; avoid if on blood thinners'],
            ],
            'lifestyle_tips' => [
                ['category' => 'Sleep',          'tip' => 'Treat sleep as your primary testosterone intervention', 'detail' => 'One week of sleeping 5 hours instead of 8 reduces testosterone by 10–15%. No supplement can compensate for poor sleep. This is non-negotiable.'],
                ['category' => 'Strength',       'tip' => 'Compound lifts (squat, deadlift, bench) trigger the most T response', 'detail' => 'Isolation exercises (bicep curls, leg extensions) produce minimal hormonal response. Compound movements that recruit large muscle groups trigger peak T and growth hormone release.'],
                ['category' => 'Stress',         'tip' => 'Cortisol and testosterone are antagonists — reduce one, the other rises', 'detail' => 'Five minutes of daily breathwork, cold showers, and nature walks all reduce cortisol measurably. Chronic stress is the most common cause of suboptimal T in modern men.'],
                ['category' => 'Alcohol',        'tip' => 'Even moderate alcohol significantly reduces testosterone', 'detail' => 'Two drinks reduce testosterone by 20% for 24 hours. This is not a moral judgement — it is biochemistry. Reduce to 0–1 drink per week for optimal T production.'],
                ['category' => 'Body Fat',       'tip' => 'Reduce visceral fat: fat tissue converts T to oestrogen', 'detail' => 'Aromatase enzyme in fat tissue converts testosterone to estradiol. Reducing body fat is one of the most effective ways to increase free testosterone.'],
                ['category' => 'Sunlight',       'tip' => '10 minutes of morning sunlight: vitamin D and cortisol rhythm', 'detail' => 'Vitamin D from sunlight is more bioavailable than supplements. Morning sunlight also synchronises cortisol rhythm and increases serotonin.'],
                ['category' => 'Competition',    'tip' => 'Pursue goals and healthy competition — T spikes with achievement', 'detail' => 'Testosterone rises with goal achievement, competition, and status. This is not toxic — it is human physiology. Pursue meaningful challenges.'],
            ],
            'tracking_guidance' => $trackingGuidance,
            'quick_win' => [
                'title'  => 'Do 10 push-ups RIGHT NOW — before you start this plan',
                'detail' => 'This is not a warm-up. This is the first anabolic signal you\'re sending your body today. Testosterone production responds to demand — give it a demand now. If you can\'t do 10 full push-ups, do them from your knees. This counts. This is Day 1.',
            ],
            'encouragement' => "$name, here is the truth about vitality that most people never learn: your energy, drive, and strength are not fixed. They are dynamic — they respond to signals.\n\nEvery training session signals your body to produce more testosterone. Every good night of sleep gives your body the factory time it needs. Every nutrient you eat either supports or undermines the hormonal environment you\'re building.\n\nAt Day 90, you won\'t just feel better. You\'ll have built a new baseline — a new normal for what your energy, focus, and strength feel like. This is yours to keep.\n\nLet\'s build.",
        ]);
    }
}
