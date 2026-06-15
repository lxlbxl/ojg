<?php
/**
 * AcneProtocolGenerator (GlowClear) — 90-Day Acne Protocol
 *
 * KEY RULES enforced by this generator:
 * - NO workout/movement module (omitted from manifest and stripped by enforceManifest)
 * - AM/PM topical skincare routine IS a core module
 * - Skin photo + clarity score logging
 * - Hormonal-acne: optional cycle-sync only if female + hormonal type
 */

require_once __DIR__ . '/AbstractProtocolGenerator.php';

class AcneProtocolGenerator extends AbstractProtocolGenerator
{
    private array $typeMap = [
        'hormonal'    => 'Hormonal',
        'inflammatory'=> 'Inflammatory',
        'comedonal'   => 'Comedonal',
        'fungal'      => 'Fungal (Folliculitis)',
        'folliculitis'=> 'Fungal (Folliculitis)',
        'stress'      => 'Stress/Cortisol',
        'cortisol'    => 'Stress/Cortisol',
        'general'     => 'Hormonal',
    ];

    public function getCondition(): string { return 'acne'; }

    public function getModuleManifest(): array
    {
        // NO MODULE_MOVEMENT — this is the key gate
        return [
            self::MODULE_MEAL_PLAN,
            self::MODULE_SKINCARE_ROUTINE,
            self::MODULE_HERBAL_PROTOCOL,
            self::MODULE_SUPPLEMENTS,
            self::MODULE_SLEEP_STRESS,
            self::MODULE_TRACKING,
            self::MODULE_PHASE_ARC,
            // MODULE_CYCLE_SYNC is conditionally allowed (hormonal + female) — handled in validation
        ];
    }

    protected function getPromptsDir(): string
    {
        return $this->promptsDir . '/acne';
    }

    protected function resolveSubType(array $assessment): string
    {
        $code = $assessment['acneType'] ?? $assessment['type'] ?? $assessment['subType'] ?? 'general';
        return $this->typeMap[strtolower((string) $code)] ?? 'Hormonal';
    }

    protected function buildUserPromptVars(array $assessment, string $name, array $regionProfile): array
    {
        $subType   = $this->resolveSubType($assessment);
        $isFemale  = strtolower($assessment['sex'] ?? $assessment['gender'] ?? 'female') !== 'male';
        $isHormonal = str_contains(strtolower($subType), 'hormonal');

        return [
            'NAME'                 => $name ?: 'Friend',
            'ACNE_TYPE'            => $subType,
            'AGE'                  => $assessment['age'] ?? 'Not specified',
            'SEX'                  => $isFemale ? 'female' : 'male',
            'SKIN_TYPE'            => $assessment['skinType'] ?? 'Not specified',
            'SYMPTOMS'             => is_array($assessment['symptoms'] ?? null)
                ? implode(', ', $assessment['symptoms']) : ($assessment['symptoms'] ?? 'Acne breakouts'),
            'TRIGGER_HISTORY'      => is_array($assessment['triggers'] ?? null)
                ? implode(', ', $assessment['triggers']) : ($assessment['triggers'] ?? 'Not specified'),
            'DIETARY_RESTRICTIONS' => is_array($assessment['dietaryRestrictions'] ?? null)
                ? implode(', ', $assessment['dietaryRestrictions']) : 'None',
            'MEDICATIONS'          => is_array($assessment['medications'] ?? null)
                ? implode(', ', $assessment['medications']) : 'None',
            'STRESS_LEVEL'         => $assessment['stressLevel'] ?? 'Not specified',
            'SLEEP_QUALITY'        => $assessment['sleepQuality'] ?? 'Not specified',
            'CYCLE_STATUS'         => ($isFemale && $isHormonal) ? ($assessment['cycleStatus'] ?? 'Not specified') : 'N/A',
            'ENABLE_CYCLE_MODULE'  => ($isFemale && $isHormonal) ? 'yes' : 'no',
            'GOALS'                => is_array($assessment['goals'] ?? null)
                ? implode(', ', $assessment['goals']) : ($assessment['goals'] ?? 'Clear skin and prevent breakouts'),
            'COUNTRY'              => $regionProfile['country'] ?? 'Nigeria',
            'MEASUREMENT_SYSTEM'   => $regionProfile['measurement_system'] ?? 'metric',
            'REGION_PROFILE'       => '{}',
        ];
    }

    protected function validateConditionContent(array $content): array
    {
        $errors = [];

        // MUST have skincare routine
        if (empty($content['skincare_routine']) && empty($content['am_pm_routine'])) {
            $errors[] = 'Acne: missing skincare_routine (AM/PM)';
        }

        // MUST NOT have workout block
        if (!empty($content['workout']) || !empty($content['movement']) || !empty($content['exercise_plan'])) {
            $errors[] = 'Acne: forbidden workout/movement block present';
        }

        // MUST have meal plan
        if (empty($content['meal_plan']) || !is_array($content['meal_plan'])) {
            $errors[] = 'Acne: missing meal_plan';
        }

        // MUST have photo protocol or tracking guidance with skin_photo key
        $hasPhotoTracking = false;
        if (!empty($content['tracking_guidance']) && is_array($content['tracking_guidance'])) {
            foreach ($content['tracking_guidance'] as $t) {
                if (($t['key'] ?? '') === 'skin_photo' || str_contains(strtolower($t['what'] ?? ''), 'photo')) {
                    $hasPhotoTracking = true;
                    break;
                }
            }
        }
        if (!$hasPhotoTracking) {
            $errors[] = 'Acne: tracking_guidance must include skin photo logging';
        }

        return $errors;
    }

