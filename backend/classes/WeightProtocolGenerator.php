<?php
/**
 * WeightProtocolGenerator (LeanFlow) — 90-Day Metabolic Protocol
 *
 * Module manifest: meal plan + progressive movement (CORE) + herbs + supplements
 *                  + sleep/stress + trend-smoothed weight tracking.
 * No skincare routine. No cycle sync (unless hormonal type + female).
 */

require_once __DIR__ . '/AbstractProtocolGenerator.php';

class WeightProtocolGenerator extends AbstractProtocolGenerator
{
    private array $typeMap = [
        'insulin'       => 'Insulin-Resistant/Metabolic',
        'metabolic'     => 'Insulin-Resistant/Metabolic',
        'stress'        => 'Stress/Cortisol',
        'cortisol'      => 'Stress/Cortisol',
        'hormonal'      => 'Hormonal (Thyroid/Perimenopause)',
        'thyroid'       => 'Hormonal (Thyroid/Perimenopause)',
        'perimenopause' => 'Hormonal (Thyroid/Perimenopause)',
        'habit'         => 'Habit/Lifestyle',
        'lifestyle'     => 'Habit/Lifestyle',
        'general'       => 'Insulin-Resistant/Metabolic',
    ];

    public function getCondition(): string { return 'weight'; }

    public function getModuleManifest(): array
    {
        return [
            self::MODULE_MEAL_PLAN,
            self::MODULE_MOVEMENT,       // progressive movement is CORE
            self::MODULE_HERBAL_PROTOCOL,
            self::MODULE_SUPPLEMENTS,
            self::MODULE_SLEEP_STRESS,
            self::MODULE_TRACKING,
            self::MODULE_PHASE_ARC,
        ];
    }

    protected function getPromptsDir(): string
    {
        return $this->promptsDir . '/weight';
    }

    protected function resolveSubType(array $assessment): string
    {
        $code = $assessment['weightType'] ?? $assessment['type'] ?? $assessment['subType'] ?? 'general';
        return $this->typeMap[strtolower((string) $code)] ?? 'Insulin-Resistant/Metabolic';
    }