    protected function getPlanLabel(string $subType): string
    {
        return "90-Day GlowClear Acne Protocol ({$subType})";
    }

    protected function renderTemplate(callable $get, string $name, string $subType, array $assessment, array $regionProfile): string
    {
        // Fall back to the shared plan template; acne-specific sections inserted at placeholders
        $templatePath = $this->templatesDir . '/plan-template.html';
        $html = @file_get_contents($templatePath);
        if (!$html) throw new Exception('Could not load template: ' . $templatePath);

        $replace = function ($ph, $val) use (&$html) {
            $html = str_replace($ph, $val ?? '', $html);
        };

        $replace('{{NAME}}', $this->esc($name ?: 'Friend'));
        $replace('{{PCOS_TYPE}}', $this->esc($subType));
        $replace('{{CONDITION_LABEL}}', 'GlowClear — Acne Protocol');
        $replace('{{AGE}}', $this->esc((string) ($assessment['age'] ?? 'N/A')));
        $replace('{{DATE}}', date('j F Y'));
        $replace('{{YEAR}}', date('Y'));
        $replace('{{COUNTRY}}', $this->esc($regionProfile['country'] ?? 'Nigeria'));

        $replace('{{SUMMARY}}', $this->esc($get('summary')));
        $replace('{{ROOT_CAUSE}}', $this->esc($get('root_cause')));
        $replace('{{GOALS}}', $this->renderGoals($get('goals')));

        // Phases
        for ($i = 1; $i <= 3; $i++) {
            $replace("{{PHASE_{$i}_TITLE}}", $this->esc($get("phase_{$i}_title")));
            $replace("{{PHASE_{$i}_FOCUS}}", $this->esc($get("phase_{$i}_focus")));
            $replace("{{PHASE_{$i}_DESCRIPTION}}", $this->esc($get("phase_{$i}_description")));
            $replace("{{PHASE_{$i}_WEEKS}}", $this->renderWeeklyActions($get("phase_{$i}_weekly_actions")));
        }

        // Routines: morning = skincare AM + daily ritual; no workout
        $skincareRoutine = $get('skincare_routine') ?: $get('am_pm_routine');
        $replace('{{MORNING_ROUTINE}}', $this->renderSkincareRoutine($skincareRoutine) . $this->renderRoutine($get('morning_routine')));
        $replace('{{AFTERNOON_ROUTINE}}', $this->renderRoutine($get('afternoon_routine')));
        $replace('{{EVENING_ROUTINE}}', $this->renderRoutine($get('evening_routine')));

        // Meal plan — no workout
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

        // Acne-specific: flare playbook section (inserted if placeholder exists)
        $replace('{{FLARE_PLAYBOOK}}', $this->renderFlarePlaybook($get('flare_playbook')));

        return $html;
    }

    private function renderFlarePlaybook($playbook): string
    {
        if (!is_array($playbook) && !is_string($playbook)) return '';
        if (is_string($playbook)) return '<p>' . $this->esc($playbook) . '</p>';
        $html = '<div class="flare-playbook">';
        foreach ($playbook as $step) {
            $html .= '<div class="flare-step">'
                . '<div class="flare-trigger">' . $this->esc($step['trigger'] ?? '') . '</div>'
                . '<div class="flare-response">' . $this->esc($step['response'] ?? '') . '</div>'
                . '</div>';
        }
        return $html . '</div>';
    }

    protected function getFallbackContent(string $subType, string $name): array
    {
        $trackingGuidance = [
            ['key' => 'skin_photo',      'label' => 'Skin Photo',           'type' => 'photo',   'frequency' => 'weekly', 'how' => 'Same lighting, angle, and time of day. Baseline on Day 1.', 'why' => 'Objective clarity tracking over time', 'chart' => 'none'],
            ['key' => 'skin_clarity',    'label' => 'Skin Clarity (1–10)',  'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate overall skin clarity each morning',                    'why' => 'Tracks week-to-week trend', 'chart' => 'trend'],
            ['key' => 'flare_count',     'label' => 'New Spots Today',      'type' => 'number',  'frequency' => 'daily',  'how' => 'Count new spots that appeared',                             'why' => 'Identifies breakout patterns and triggers', 'chart' => 'trend'],
            ['key' => 'dairy_consumed',  'label' => 'Dairy Consumed',       'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Did you consume dairy today?',                              'why' => 'Dairy-acne correlation identification', 'chart' => 'none'],
            ['key' => 'sugar_consumed',  'label' => 'High-Sugar Foods',     'type' => 'boolean', 'frequency' => 'daily',  'how' => 'Did you consume high-sugar foods today?',                   'why' => 'Blood-sugar spike → sebum → breakout', 'chart' => 'none'],
            ['key' => 'sleep_hours',     'label' => 'Sleep Hours',          'type' => 'number',  'frequency' => 'daily',  'how' => 'Log hours slept',                                          'why' => 'Poor sleep raises cortisol → skin flares', 'chart' => 'trend'],
            ['key' => 'mood',            'label' => 'Mood (1–10)',          'type' => 'scale',   'frequency' => 'daily',  'how' => 'Rate mood each morning',                                    'why' => 'Stress-acne connection tracking', 'chart' => 'trend'],
        ];

        $typeDescriptions = [
            'Hormonal' => [
                'summary'    => "$name, your acne pattern — concentrated on the jawline and chin, worsening before your period — points clearly to hormonal acne. This is driven by androgen fluctuations creating excess sebum. The GlowClear protocol targets blood-sugar balance, androgen-reducing nutrients, and dairy elimination to clear your skin from the inside out.",
                'root_cause' => "Hormonal acne is driven by androgens (testosterone and DHT) stimulating sebaceous glands to overproduce oil. This typically manifests as deep, cystic breakouts along the jawline and chin — the classic hormonal pattern.\n\nAndrogen spikes are often linked to blood-sugar instability: when blood sugar rises sharply, insulin surges — and insulin directly stimulates androgen production. This is why sugar, dairy, and refined carbs are major hormonal-acne drivers.\n\nThe GlowClear protocol targets this at the root: blood-sugar balancing nutrition, DIM and zinc to reduce androgen activity, and spearmint tea — proven to lower testosterone in clinical studies.",
                'goals'      => ['Reduce androgen-driven sebum overproduction', 'Balance blood sugar to lower insulin spikes', 'Eliminate top dietary triggers (dairy, refined sugar)', 'Establish an AM/PM routine that prevents pore blockage', 'Achieve baseline clear skin by Day 60'],
            ],
            'Inflammatory' => [
                'summary'    => "$name, your acne profile — red, sensitive papules and pustules that react strongly to certain foods — indicates inflammatory acne. The protocol focuses on calming systemic inflammation through diet, gut healing, and barrier-repair skincare.",
                'root_cause' => "Inflammatory acne is driven by immune overactivation — your body treats certain bacteria (C. acnes) as a threat and launches an excessive inflammatory response. Diet, gut microbiome, and stress all influence this response.\n\nKey triggers: omega-6-heavy diets, gut dysbiosis, and environmental toxins. The fix: anti-inflammatory nutrition (omega-3 rich, colourful vegetables), gut-healing probiotics, and calming barrier-repair skincare.",
                'goals'      => ['Reduce systemic inflammation through nutrition', 'Heal gut microbiome with probiotics and fibre', 'Eliminate dietary inflammatory triggers', 'Repair and strengthen the skin barrier', 'Achieve consistently calm skin by Day 60'],
            ],
            'Comedonal' => [
                'summary'    => "$name, your blackheads, whiteheads, and congestion indicate comedonal acne — driven by excess oil and dead skin cells blocking pores. The GlowClear protocol targets this with chemical exfoliation, non-comedogenic skincare, and oil-balancing nutrition.",
                'root_cause' => "Comedonal acne forms when excess sebum and dead skin cells block follicles. Unlike inflammatory acne, comedones are typically non-red and non-inflamed — but if left untreated, they can become inflamed papules.\n\nRegular chemical exfoliation (salicylic acid) and niacinamide are the proven topical interventions. Dietarily, reducing saturated fats and refined carbs decreases sebum production.",
                'goals'      => ['Clear existing blackheads and whiteheads with salicylic acid', 'Reduce sebum overproduction through diet and niacinamide', 'Establish consistent exfoliation routine', 'Prevent pore blockage with non-comedogenic products', 'Smooth skin texture by Day 45'],
            ],
            'Fungal (Folliculitis)' => [
                'summary'    => "$name, IMPORTANT: your itchy, uniform bumps that worsen with sweat suggest fungal acne (Malassezia folliculitis) — NOT bacterial acne. This requires a completely different approach. The GlowClear protocol eliminates fungal triggers in skincare and diet while using anti-fungal topicals.",
                'root_cause' => "Fungal acne (Malassezia folliculitis) is caused by yeast overgrowth in hair follicles — NOT the same bacteria as regular acne. This is critical: antibacterial treatments DO NOT work and may worsen it.\n\nKey characteristics: uniform, itchy bumps; worse after sweating; often on forehead, chest, or back. Main triggers: oil-rich skincare, humid environments, sweat sitting on skin, and antibiotic use (which kills competing bacteria).\n\nThe fix: anti-fungal skincare ingredients (zinc pyrithione, selenium sulfide, ketoconazole), eliminating oils that feed Malassezia, and strict post-sweat cleansing.",
                'goals'      => ['Eliminate Malassezia-feeding oils from skincare routine', 'Implement anti-fungal cleansing protocol', 'Establish immediate post-sweat cleansing habit', 'Reduce recurrence through dietary adjustments', 'Clear fungal folliculitis within 30–45 days'],
            ],
            'Stress/Cortisol' => [
                'summary'    => "$name, your breakout pattern — tracking directly with stress and poor sleep — points to stress/cortisol acne. Cortisol stimulates oil production and inflammation simultaneously. The GlowClear protocol targets cortisol management, sleep optimisation, and adaptogens.",
                'root_cause' => "Stress triggers cortisol release — and cortisol directly stimulates sebaceous glands to produce more oil while simultaneously increasing inflammation. The result: more sebum AND more inflammatory response = more acne.\n\nThis explains why you break out during exam periods, work deadlines, or emotional stress — and why sleep deprivation (even one bad night) can trigger a breakout.\n\nThe GlowClear stress protocol targets cortisol at its source: sleep hygiene, adaptogenic herbs, and cortisol-lowering daily practices.",
                'goals'      => ['Lower chronic cortisol through evidence-based stress practices', 'Optimise sleep to reduce overnight cortisol spikes', 'Introduce adaptogenic herbs to buffer stress response', 'Break the stress-breakout cycle within 30 days', 'Build resilient skin that handles stress without breaking out'],
            ],
        ];

        $typeContent = $typeDescriptions[$subType] ?? $typeDescriptions['Hormonal'];

        return array_merge($typeContent, [
            'phase_1_title'          => 'Barrier Repair & Trigger Elimination',
            'phase_1_focus'          => 'Building the clear-skin foundation',
            'phase_1_description'    => 'The first 30 days focus on two things: eliminating what\'s driving your acne, and establishing a consistent topical routine that supports your skin barrier. You\'ll identify and remove dietary triggers, introduce your AM/PM routine, and begin supplements targeting your acne type.',
            'phase_1_weekly_actions' => [
                ['week' => 1, 'focus' => 'Eliminate & Cleanse',   'actions' => ['Remove dairy and refined sugar for 2 weeks (elimination trial)', 'Start AM/PM skincare routine exactly as prescribed', 'Begin zinc and omega-3 supplements', 'Take Day 1 baseline skin photo in consistent lighting'], 'milestone' => 'Consistent AM/PM routine established; baseline photo taken'],
                ['week' => 2, 'focus' => 'Nutrition & Hydration', 'actions' => ['Replace processed snacks with anti-inflammatory options', 'Add daily spearmint tea (hormonal) or probiotic (inflammatory)', 'Track daily clarity score (1–10) each morning', 'Audit skincare products for comedogenic ingredients'], 'milestone' => 'Dietary triggers removed; supplement stack started'],
                ['week' => 3, 'focus' => 'Sleep & Stress',        'actions' => ['Implement 10 PM screen-off rule', 'Add herbal sleep protocol', '5-minute morning breathwork practice', 'Review and remove all potentially problematic skincare items'], 'milestone' => 'Sleep improving; stress practice consistent'],
                ['week' => 4, 'focus' => 'Consolidation',         'actions' => ['Check Week 4 photo against baseline — celebrate any progress', 'Fine-tune routine based on skin response', 'Identify remaining triggers from daily logs', 'Assess supplement tolerance and adjust'], 'milestone' => 'Foundation protocol fully active; first visible improvements'],
            ],
            'phase_2_title'          => 'Active Treatment & Clearing',
            'phase_2_focus'          => 'Targeting active breakouts',
            'phase_2_description'    => 'With your foundation set and triggers reduced, Phase 2 intensifies the treatment. Active ingredients are introduced at higher concentrations, gut-healing protocols deepen, and the herbal protocol reaches full dose. This is where you should see visible clearing.',
            'phase_2_weekly_actions' => [
                ['week' => 5, 'focus' => 'Treatment Intensification', 'actions' => ['Introduce higher-concentration active ingredient (if tolerated)', 'Add gut-healing supplement (L-glutamine or bone broth)', 'Full herbal protocol active', 'Weekly photo comparison'], 'milestone' => 'Visible reduction in active breakouts'],
                ['week' => 6, 'focus' => 'Optimisation',             'actions' => ['Adjust routine based on skin feedback', 'Increase anti-inflammatory foods', 'Introduce spot treatment protocol', 'Evaluate dairy reintroduction (controlled)'], 'milestone' => 'Active breakouts significantly reduced'],
                ['week' => 7, 'focus' => 'Texture & Tone',           'actions' => ['Add Vitamin C serum for post-acne marks', 'Consistent SPF use (marks darken without protection)', 'Deepen sleep and stress protocol', 'Review trigger logs for patterns'], 'milestone' => 'Skin tone evening out; fewer new spots'],
                ['week' => 8, 'focus' => 'Sustaining',               'actions' => ['All protocols running smoothly', 'Track progress against Month 1 photos', 'Plan Phase 3 maintenance goals', 'Share progress (accountability partner)'], 'milestone' => 'Clearest skin to date; protocols feel natural'],
            ],
            'phase_3_title'          => 'Maintenance & Glow',
            'phase_3_focus'          => 'Locking in clear skin for life',
            'phase_3_description'    => 'Phase 3 is about sustainability. You\'ve cleared your skin — now build habits that keep it clear permanently without relying on an intensive protocol. Transition from active treatment to maintenance mode.',
            'phase_3_weekly_actions' => [
                ['week' => 9,  'focus' => 'Maintenance Mode',   'actions' => ['Reduce active ingredients to maintenance frequency', 'Identify your minimum effective routine', 'Establish flare-response playbook', 'Continue daily clarity tracking'], 'milestone' => 'Sustainable routine identified'],
                ['week' => 10, 'focus' => 'Lifestyle Mastery',  'actions' => ['Food reintroduction experiment (controlled)', 'Stress-proofing: plan for high-stress periods', 'Social accountability: share your journey', 'Refine diet based on reintroduction results'], 'milestone' => 'Understanding your personal trigger map'],
                ['week' => 11, 'focus' => 'Sustainability',     'actions' => ['Build emergency flare-response kit', 'Identify seasonal adjustments needed', 'Create 3-month maintenance plan', 'Long-term supplement strategy'], 'milestone' => 'Fully independent clear-skin protocol'],
                ['week' => 12, 'focus' => 'Transformation',     'actions' => ['Full 90-day photo comparison', 'Document your personalised clear-skin map', 'Set next-quarter skin goals', 'Celebrate — and share your story'], 'milestone' => 'Transformed skin and confidence'],
            ],
            'skincare_routine' => [
                'am' => [
                    ['step' => 'Gentle Cleanser', 'ingredient' => 'CeraVe Foaming, gentle sulphate-free', 'why' => 'Removes overnight oil without stripping barrier'],
                    ['step' => 'Active Treatment',  'ingredient' => 'Niacinamide 10% or Salicylic Acid 2% (comedonal)', 'why' => 'Pore-clearing and sebum-regulating'],
                    ['step' => 'Light Moisturiser', 'ingredient' => 'Non-comedogenic, oil-free', 'why' => 'Hydrates without blocking pores'],
                    ['step' => 'SPF 30–50',         'ingredient' => 'Mineral or lightweight chemical SPF', 'why' => 'Post-acne marks darken 10× without UV protection'],
                ],
                'pm' => [
                    ['step' => 'Double Cleanse (if wearing SPF/makeup)', 'ingredient' => 'Oil cleanser, then water-based cleanser', 'why' => 'Removes sunscreen and environmental pollution'],
                    ['step' => 'Gentle Cleanser',  'ingredient' => 'Same as AM', 'why' => 'Remove day\'s sebum and bacteria'],
                    ['step' => 'Exfoliant (3×/week)', 'ingredient' => 'BHA (salicylic acid) or AHA (glycolic) — not both', 'why' => 'Prevents dead-cell buildup that causes pore blockage'],
                    ['step' => 'Moisturiser',      'ingredient' => 'Richer than AM but still non-comedogenic', 'why' => 'Barrier repair happens during sleep'],
                    ['step' => 'Spot Treatment',   'ingredient' => 'Benzoyl peroxide 2.5% on active spots only', 'why' => 'Kills acne bacteria at the source'],
                ],
            ],
            'morning_routine' => [
                ['time' => '6:30 AM', 'action' => 'Complete AM skincare routine (cleanser → niacinamide → moisturiser → SPF)', 'why' => 'Consistent morning routine regulates sebum production'],
                ['time' => '7:00 AM', 'action' => 'Anti-inflammatory breakfast (avoid dairy and refined carbs)', 'why' => 'Blood sugar stability = less androgen = less oil'],
                ['time' => '7:30 AM', 'action' => 'Morning supplement stack (zinc, omega-3, DIM for hormonal)', 'why' => 'Zinc reduces androgenic sebum; omega-3 calms inflammation'],
                ['time' => '8:00 AM', 'action' => 'Spearmint tea (hormonal) or green tea (inflammatory)', 'why' => 'Spearmint reduces testosterone by 50% in clinical trials'],
            ],
            'afternoon_routine' => [
                ['time' => '12:30 PM', 'action' => 'Anti-inflammatory lunch — avoid dairy and high-GI foods', 'why' => 'Post-lunch blood-sugar control prevents afternoon androgen spike'],
                ['time' => '3:00 PM',  'action' => 'If sweating — blot dry, do NOT scrub', 'why' => 'Friction spreads bacteria and worsens acne'],
                ['time' => '3:30 PM',  'action' => 'Afternoon spearmint or chamomile tea', 'why' => 'Continued cortisol modulation through afternoon'],
            ],
            'evening_routine' => [
                ['time' => '6:30 PM', 'action' => 'Anti-inflammatory dinner — plenty of omega-3 and vegetables', 'why' => 'Evening nutrition supports overnight skin repair'],
                ['time' => '8:30 PM', 'action' => 'Begin PM skincare routine (double cleanse → exfoliant → moisturiser → spot treatment)', 'why' => 'Skin barrier repair is highest overnight — capitalise on it'],
                ['time' => '9:00 PM', 'action' => 'Screens off — dim lights', 'why' => 'Blue light increases cortisol; cortisol increases oil production'],
                ['time' => '9:30 PM', 'action' => 'Journaling or relaxation practice, then sleep by 10 PM', 'why' => 'Sleep deprivation triggers cortisol spike → next-day breakout'],
            ],
            'meal_plan' => [
                ['day' => 1, 'breakfast' => ['meal' => 'Scrambled Eggs with Avocado and Tomato', 'description' => 'Two eggs with half avocado and sliced tomatoes', 'benefit' => 'Omega-3 from eggs and healthy fats from avocado reduce inflammation'], 'lunch' => ['meal' => 'Grilled Chicken with Vegetable Stew', 'description' => 'Seasoned chicken with tomato-based vegetable stew and brown rice', 'benefit' => 'Low-GI meal keeps blood sugar stable; lean protein supports skin repair'], 'dinner' => ['meal' => 'Baked Fish with Steamed Vegetables', 'description' => 'Omega-3-rich fish with broccoli, spinach, and carrots', 'benefit' => 'Omega-3 directly reduces inflammatory acne'], 'snack' => ['meal' => 'Walnuts and Berries', 'description' => 'A handful of walnuts with fresh or frozen berries', 'benefit' => 'Anti-inflammatory omega-3 and antioxidants']],
                ['day' => 2, 'breakfast' => ['meal' => 'Oat Porridge with Seeds', 'description' => 'Rolled oats with flaxseeds, chia seeds, and banana', 'benefit' => 'Fibre stabilises blood sugar; flaxseeds provide omega-3'], 'lunch' => ['meal' => 'Bean and Vegetable Soup', 'description' => 'Mixed beans with leafy greens, tomatoes, and peppers', 'benefit' => 'High fibre supports gut health; beans provide zinc'], 'dinner' => ['meal' => 'Turkey with Sautéed Greens', 'description' => 'Lean turkey with spinach, garlic, and onions', 'benefit' => 'Lean protein with garlic — proven antibacterial support'], 'snack' => ['meal' => 'Cucumber and Hummus', 'description' => 'Fresh cucumber with chickpea hummus', 'benefit' => 'Hydration plus zinc from chickpeas']],
                ['day' => 3, 'breakfast' => ['meal' => 'Smoothie with Spinach, Mango, and Flaxseed', 'description' => 'Blended spinach, mango, flaxseed, and coconut water', 'benefit' => 'Antioxidant-rich; flaxseed omega-3 is anti-inflammatory'], 'lunch' => ['meal' => 'Sardines on Wholegrain with Salad', 'description' => 'Canned sardines with sliced cucumber and tomato on wholegrain', 'benefit' => 'Highest bioavailable omega-3 with low-GI bread'], 'dinner' => ['meal' => 'Chicken and Vegetable Curry (no dairy)', 'description' => 'Coconut milk-based curry with chicken and mixed vegetables', 'benefit' => 'Turmeric in curry is powerfully anti-inflammatory'], 'snack' => ['meal' => 'Apple with Almond Butter', 'description' => 'Sliced apple with 2 tablespoons of almond butter', 'benefit' => 'Blood-sugar stable snack with healthy fat']],
                ['day' => 4, 'breakfast' => ['meal' => 'Veggie Omelette', 'description' => 'Two eggs with spinach, peppers, and onions (no cheese)', 'benefit' => 'Protein-rich, dairy-free; eggs contain biotin for skin'], 'lunch' => ['meal' => 'Tuna Salad', 'description' => 'Tuna with mixed greens, avocado, cucumber, and olive oil', 'benefit' => 'High omega-3, zero dairy, blood-sugar stable'], 'dinner' => ['meal' => 'Grilled Mackerel with Sweet Potato', 'description' => 'Mackerel fillet with steamed sweet potato and greens', 'benefit' => 'Sweet potato is anti-inflammatory; mackerel is omega-3 powerhouse'], 'snack' => ['meal' => 'Pumpkin Seeds', 'description' => 'A handful of roasted pumpkin seeds', 'benefit' => 'Zinc-rich — directly reduces acne bacteria']],
                ['day' => 5, 'breakfast' => ['meal' => 'Plantain Pancakes (dairy-free)', 'description' => 'Mashed ripe plantain with eggs — no milk, no butter', 'benefit' => 'Naturally sweet, low-GI, dairy-free'], 'lunch' => ['meal' => 'Lentil and Vegetable Soup', 'description' => 'Red lentils with carrots, celery, cumin, and coriander', 'benefit' => 'Plant protein, high fibre — excellent for gut health and skin'], 'dinner' => ['meal' => 'Baked Chicken with Roasted Vegetables', 'description' => 'Herb-seasoned chicken with roasted broccoli, carrots, and peppers', 'benefit' => 'Broccoli is the highest-DIM food — directly reduces skin androgens'], 'snack' => ['meal' => 'Coconut Yoghurt with Seeds', 'description' => 'Dairy-free coconut yoghurt with chia and pumpkin seeds', 'benefit' => 'Probiotic support for gut-skin axis']],
                ['day' => 6, 'breakfast' => ['meal' => 'Chia Pudding', 'description' => 'Coconut milk chia pudding with mango and cashews', 'benefit' => 'Omega-3 from chia; anti-inflammatory mango antioxidants'], 'lunch' => ['meal' => 'Prawn and Vegetable Stir-fry', 'description' => 'Prawns with broccoli, snap peas, and garlic in olive oil', 'benefit' => 'Prawns are high in zinc and selenium — both skin-clearing minerals'], 'dinner' => ['meal' => 'Salmon with Quinoa and Greens', 'description' => 'Baked salmon fillet with quinoa and steamed spinach', 'benefit' => 'The highest omega-3 meal of the week — targets inflammation powerfully'], 'snack' => ['meal' => 'Mixed Berries', 'description' => 'Mixed fresh or frozen berries', 'benefit' => 'Antioxidants neutralise free radicals that worsen acne']],
                ['day' => 7, 'breakfast' => ['meal' => 'Avocado Toast on Sourdough (no dairy)', 'description' => 'Mashed avocado on sourdough with lemon and pepper', 'benefit' => 'Healthy fats reduce inflammation; sourdough is lower-GI than white bread'], 'lunch' => ['meal' => 'Grilled Fish Tacos (corn tortillas, no sour cream)', 'description' => 'Grilled white fish in corn tortillas with shredded cabbage and salsa', 'benefit' => 'Corn tortillas are lower-GI; cabbage contains skin-clearing DIM'], 'dinner' => ['meal' => 'Lamb and Vegetable Stew', 'description' => 'Lean lamb with tomatoes, courgette, and root vegetables', 'benefit' => 'Lamb is rich in zinc — essential for skin healing and sebum regulation'], 'snack' => ['meal' => 'Brazil Nuts (2–3 only)', 'description' => 'Two to three Brazil nuts', 'benefit' => 'Selenium-rich — just 2 Brazil nuts provide the daily selenium requirement for skin']],
            ],
            'supplements' => [
                ['name' => 'Zinc (Picolinate or Bisglycinate)',   'dosage' => '25–30mg daily', 'timing' => 'With dinner', 'benefit' => 'Reduces P. acnes bacteria, inhibits androgen activity, and accelerates skin healing', 'note' => 'Take with food to prevent nausea; do not exceed 40mg/day long-term'],
                ['name' => 'Omega-3 Fish Oil',                     'dosage' => '2000–3000mg EPA+DHA daily', 'timing' => 'With meals, split if possible', 'benefit' => 'Directly reduces inflammatory acne by lowering leukotriene activity', 'note' => 'Choose a brand tested for heavy metals'],
                ['name' => 'DIM (Diindolylmethane)',               'dosage' => '150–300mg daily', 'timing' => 'With a fatty meal', 'benefit' => 'FOR HORMONAL ACNE: detoxifies excess oestrogen and reduces skin-stimulating androgens', 'note' => 'Specifically for hormonal/cyclical acne; not needed for comedonal or fungal types'],
                ['name' => 'Probiotics (multi-strain)',            'dosage' => '10–50 billion CFU daily', 'timing' => 'With breakfast or on empty stomach — check your product', 'benefit' => 'Gut microbiome directly influences skin inflammation (gut-skin axis)', 'note' => 'Refrigerate if required; Lactobacillus rhamnosus has most acne evidence'],
                ['name' => 'Vitamin A (from food or supplement)',  'dosage' => 'From food: liver, eggs, orange vegetables. Supplement: beta-carotene 10mg', 'timing' => 'With meals', 'benefit' => 'Regulates skin cell turnover and prevents dead cell accumulation in pores', 'note' => 'Do NOT take high-dose preformed Vitamin A — it is teratogenic. Beta-carotene is safe.'],
            ],
            'herbal_protocols' => [
                ['herb' => 'Spearmint', 'local_name' => 'Spearmint tea (widely available)', 'preparation' => 'Steep 1 teaspoon dried spearmint in hot water for 5 minutes', 'dosage' => '2 cups daily (morning and afternoon)', 'benefit' => 'FOR HORMONAL ACNE: reduces free testosterone by up to 51% (clinical evidence)', 'caution' => 'Avoid if pregnant or trying to conceive'],
                ['herb' => 'Turmeric', 'local_name' => 'Ata-ile pupa (Yoruba) / Kurkuma', 'preparation' => 'Golden milk: 1 tsp turmeric in warm plant milk with black pepper', 'dosage' => '1–2 teaspoons daily with black pepper (enhances absorption 2000%)', 'benefit' => 'Potent anti-inflammatory that reduces the cytokine storm driving inflammatory acne', 'caution' => 'May stain — use caution on clothes and surfaces'],
                ['herb' => 'Neem (Dongoyaro)', 'local_name' => 'Dongoyaro (Yoruba/Hausa)', 'preparation' => 'Topical: neem oil diluted 1:10 in jojoba or sweet almond oil', 'dosage' => 'Apply 2–3 drops to affected areas after cleansing (NOT full face — spot use)', 'benefit' => 'Anti-bacterial, anti-fungal; inhibits P. acnes and Malassezia; anti-inflammatory', 'caution' => 'Very strong — patch test essential; undiluted neem can irritate; avoid eye area'],
                ['herb' => 'Burdock Root', 'local_name' => 'Available in health food stores and herbal shops', 'preparation' => 'Brew as tea from dried root or take as tincture', 'dosage' => '1 cup of tea daily, or 2ml tincture twice daily', 'benefit' => 'Traditional blood-purifying herb; supports liver detoxification of androgens and toxins', 'caution' => 'Avoid if allergic to daisies/chrysanthemums (same plant family)'],
            ],
            'lifestyle_tips' => [
                ['category' => 'Pillow Care',    'tip' => 'Change pillowcase every 2 days', 'detail' => 'Your pillowcase accumulates bacteria, dead skin cells, and hair products overnight. After 2 days it\'s actively depositing them back onto your skin.'],
                ['category' => 'Phone Hygiene',  'tip' => 'Wipe your phone screen daily with antibacterial wipe', 'detail' => 'Your phone harbours more bacteria than a toilet seat — and you press it against your face repeatedly.'],
                ['category' => 'Diet',           'tip' => 'Do a 2-week dairy elimination trial', 'detail' => 'Dairy contains hormones that directly stimulate oil glands. 80% of people who eliminate dairy see skin improvement within 2–4 weeks.'],
                ['category' => 'Touch',          'tip' => 'Stop touching your face', 'detail' => 'Your hands transfer bacteria and oils to your skin continuously. This is one of the most impactful habits to break.'],
                ['category' => 'Sleep',          'tip' => 'Skin repair is highest between 10 PM and 2 AM', 'detail' => 'Cortisol is lowest and growth hormone peaks during this window — both critical for skin healing. Missing this window = missing your best healing opportunity.'],
                ['category' => 'Stress',         'tip' => 'Morning breathwork (5 minutes)', 'detail' => 'Cortisol peaks in the first hour after waking. A brief breathwork session can reduce this spike by 20–30%, lowering your day\'s baseline oil production.'],
                ['category' => 'SPF',            'tip' => 'SPF is non-negotiable if you have post-acne marks', 'detail' => 'UV exposure darkens post-inflammatory hyperpigmentation (those red or brown marks after spots heal) by up to 10× without protection.'],
                ['category' => 'Sweating',       'tip' => 'Cleanse within 30 minutes of heavy sweating', 'detail' => 'Sweat mixed with bacteria and skin oils is a perfect acne-feeding environment. Same-day cleansing is critical for fungal and bacterial acne types.'],
            ],
            'tracking_guidance'  => $trackingGuidance,
            'flare_playbook' => [
                ['trigger' => 'New cystic breakout', 'response' => 'Apply 2.5% benzoyl peroxide spot treatment at night. Ice for 2 minutes to reduce swelling. Do NOT squeeze. Increase water intake.'],
                ['trigger' => 'Period-related flare (hormonal)', 'response' => 'Expect Days 21–28 to be harder. Double spearmint tea dose. Reduce sugar and dairy for the week before period. Do NOT change routine.'],
                ['trigger' => 'Diet slip (dairy/sugar)', 'response' => 'Not a crisis. Return to protocol immediately. Add extra omega-3 that day. A single slip does not set you back weeks.'],
                ['trigger' => 'Suspected fungal flare', 'response' => 'Switch to anti-fungal cleanser (Head & Shoulders or zinc pyrithione). Eliminate oil-based products. See dermatologist if no improvement in 2 weeks.'],
            ],
            'quick_win' => [
                'title'  => 'Take your Day 1 skin photo RIGHT NOW',
                'detail' => 'Same lighting (natural daylight), same angle (front and both sides), no filters. This is your baseline. In 30 days, when your skin is clearer, you\'ll want to compare. Most people underestimate their progress because they can\'t remember how they started.',
            ],
            'encouragement' => "$name, I want you to know something important: your acne is NOT your fault, and it does NOT define you.\n\nBut now you have a protocol designed for your specific acne type, your specific triggers, and your skin — not a generic advice sheet. Every decision in this plan is based on what the evidence shows actually works for acne like yours.\n\nAt Day 90, you\'ll have a clear-skin protocol that is uniquely yours — one that you understand deeply enough to maintain for life. Not just clear skin today, but the knowledge to keep it clear. That is the real transformation.\n\nYou can do this. One step, one day at a time.",
        ]);
    }
}