    protected function buildUserPromptVars(array $assessment, string $name, array $regionProfile): array
    {
        $subType = $this->resolveSubType($assessment);

        // Compute BMR/TDEE if enough data
        $age    = (int) ($assessment['age'] ?? 30);
        $weight = (float) ($assessment['weight'] ?? 70); // kg
        $height = (float) ($assessment['height'] ?? 165); // cm
        $activityMultipliers = ['sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55, 'active' => 1.725, 'very_active' => 1.9];
        $activityLevel = strtolower($assessment['activityLevel'] ?? $assessment['exerciseLevel'] ?? 'sedentary');
        $multiplier = $activityMultipliers[$activityLevel] ?? 1.2;
        $isFemale   = strtolower($assessment['sex'] ?? $assessment['gender'] ?? 'female') !== 'male';

        if ($isFemale) {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        }
        $tdee = (int) round($bmr * $multiplier);
        $units = ($regionProfile['measurement_system'] ?? 'metric') === 'imperial' ? 'imperial' : 'metric';
        $weightUnit = $units === 'imperial' ? 'lbs' : 'kg';

        return [
            'NAME'                 => $name ?: 'Friend',
            'WEIGHT_TYPE'          => $subType,
            'AGE'                  => $age,
            'WEIGHT'               => $weight . ' ' . $weightUnit,
            'HEIGHT'               => $height . ($units === 'imperial' ? ' inches' : ' cm'),
            'SEX'                  => $isFemale ? 'female' : 'male',
            'ACTIVITY_LEVEL'       => $activityLevel,
            'ESTIMATED_TDEE'       => $tdee . ' kcal/day',
            'TARGET_DEFICIT'       => '300–500 kcal/day (sustainable deficit)',
            'GOAL_WEIGHT'          => $assessment['goalWeight'] ?? 'Not specified',
            'SYMPTOMS'             => is_array($assessment['symptoms'] ?? null)
                ? implode(', ', $assessment['symptoms']) : ($assessment['symptoms'] ?? 'Weight management challenges'),
            'GOALS'                => is_array($assessment['goals'] ?? null)
                ? implode(', ', $assessment['goals']) : ($assessment['goals'] ?? 'Sustainable weight loss and metabolic health'),
            'DIETARY_RESTRICTIONS' => is_array($assessment['dietaryRestrictions'] ?? null)
                ? implode(', ', $assessment['dietaryRestrictions']) : 'None',
            'MEDICATIONS'          => is_array($assessment['medications'] ?? null)
                ? implode(', ', $assessment['medications']) : 'None',
            'SLEEP_QUALITY'        => $assessment['sleepQuality'] ?? 'Not specified',
            'STRESS_LEVEL'         => $assessment['stressLevel'] ?? 'Not specified',
            'MEASUREMENT_SYSTEM'   => $units,
            'COUNTRY'              => $regionProfile['country'] ?? 'Nigeria',
            'REGION_PROFILE'       => '{}',
        ];
    }

    protected function validateConditionContent(array $content): array
    {
        $errors = [];

        if (empty($content['meal_plan']) || !is_array($content['meal_plan'])) {
            $errors[] = 'Weight: missing meal_plan';
        }
        if (empty($content['movement_plan']) && empty($content['movement'] ?? null) && empty($content['exercise_plan'])) {
            $errors[] = 'Weight: missing movement_plan (core for LeanFlow)';
        }

        // MUST have trend-smoothed weight tracking
        $hasWeightTracking = false;
        if (!empty($content['tracking_guidance']) && is_array($content['tracking_guidance'])) {
            foreach ($content['tracking_guidance'] as $t) {
                if (in_array($t['key'] ?? '', ['weight', 'weight_trend']) ||
                    str_contains(strtolower($t['what'] ?? ''), 'weight')) {
                    $hasWeightTracking = true;
                    break;
                }
            }
        }
        if (!$hasWeightTracking) {
            $errors[] = 'Weight: tracking_guidance must include weight trend logging';
        }

        // Must NOT have skincare routine
        if (!empty($content['skincare_routine']) || !empty($content['am_pm_routine'])) {
            $errors[] = 'Weight: forbidden skincare_routine block present';
        }

        return $errors;
    }

    protected function getPlanLabel(string $subType): string
    {
        return "90-Day LeanFlow Protocol ({$subType})";
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
        $replace('{{CONDITION_LABEL}}', 'LeanFlow — Metabolic Protocol');
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

        // Movement plan (core module for weight)
        $movementPlan = $get('movement_plan') ?: $get('movement') ?: $get('exercise_plan');
        $replace('{{MOVEMENT_PLAN}}', $this->renderMovementProtocol($movementPlan ?: []));

        $replace('{{SUPPLEMENTS}}', $this->renderSupplements($get('supplements')));
        $replace('{{HERBAL_PROTOCOLS}}', $this->renderHerbalProtocols($get('herbal_protocols')));
        $replace('{{LIFESTYLE_TIPS}}', $this->renderLifestyleTips($get('lifestyle_tips')));
        $replace('{{TRACKING_GUIDANCE}}', $this->renderTrackingGuidance($get('tracking_guidance')));
        $replace('{{QUICK_WIN}}', $this->renderQuickWin($get('quick_win')));
        $replace('{{SOURCING_GUIDE}}', $this->renderSourcingGuide($get('sourcing_guide'), $regionProfile));
        $replace('{{PLATEAU_PROTOCOL}}', $this->renderPlateauProtocol($get('plateau_protocol')));
        $replace('{{ENCOURAGEMENT}}', $this->renderEncouragement($get('encouragement')));

        return $html;
    }

    private function renderPlateauProtocol($protocol): string
    {
        if (!is_array($protocol) && !is_string($protocol)) return '';
        if (is_string($protocol)) return '<p>' . $this->esc($protocol) . '</p>';
        $html = '<div class="plateau-protocol">';
        foreach ($protocol as $step) {
            $html .= '<div class="plateau-step">'
                . '<div class="plateau-scenario">' . $this->esc($step['scenario'] ?? '') . '</div>'
                . '<div class="plateau-action">' . $this->esc($step['action'] ?? '') . '</div>'
                . '</div>';
        }
        return $html . '</div>';
    }

    protected function getFallbackContent(string $subType, string $name): array
    {
        $trackingGuidance = [
            ['key' => 'weight',      'label' => 'Weight (daily, 7-day avg)',  'type' => 'number',  'unit' => 'kg', 'frequency' => 'daily',  'how' => 'Same morning conditions: after waking, before eating, minimal clothing',    'why' => '7-day average smooths daily fluctuations — track the TREND not the daily number', 'chart' => 'trend'],
            ['key' => 'waist_cm',    'label' => 'Waist Measurement',          'type' => 'number',  'unit' => 'cm', 'frequency' => 'weekly', 'how' => 'Midpoint between bottom rib and hip bone, after exhaling normally',          'why' => 'Waist shrinks even when scale stalls — non-scale progress indicator', 'chart' => 'trend'],
            ['key' => 'energy',      'label' => 'Energy (1–10)',              'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate energy at 8 AM and 3 PM',                                                   'why' => 'Energy improvements confirm metabolic healing', 'chart' => 'trend'],
            ['key' => 'habit_check', 'label' => 'Habit Checklist',           'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Did you follow the meal plan? Did you complete movement? Sleep before 10:30?',  'why' => 'Consistency drives results — track adherence not perfection', 'chart' => 'streak'],
            ['key' => 'nsv',         'label' => 'Non-Scale Victory',          'type' => 'select',  'frequency' => 'weekly', 'how' => 'What non-scale win did you notice this week?',                                    'why' => 'Motivation maintenance — scale noise causes churn; NSVs sustain the journey', 'chart' => 'none'],
        ];

        $typeDescriptions = [
            'Insulin-Resistant/Metabolic' => [
                'summary'    => "$name, your weight pattern — belly fat, energy crashes, sugar cravings, and fatigue after meals — points clearly to insulin resistance as the metabolic driver. Your cells have become less responsive to insulin, causing your body to store fat (especially viscerally) and making weight loss feel impossible despite effort. The LeanFlow protocol targets insulin sensitivity directly — and when that improves, everything else follows.",
                'root_cause' => "Insulin resistance means your cells have stopped responding effectively to insulin's 'store glucose' signal. Your pancreas compensates by producing more insulin — and high insulin is the primary driver of fat storage, particularly around the abdomen.\n\nThis creates a frustrating cycle: high insulin makes fat cells reluctant to release stored fat for energy, so you feel fatigued and reach for sugar/carbs for quick energy — which spikes insulin further.\n\nThe LeanFlow approach breaks this cycle with protein-first meals that blunt insulin spikes, strategic movement that forces cells to use glucose without insulin, and specific supplements that restore insulin sensitivity at the cellular level.",
                'goals'      => ['Restore cellular insulin sensitivity within 30 days', 'Reduce visceral (belly) fat as primary metric', 'Eliminate energy crashes through blood-sugar stability', 'Build progressive movement habit without burnout', 'Achieve sustainable weekly loss of 0.5–1 kg'],
            ],
            'Stress/Cortisol' => [
                'summary'    => "$name, your weight pattern — stress eating, belly retention despite eating well, poor sleep, and emotional triggers — indicates cortisol-driven weight gain. Chronic stress keeps cortisol elevated, which promotes abdominal fat storage and drives carbohydrate cravings. Aggressive dieting makes this worse. The LeanFlow protocol prioritises cortisol management first.",
                'root_cause' => "Chronic cortisol elevation sends your body a clear signal: 'store fat around the middle for emergency fuel.' This is an evolutionary survival mechanism that becomes maladaptive in modern chronic stress.\n\nHigh cortisol also directly increases appetite (especially for high-calorie foods), promotes muscle breakdown, disrupts sleep, and raises blood sugar — all of which make weight loss feel impossible. Aggressive calorie restriction makes cortisol worse.\n\nThe LeanFlow stress protocol addresses this directly: cortisol-lowering practices first, moderate deficit second.",
                'goals'      => ['Lower chronic cortisol through targeted daily practices', 'Improve sleep quality (poor sleep raises cortisol 40%)', 'Build emotional eating awareness and alternatives', 'Establish gentle consistent movement (not intense exercise)', 'Lose 0.5–0.75 kg per week sustainably'],
            ],
            'Hormonal (Thyroid/Perimenopause)' => [
                'summary'    => "$name, your weight pattern — unexplained weight gain, fatigue, cold intolerance, and resistance to typical diet approaches — suggests a hormonal metabolic driver (thyroid or perimenopause). Please request thyroid labs (TSH, Free T4, Free T3) if not already done. The LeanFlow protocol supports thyroid function and hormonal transition while building sustainable habits.",
                'root_cause' => "Thyroid hormones regulate your metabolic rate — when they're low, your body burns significantly fewer calories at rest. This is why standard calorie approaches fail: your metabolic baseline has changed.\n\nPerimenopause (declining oestrogen) causes similar metabolic slowdown, plus redistribution of fat to the abdomen. Both conditions require a different nutrition and movement approach than simple calorie restriction.\n\nIMPORTANT: Please get thyroid labs before starting intense protocols. The LeanFlow plan supports thyroid function with selenium, iodine, and thyroid-supportive foods, and recommends moderate movement that doesn't stress the system.",
                'goals'      => ['Support thyroid/hormonal function through nutrition', 'Get thyroid labs: TSH, Free T4, Free T3 (referral if not done)', 'Build moderate consistent movement appropriate for current energy', 'Reduce abdominal fat through hormonal support', 'Set realistic expectation: 0.25–0.5 kg/week with hormonal weight'],
            ],
            'Habit/Lifestyle' => [
                'summary'    => "$name, your weight pattern reflects lifestyle and habit factors — portion sizes, movement levels, and eating patterns — rather than a clinical metabolic condition. The great news: habit-driven weight is the most straightforwardly addressable. The LeanFlow protocol builds sustainable behaviours one habit at a time.",
                'root_cause' => "Weight gain driven by lifestyle factors is often a combination of energy intake slightly exceeding output over months or years — with habits, environment, and behaviour patterns making it difficult to shift.\n\nThe most common drivers: large portion sizes (often without awareness), limited NEAT (non-exercise activity throughout the day), frequent processed food consumption, and inconsistent meal timing that allows hunger to drive poor choices.\n\nThe LeanFlow approach uses habit architecture — small sustainable changes compounded over 90 days — rather than dramatic restriction that fails.",
                'goals'      => ['Build awareness of portion sizes and eating patterns', 'Increase NEAT (Non-Exercise Activity Thermogenesis) daily', 'Establish consistent meal timing to reduce hunger-driven choices', 'Build progressive movement to 150+ minutes per week', 'Lose 0.5–1 kg per week through sustainable behaviours'],
            ],
        ];

        $typeContent = $typeDescriptions[$subType] ?? $typeDescriptions['Insulin-Resistant/Metabolic'];

        return array_merge($typeContent, [
            'phase_1_title'          => 'Foundation & Metabolic Reset',
            'phase_1_focus'          => 'Building the metabolic base',
            'phase_1_description'    => 'The first 30 days establish your metabolic foundation. You\'ll remove the biggest dietary blockers, establish consistent meal timing, begin your movement habit at a manageable level, and start supplements targeting your weight type. Expect the most dramatic early improvements in energy and cravings.',
            'phase_1_weekly_actions' => [
                ['week' => 1, 'focus' => 'Baseline & Foundations', 'actions' => ['Take baseline measurements (weight, waist, hips — photos optional)', 'Remove sugar-sweetened drinks and replace with water/herbal tea', 'Eat breakfast within 1 hour of waking (protein-first)', 'Begin daily 20-minute walk after largest meal'], 'milestone' => 'Baseline documented; first movement habit started'],
                ['week' => 2, 'focus' => 'Nutrition Structure',    'actions' => ['Introduce protein-first meal structure at every meal', 'Start supplement stack', 'Meal prep on Sundays to prevent midweek decision fatigue', 'Add herbal metabolic tea morning ritual'], 'milestone' => 'Consistent meal structure; less hunger between meals'],
                ['week' => 3, 'focus' => 'Movement Building',      'actions' => ['Increase daily walk to 30 minutes + add 2 strength sessions per week', 'Track daily energy and cravings', 'Identify and address emotional eating triggers', 'Assess progress: energy, cravings, sleep quality'], 'milestone' => 'Movement is daily habit; energy noticeably better'],
                ['week' => 4, 'focus' => 'Consolidation',          'actions' => ['Full protocol active: supplements + nutrition + movement', 'First weight measurement (don\'t obsess daily — use 7-day average)', 'Review biggest wins and challenges', 'Prepare for Phase 2 intensity increase'], 'milestone' => 'Foundational habits locked in; first measurable progress'],
            ],
            'phase_2_title'          => 'Momentum & Optimisation',
            'phase_2_focus'          => 'Accelerating metabolic change',
            'phase_2_description'    => 'With your metabolism starting to respond, Phase 2 builds on the foundation with progressive movement increases, refined nutrition, and targeted interventions for your specific weight type.',
            'phase_2_weekly_actions' => [
                ['week' => 5, 'focus' => 'Progressive Overload', 'actions' => ['Increase movement: 3 strength sessions + daily walking target', 'Refine macro ratios for your type (protein increases to 30% of calories)', 'Introduce intermittent eating window if cortisol type allows', 'Track non-scale victories this week'], 'milestone' => 'Physical strength noticeably increasing'],
                ['week' => 6, 'focus' => 'Fine-Tuning',          'actions' => ['Adjust based on 6-week data: what\'s working?', 'Introduce new recipes from localised meal plan', 'Add metabolism-boosting herbal protocol', 'Sleep optimisation deep dive'], 'milestone' => 'Visible body composition changes'],
                ['week' => 7, 'focus' => 'Sustainability Test',  'actions' => ['Eat out twice this week — using protocol principles', 'Travel or holiday scenario planning', 'Practice plateau response (if scale stalls)', 'Add variety to movement routine'], 'milestone' => 'Protocol works in real-life conditions'],
                ['week' => 8, 'focus' => 'Peak Month 2',         'actions' => ['8-week progress photos and measurements', 'Compare to baseline — celebrate ALL progress', 'Address any remaining sticking points', 'Plan Phase 3 goals'], 'milestone' => 'Maximum 2-month progress; ready for Phase 3'],
            ],
            'phase_3_title'          => 'Sustainability & Body Composition',
            'phase_3_focus'          => 'Making it permanent',
            'phase_3_description'    => 'The final phase transitions you from active weight-loss protocol to sustainable lifestyle maintenance. The goal is a protocol you can maintain permanently — not a 90-day sprint followed by regression.',
            'phase_3_weekly_actions' => [
                ['week' => 9,  'focus' => 'Maintenance Mode',    'actions' => ['Increase calories slightly (reverse diet) if approaching goal', 'Shift focus from scale to body composition and strength', 'Add 1 new food or meal per week', 'Establish long-term movement schedule'], 'milestone' => 'Sustainable maintenance established'],
                ['week' => 10, 'focus' => 'Lifestyle Integration', 'actions' => ['Host a social event using protocol-friendly menu', 'Identify your minimum effective protocol', 'Set body composition goals beyond the scale', 'Begin mentoring a friend or family member'], 'milestone' => 'Protocol feels like natural lifestyle'],
                ['week' => 11, 'focus' => 'Plateau-Proof',       'actions' => ['Create your personal plateau-response playbook', 'Seasonal eating adjustments', 'Long-term supplement strategy', 'Medical check-up planning (blood sugar, lipids)'], 'milestone' => 'Fully prepared for long-term maintenance'],
                ['week' => 12, 'focus' => 'Transformation',      'actions' => ['90-day full progress review: photos, measurements, energy, mood', 'Calculate total progress (weight, waist, NSVs)', 'Write your personal success story', 'Set 6-month and 1-year goals'], 'milestone' => 'Transformed body composition and metabolic health'],
            ],
            'morning_routine' => [
                ['time' => '6:30 AM', 'action' => 'Drink 500ml water immediately — before anything else', 'why' => 'Reduces hunger, supports metabolism, and prevents mistaking thirst for hunger'],
                ['time' => '7:00 AM', 'action' => 'Protein-first breakfast (minimum 25g protein)', 'why' => 'Protein for breakfast reduces total daily calorie intake by 15–20% through satiety hormones'],
                ['time' => '7:15 AM', 'action' => 'Morning metabolic supplement stack', 'why' => 'Berberine and chromium are most effective with the first meal of the day'],
                ['time' => '7:30 AM', 'action' => 'Morning metabolic tea (green tea or herbal blend)', 'why' => 'Green tea catechins increase fat oxidation by 17% in clinical studies'],
            ],
            'afternoon_routine' => [
                ['time' => '12:30 PM', 'action' => 'Lunch: protein + fibre + healthy fat (no refined carbs)', 'why' => 'Prevents post-lunch energy crash and afternoon hunger that drives snacking'],
                ['time' => '1:00 PM',  'action' => '10-minute walk immediately after lunch', 'why' => 'Post-meal walking reduces blood glucose spike by 30%'],
                ['time' => '3:00 PM',  'action' => 'Protein-rich snack if hungry (Greek yoghurt / nuts / boiled egg)', 'why' => 'Prevents over-eating at dinner — the single most common overeating trigger'],
            ],
            'evening_routine' => [
                ['time' => '6:30 PM', 'action' => 'Dinner: protein + abundant vegetables + minimal starch', 'why' => 'Evening meals high in vegetables and protein support overnight fat burning'],
                ['time' => '7:00 PM', 'action' => 'Evening walk or strength session (if not done earlier)', 'why' => 'Evening movement improves insulin sensitivity the following morning'],
                ['time' => '8:30 PM', 'action' => 'Screens off — kitchen closed', 'why' => 'Late-night eating accounts for 20% of excess calorie intake on average'],
                ['time' => '9:30 PM', 'action' => 'Journaling or relaxation practice, then sleep', 'why' => 'Poor sleep raises ghrelin (hunger hormone) by 25% the following day'],
            ],
            'meal_plan' => [
                ['day' => 1, 'breakfast' => ['meal' => 'Boiled Eggs with Sweet Potato and Avocado', 'description' => 'Two boiled eggs with 150g roasted sweet potato and half avocado', 'benefit' => 'Balanced macros, slow-release energy, healthy fats prevent cravings'], 'lunch' => ['meal' => 'Grilled Chicken with Brown Rice and Vegetables', 'description' => 'Seasoned chicken breast, 100g brown rice, mixed stir-fried vegetables', 'benefit' => 'Protein-first, complex carbs, high fibre — ideal blood-sugar profile'], 'dinner' => ['meal' => 'Pepper Soup with Lean Meat', 'description' => 'Light broth with goat meat or chicken, traditional spices, no starch', 'benefit' => 'Low calorie, high protein, thermogenic spices'], 'snack' => ['meal' => 'Mixed Nuts (30g)', 'description' => 'Unsalted mixed nuts — almonds, walnuts, cashews', 'benefit' => 'Healthy fat and protein combination that reduces hunger until dinner']],
                ['day' => 2, 'breakfast' => ['meal' => 'Protein Smoothie', 'description' => 'Plant or whey protein, banana, spinach, almond milk, chia seeds', 'benefit' => 'Convenient protein-first breakfast with omega-3 from chia'], 'lunch' => ['meal' => 'Bean Porridge with Plantain (reduced)', 'description' => 'Beans with small portion green (unripe) plantain and leafy greens', 'benefit' => 'Plant protein, resistant starch in green plantain — excellent for insulin'], 'dinner' => ['meal' => 'Baked Fish with Steamed Vegetables', 'description' => 'Baked tilapia or mackerel with broccoli, carrots, and spinach', 'benefit' => 'Omega-3 from fish reduces inflammation; high protein, low calorie'], 'snack' => ['meal' => 'Greek Yoghurt (plain, full-fat)', 'description' => '150g plain Greek yoghurt with 1 teaspoon of cinnamon', 'benefit' => 'Protein + probiotics; cinnamon improves insulin sensitivity']],
                ['day' => 3, 'breakfast' => ['meal' => 'Oats with Protein and Seeds', 'description' => 'Rolled oats with protein powder, flaxseeds, and berries', 'benefit' => 'Beta-glucan fibre in oats reduces post-meal blood glucose by 40%'], 'lunch' => ['meal' => 'Moi Moi with Green Salad', 'description' => 'Steamed bean pudding with fresh salad and olive oil dressing', 'benefit' => 'High protein, high fibre, low glycaemic index'], 'dinner' => ['meal' => 'Chicken and Vegetable Stir-Fry', 'description' => 'Diced chicken with mixed vegetables in tomato sauce, no added oil', 'benefit' => 'Low calorie density, high volume — satisfying without excess calories'], 'snack' => ['meal' => 'Boiled Egg and Cucumber', 'description' => 'One boiled egg with sliced cucumber and lemon', 'benefit' => 'High satiety, low calorie — ideal afternoon snack']],
                ['day' => 4, 'breakfast' => ['meal' => 'Plantain Omelette (unripe plantain)', 'description' => 'Diced unripe plantain mixed with eggs and peppers', 'benefit' => 'Resistant starch + protein — low glycaemic index breakfast'], 'lunch' => ['meal' => 'Ogbono Soup with Fish (no eba)', 'description' => 'Light ogbono soup with fish and leafy greens, no fufu or eba', 'benefit' => 'High protein, fibre from vegetables, low starch'], 'dinner' => ['meal' => 'Turkey Stew with Cauliflower Rice', 'description' => 'Tomato-based turkey stew with grated cauliflower instead of rice', 'benefit' => 'All the satisfaction with 75% fewer calories than rice portion'], 'snack' => ['meal' => 'Apple with Almond Butter', 'description' => 'Medium apple with 1 tablespoon almond butter', 'benefit' => 'Blood-sugar stable snack with fibre and fat']],
                ['day' => 5, 'breakfast' => ['meal' => 'Akara with Vegetable Sauce (no pap)', 'description' => 'Bean cakes with tomato and pepper sauce, no sweetened pap', 'benefit' => 'High plant protein with minimal refined carbs'], 'lunch' => ['meal' => 'Jollof Rice (small portion) with Double Protein', 'description' => '100g jollof rice with extra grilled chicken or turkey', 'benefit' => 'When eating rice, protein load compensates for glycaemic impact'], 'dinner' => ['meal' => 'Egusi Soup with Pomo (reduced palm oil)', 'description' => 'Egusi with pomo and leafy greens, light oil', 'benefit' => 'Protein from egusi and pomo; leafy greens add fibre'], 'snack' => ['meal' => 'Watermelon (200g)', 'description' => 'Fresh watermelon — not juice', 'benefit' => '92% water, low calorie, high volume — excellent hunger management']],
                ['day' => 6, 'breakfast' => ['meal' => 'Smoothie Bowl', 'description' => 'Blended banana, spinach, flaxseed with nuts and seeds as toppings', 'benefit' => 'Nutrient-dense, omega-3 rich, satisfying with good fats on top'], 'lunch' => ['meal' => 'Vegetable Yam Porridge (moderate portion)', 'description' => 'Yam porridge loaded with ugwu, spinach, and crayfish — small yam portion', 'benefit' => 'Yam has lower GI than cassava/fufu; vegetables add volume'], 'dinner' => ['meal' => 'Grilled Whole Fish with Garden Salad', 'description' => 'Grilled tilapia with fresh salad, olive oil, and lemon', 'benefit' => 'Low calorie, high protein — ideal deficit dinner'], 'snack' => ['meal' => 'Tiger Nuts', 'description' => 'Small handful of tiger nuts', 'benefit' => 'Prebiotic fibre, healthy fats, blood-sugar friendly']],
                ['day' => 7, 'breakfast' => ['meal' => 'Nigerian Omelette (no bread)', 'description' => 'Three-egg omelette with tomatoes, peppers, onions, and mackerel inside', 'benefit' => 'High protein breakfast that prevents midmorning hunger for 4–5 hours'], 'lunch' => ['meal' => 'Coconut Chicken with Quinoa', 'description' => 'Chicken in light coconut sauce with cooked quinoa and steamed greens', 'benefit' => 'Complete protein in quinoa; coconut medium-chain fats support metabolism'], 'dinner' => ['meal' => 'Efo Riro (reduced oil) with Boiled Eggs', 'description' => 'Spinach stew with 2 boiled eggs, minimal palm oil', 'benefit' => 'Iron-rich, high protein, low calorie density'], 'snack' => ['meal' => 'Herbal Tea with Cinnamon', 'description' => 'Rooibos or green tea with a cinnamon stick', 'benefit' => 'Zero calorie, cinnamon improves fasting insulin sensitivity']],
            ],
            'movement_plan' => [
                'overview' => 'LeanFlow uses progressive movement: starting where you are, building week by week. Never jump to intensity your body cannot sustain. Consistency beats intensity every time.',
                'weeks' => [
                    ['week' => '1–2',  'focus' => 'Foundation Movement', 'sessions' => '5×/week: 20–30 min brisk walking', 'progression' => 'Get consistent — same time each day'],
                    ['week' => '3–4',  'focus' => 'Adding Resistance',   'sessions' => '3×/week: 30 min strength + 2× walking', 'progression' => 'Introduce bodyweight exercises'],
                    ['week' => '5–8',  'focus' => 'Building Strength',   'sessions' => '3× strength + 2× cardio + daily 10k steps', 'progression' => 'Add resistance bands or weights'],
                    ['week' => '9–12', 'focus' => 'Peak Performance',    'sessions' => '4× strength + 2× cardio + 10k daily steps', 'progression' => 'Increase resistance, improve form'],
                ],
            ],
            'supplements' => [
                ['name' => 'Berberine',          'dosage' => '500mg, 3× daily with meals (total 1500mg max)', 'timing' => 'With each meal', 'benefit' => 'Activates AMPK (the same pathway as Metformin) — improves insulin sensitivity as effectively as some medications', 'note' => 'Do NOT exceed 1500mg/day. Consult doctor if on Metformin — combined effect may be too strong'],
                ['name' => 'Chromium Picolinate', 'dosage' => '200–400mcg daily', 'timing' => 'With largest meal of day', 'benefit' => 'Enhances insulin signalling — reduces sugar cravings significantly within 2–4 weeks', 'note' => 'Safe and well-tolerated; avoid if kidney disease'],
                ['name' => 'Magnesium Glycinate', 'dosage' => '300–400mg daily', 'timing' => 'Evening before bed', 'benefit' => 'Improves insulin sensitivity, supports sleep (poor sleep = 25% more hunger hormones), reduces cortisol', 'note' => 'Start with 200mg if new to magnesium'],
                ['name' => 'Omega-3 Fish Oil',   'dosage' => '2000mg EPA+DHA daily', 'timing' => 'With meals', 'benefit' => 'Reduces inflammation that blocks fat burning; improves insulin receptor sensitivity', 'note' => 'Choose quality brand tested for heavy metals'],
                ['name' => 'Vitamin D3',          'dosage' => '2000–4000 IU daily', 'timing' => 'With a fatty meal', 'benefit' => 'Deficiency is linked to increased fat storage and insulin resistance in 77% of overweight individuals', 'note' => 'Test levels if possible — optimal range 40–60 ng/mL'],
            ],
            'herbal_protocols' => [
                ['herb' => 'Fenugreek Seeds', 'local_name' => 'Hulba (Arabic) / Methi (widely available)', 'preparation' => 'Soak 1 tablespoon overnight; drink the water and chew seeds in morning', 'dosage' => '1 tablespoon soaked seeds daily', 'benefit' => 'Reduces post-meal blood sugar spikes by 50% in clinical studies; reduces appetite', 'caution' => 'Can cause maple-syrup body odour (harmless); reduce dose if GI discomfort'],
                ['herb' => 'Moringa',         'local_name' => 'Drumstick tree / Moringa oleifera', 'preparation' => '1 teaspoon dried powder in water, smoothie, or sprinkled on food', 'dosage' => '1–2 teaspoons daily', 'benefit' => 'Stabilises blood sugar, reduces inflammation, packed with metabolism-supporting minerals', 'caution' => 'Avoid high doses in pregnancy; start with 1/2 teaspoon'],
                ['herb' => 'Bitter Leaf',     'local_name' => 'Ewuro (Yoruba) / Onugbu (Igbo)', 'preparation' => 'Squeeze and drink juice (diluted) or brew as tea', 'dosage' => '1 cup juice or tea, 3× per week', 'benefit' => 'Traditional blood-sugar regulator; supports liver detoxification', 'caution' => 'Very bitter — dilute with water or mix with mild herbs'],
                ['herb' => 'Cinnamon',        'local_name' => 'Oloorun (Yoruba) / widely available', 'preparation' => 'Add 1 teaspoon to morning oats, tea, or smoothie', 'dosage' => '1–2 teaspoons per day (Ceylon cinnamon preferred)', 'benefit' => 'Mimics insulin action, improves glucose uptake by cells', 'caution' => 'Use Ceylon (true) cinnamon, not Cassia, for daily supplementation'],
            ],
            'lifestyle_tips' => [
                ['category' => 'Scale Strategy',   'tip' => 'Weigh daily but track the 7-day average', 'detail' => 'Daily weight fluctuates 1–3 kg from water, food weight, and hormones. One bad number causes people to quit. Track the 7-day average — the TREND is what matters.'],
                ['category' => 'Protein First',     'tip' => 'Eat protein before carbs at every meal', 'detail' => 'This single habit reduces post-meal blood glucose by 29% and keeps you full 2 hours longer. Chicken before rice. Fish before yam. Always.'],
                ['category' => 'NEAT',              'tip' => 'Add steps, not just exercise sessions', 'detail' => 'Non-exercise activity (walking to shops, taking stairs, standing at desk) can burn 300–500 extra kcal/day. This often matters more than gym sessions.'],
                ['category' => 'Sleep',             'tip' => 'Sleep 7–9 hours: insufficient sleep makes weight loss 55% harder', 'detail' => 'Ghrelin (hunger hormone) rises 25% after one night of poor sleep. Weight loss protocols fail primarily due to sleep deprivation, not diet mistakes.'],
                ['category' => 'Meals Out',         'tip' => 'Protein-first ordering: ask for extra protein, less starch', 'detail' => 'Restaurant meals don\'t have to derail you. Ask for grilled protein instead of fried, vegetables instead of extra starch, sauces on the side.'],
                ['category' => 'Stress',            'tip' => 'Cortisol management is weight management', 'detail' => 'High cortisol promotes fat storage and cravings regardless of calorie intake. Five minutes of daily breathwork reduces cortisol measurably.'],
                ['category' => 'Hydration',         'tip' => 'Drink 500ml water before each main meal', 'detail' => 'Pre-meal water reduces food intake by 13% in clinical studies. Also, thirst is frequently mistaken for hunger.'],
            ],
            'tracking_guidance' => $trackingGuidance,
            'plateau_protocol' => [
                ['scenario' => 'Scale hasn\'t moved in 2 weeks', 'action' => 'First: Are your measurements and energy improving? Scale plateau ≠ progress plateau. Second: Reduce calorie-dense condiments for 1 week. Third: Add 2000 steps/day.'],
                ['scenario' => 'Extreme hunger and cravings', 'action' => 'Increase protein at every meal by 25%. Add a protein-rich snack mid-afternoon. Evaluate sleep quality — hunger hormones rise dramatically with poor sleep.'],
                ['scenario' => 'Exercise plateau (not seeing results from workouts)', 'action' => 'Change stimulus: if doing walks, add resistance. If doing bodyweight, add bands/weights. Progressive overload is the key.'],
                ['scenario' => 'Social event / holiday derail', 'action' => 'Not a crisis. Return to protocol the next meal — not the next Monday. One day off does not require a week of compensation.'],
            ],
            'quick_win' => [
                'title'  => 'Drink 500ml of water RIGHT NOW — before you read another page',
                'detail' => 'Most people start a protocol and feel hungry within 2 hours. Frequently, that\'s dehydration, not hunger. Drinking water before every meal and upon waking is the single highest-return habit in weight management. Start now.',
            ],
            'encouragement' => "$name, let me be clear about something: the problem has never been your willpower or your discipline.\n\nThe problem has been that your body was working against you — high insulin, elevated cortisol, or hormonal disruption making fat storage the path of least resistance.\n\nThe LeanFlow protocol doesn't fight your body. It works with your biology to create the conditions where your body WANTS to release stored fat and build metabolic health.\n\nAt Day 90, you won't just be lighter. You'll understand your body in a way that makes weight management make sense for the first time. That knowledge is permanent — unlike the results from approaches that don't address the root cause.\n\nYou've already done the hardest thing: starting. Now let's build.",
        ]);
    }
}
