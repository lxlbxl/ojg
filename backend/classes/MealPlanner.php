<?php
// Initializing additional columns if missing
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check for trigger_type column in daily_plans
    $hasTrigger = false;
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $stmt = $conn->query("SHOW COLUMNS FROM daily_plans LIKE 'trigger_type'");
        $hasTrigger = ($stmt->fetch() !== false);
    } else {
        $cols = $db->fetchAll("PRAGMA table_info(daily_plans)");
        foreach ($cols as $c)
            if ($c['name'] === 'trigger_type')
                $hasTrigger = true;
    }

    if (!$hasTrigger) {
        $db->query("ALTER TABLE daily_plans ADD COLUMN trigger_type VARCHAR(20) DEFAULT 'auto'");
    }
} catch (Exception $e) {
    // Ignore errors if table doesn't exist yet or other schema issues
}

class MealPlanner
{
    private $db;
    private $ai;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ai = new AIOrchestrator();
    }

    public function calculateCyclePhase($lastPeriodDate, $cycleLength = 28, $targetDate = null)
    {
        $lastPeriod = new DateTime($lastPeriodDate ?? 'now');
        $now = new DateTime($targetDate ?? 'now');
        $dayOfCycle = $now->diff($lastPeriod)->days % $cycleLength + 1;

        if ($dayOfCycle <= 5)
            return ['phase' => 'Menstrual', 'day' => $dayOfCycle];
        if ($dayOfCycle <= 14)
            return ['phase' => 'Follicular', 'day' => $dayOfCycle];
        if ($dayOfCycle <= 17)
            return ['phase' => 'Ovulatory', 'day' => $dayOfCycle];
        return ['phase' => 'Luteal', 'day' => $dayOfCycle];
    }

    public function getTodayPlan($userId, $dateStr = null)
    {
        $today = $dateStr ?? date('Y-m-d');
        // Check DB first
        $sql = "SELECT * FROM daily_plans WHERE user_id = :uid AND plan_date = :date";
        $plan = $this->db->fetch($sql, [':uid' => $userId, ':date' => $today]);

        if ($plan) {
            return json_decode($plan['plan_data'], true);
        }

        // if not exists, generate one
        return $this->generateDailyPlan($userId, $today);
    }

    public function generateDailyPlan($userId, $date, $triggerType = 'auto')
    {
        $startTime = microtime(true);
        $logId = $this->db->insert('ai_generation_logs', [
            'user_id' => $userId,
            'action' => 'daily_plan',
            'target_date' => $date,
            'status' => 'generating',
            'metadata' => json_encode(['trigger' => $triggerType])
        ]);

        try {
            // 1. Get User Profile
            $profile = $this->db->fetch("SELECT * FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
            if (!$profile) {
                $profile = ['pcos_type' => 'General', 'allergies' => 'None', 'dietary_preferences' => 'None', 'condition_type' => 'pcos'];
            }

            // Calculate Context Variables
            $programStart = new DateTime($profile['start_date'] ?? 'now');
            $now = new DateTime($date);
            $daysIn = $now->diff($programStart)->days + 1;
            $programWeek = ceil($daysIn / 7);

            // Funnel-specific context
            $conditionType = $profile['condition_type'] ?? 'pcos';
            if (!$conditionType)
                $conditionType = 'pcos'; // Fallback if empty string

            $vars = [
                'CONDITION_TYPE' => strtoupper($conditionType),
                'ALLERGIES' => !empty($profile['allergies']) ? $profile['allergies'] : 'None',
                'PREFERENCES' => !empty($profile['dietary_preferences']) ? $profile['dietary_preferences'] : 'None',
                'PROGRAM_WEEK' => "Week $programWeek",
                'DATE' => $date
            ];

            // Condition-specific context
            $regionProfile = [];
            try {
                if (class_exists('RegionProfile')) {
                    $regionResolver = new RegionProfile();
                    $regionProfile  = $regionResolver->resolveForUser($userId);
                }
            } catch (Exception $re) {
                // Non-fatal — fall back to defaults
            }

            $country = $regionProfile['country'] ?? 'Nigeria';
            $staplefoods = !empty($regionProfile['staple_foods'])
                ? implode(', ', array_slice($regionProfile['staple_foods'], 0, 6))
                : 'rice, beans, yam, plantain, fish, leafy greens';
            $sourcing = $regionProfile['where_to_source'] ?? 'local markets';
            $units    = $regionProfile['measurement_system'] ?? 'metric';

            if ($conditionType === 'pcos') {
                $cycleData = $this->calculateCyclePhase(
                    $profile['last_period_date'] ?? 'now',
                    $profile['cycle_length'] ?? 28
                );
                $vars['CYCLE_PHASE'] = $cycleData['phase'];
                $vars['PCOS_TYPE']   = !empty($profile['pcos_type']) ? $profile['pcos_type'] : 'General';
            }

            // ── Module manifest: which blocks to generate per condition ──────
            // Acne does NOT get a workout block.
            $includeMovement = !in_array($conditionType, ['acne']);
            $includeSkincareRoutine = in_array($conditionType, ['acne']);
            $includeFruitRitual = in_array($conditionType, ['pcos']);
            $includeHerbalTea   = in_array($conditionType, ['pcos', 'acne', 'weight']);

            // 3. Prepare Prompt
            $promptKey = $conditionType . '_meal_planner';

            // Try to get specific prompt, fallback to pcos_meal_planner
            $systemPromptRow = $this->db->fetch("SELECT prompt_text FROM system_prompts WHERE prompt_key = ?", [$promptKey]);
            if (!$systemPromptRow) {
                $promptKey = 'pcos_meal_planner';
            }

            // Build condition-specific user prompt
            $userPrompt = $this->buildMealPlannerPrompt(
                $conditionType,
                $vars,
                $country,
                $staplefoods,
                $sourcing,
                $units,
                $includeMovement,
                $includeSkincareRoutine,
                $includeFruitRitual,
                $includeHerbalTea,
                $regionProfile
            );


            $maxRetries = 1;
            $attempt = 0;
            $planData = null;

            while ($attempt <= $maxRetries) {
                $response = $this->ai->generateResponse($promptKey, $userPrompt, $vars);

                if (is_string($response)) {
                    $cleanJson = $this->cleanJson($response);
                    $planData = json_decode($cleanJson, true);
                }

                if ($planData && isset($planData['meals']) && !empty($planData['meals'])) {
                    // Enforce module manifest: strip forbidden blocks per condition
                    $planData = $this->enforceManifest($planData, $conditionType);
                    $planData['generated_at'] = date('Y-m-d H:i:s');
                    $planData['retries'] = $attempt;
                    break;
                }

                $attempt++;
                if ($attempt <= $maxRetries) {
                    error_log("Retrying AI generation for user $userId on $date (Attempt $attempt)");
                    usleep(500000); // 0.5s pause before retry
                }
            }

            if (!$planData) {
                // Final Fallback if all retries fail
                $planData = [
                    'raw_content' => $response ?? 'No response',
                    'error' => 'Failed to generate valid JSON plan after retries',
                    'meals' => [],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            }

            // 5. Save to DB
            $existing = $this->db->fetch("SELECT id FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $date]);

            if ($existing) {
                $this->db->update('daily_plans', [
                    'plan_data' => json_encode($planData),
                    'trigger_type' => $triggerType
                ], "id = :id", [':id' => $existing['id']]);
            } else {
                $this->db->insert('daily_plans', [
                    'user_id' => $userId,
                    'plan_date' => $date,
                    'plan_data' => json_encode($planData),
                    'trigger_type' => $triggerType
                ]);
            }

            // Update Log
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->db->update('ai_generation_logs', [
                'status' => 'success',
                'duration_ms' => $duration
            ], "id = :id", [':id' => $logId]);

            return $planData;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->db->update('ai_generation_logs', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => $duration
            ], "id = :id", [':id' => $logId]);
            throw $e;
        }
    }

    private function cleanJson($text)
    {
        if (!is_string($text))
            return '';
        if (preg_match('/```json(.*?)```/s', $text, $matches)) {
            return trim($matches[1]);
        }
        return $text;
    }

    /**
     * Strip blocks that are not in the condition's module manifest.
     * Acne must never receive a workout block; other conditions get appropriate blocks.
     */
    private function enforceManifest(array $planData, string $conditionType): array
    {
        $noMovement    = ['acne'];
        $noSkincare    = ['pcos', 'weight', 'mens'];
        $noCycleSync   = ['acne', 'weight', 'mens'];
        $noFruitRitual = ['acne', 'weight', 'mens'];

        if (in_array($conditionType, $noMovement)) {
            unset($planData['workout'], $planData['movement'], $planData['exercise']);
        }
        if (in_array($conditionType, $noSkincare)) {
            unset($planData['skincare_routine'], $planData['am_routine'], $planData['pm_routine']);
        }
        if (in_array($conditionType, $noCycleSync)) {
            unset($planData['cycle_sync'], $planData['cycle_phase_guidance']);
        }
        if (in_array($conditionType, $noFruitRitual)) {
            unset($planData['fruit_ritual']);
        }

        return $planData;
    }

    /**
     * Build a condition-aware, region-aware daily meal plan prompt.
     * Movement block is only requested when $includeMovement is true.
     */
    private function buildMealPlannerPrompt(
        string $conditionType,
        array  $vars,
        string $country,
        string $staplefoods,
        string $sourcing,
        string $units,
        bool   $includeMovement,
        bool   $includeSkincareRoutine,
        bool   $includeFruitRitual,
        bool   $includeHerbalTea,
        array  $regionProfile
    ): string {
        $condLabel  = strtoupper($conditionType);
        $weekLabel  = $vars['PROGRAM_WEEK'] ?? 'Week 1';
        $date       = $vars['DATE'] ?? date('Y-m-d');
        $allergies  = $vars['ALLERGIES'] ?? 'None';
        $prefs      = $vars['PREFERENCES'] ?? 'None';

        // Condition-specific clinical context line
        $clinicalCtx = match ($conditionType) {
            'pcos'   => "PCOS Type: {$vars['PCOS_TYPE']}. Cycle Phase: " . ($vars['CYCLE_PHASE'] ?? 'Follicular') . ". Prioritise blood-sugar stability and hormone-supporting nutrients.",
            'acne'   => "Skin goal: anti-inflammatory, low-glycaemic, dairy-free meals. Avoid high-GI foods, dairy, and refined sugar. Prioritise omega-3 and zinc.",
            'weight' => "Goal: sustainable calorie-controlled meals with protein-first structure. Each meal must have minimum 25g protein. Low glycaemic index throughout.",
            'mens'   => "Goal: testosterone-supporting nutrition. Protein minimum 30g per meal. Include zinc-rich and omega-3-rich foods. Support recovery and strength.",
            default  => "Goal: general health and hormonal balance.",
        };

        // Movement requirement line (manifest-gated)
        $movementSection = '';
        if ($includeMovement) {
            $movementSection = match ($conditionType) {
                'pcos'   => '4. MOVEMENT: Include a walking-based routine (NO intense HIIT). Step target minimum 6000/day. Time ranges required.',
                'weight' => '4. MOVEMENT: Include 30-minute strength or cardio session appropriate for Week ' . ($vars['PROGRAM_WEEK'] ?? '1') . '. Progressive — not the same every day.',
                'mens'   => '4. MOVEMENT: Include compound-movement strength session or conditioning. Note recovery priority.',
                default  => '4. MOVEMENT: Include appropriate movement for this condition.',
            };
        } else {
            // Acne: explicitly NO movement block
            $movementSection = '4. MOVEMENT: DO NOT include any workout or exercise block. This condition does not use a movement module.';
        }

        $fruitSection   = $includeFruitRitual
            ? "5. FRUIT RITUAL: Recommend ONE locally-available fruit for the day with hormonal benefit and 'why_it_works' field.\n"
            : '';
        $herbalSection  = $includeHerbalTea
            ? "6. HERBAL TEA: Include morning and evening herbal tea recommendations available in {$country}.\n"
            : '';
        $skincareSection = $includeSkincareRoutine
            ? "7. SKINCARE NOTE: Briefly note if any foods today specifically support skin clarity.\n"
            : '';

        // Build the JSON schema requirement based on manifest
        $workoutSchema  = $includeMovement ? '"workout": { "name": "...", "description": "...", "intensity": "...", "duration": "...", "time_start": "...", "time_end": "...", "activities": [] },' : '';
        $fruitSchema    = $includeFruitRitual ? '"fruit_ritual": { "name": "...", "portion": "...", "benefits": "...", "why_it_works": "...", "time_start": "...", "time_end": "..." },' : '';
        $herbalSchema   = $includeHerbalTea
            ? '"herbal_tea": { "morning": { "name": "...", "time_start": "...", "time_end": "...", "benefits": "...", "product_key": "..." }, "evening": { "name": "...", "time_start": "...", "time_end": "...", "benefits": "...", "product_key": "..." } },'
            : '';

        return "Generate a personalised daily {$condLabel} meal plan for this user.

PROFILE:
- Condition: {$condLabel}
- {$clinicalCtx}
- Allergies/Intolerances: {$allergies}
- Dietary Preferences: {$prefs}
- Program: {$weekLabel} | Date: {$date}

LOCALISATION (MANDATORY):
- Country: {$country}
- All meals MUST use foods available and culturally familiar in {$country}.
- Staple ingredients available: {$staplefoods}
- Where to source: {$sourcing}
- Units: {$units} system

CRITICAL REQUIREMENTS:
1. MEALS: All meals must be locally appropriate for {$country} — real dishes, not generic descriptions.
2. TIME RANGES: Provide 'time_start' and 'time_end' for EVERY meal and activity.
3. SHOPPING LIST: Derive from today's meals. Group by category. Note where to buy in {$country}.
{$movementSection}
{$fruitSection}{$herbalSection}{$skincareSection}
IMPORTANT: Output ONLY valid JSON. No text before or after.

Required JSON structure:
{
  \"meals\": {
    \"breakfast\": { \"name\": \"...\", \"description\": \"...\", \"calories\": 0, \"time_start\": \"07:00\", \"time_end\": \"08:00\", \"ingredients\": [{\"item\": \"...\", \"quantity\": \"...\"}], \"instructions\": [\"Step 1\"] },
    \"lunch\":     { \"name\": \"...\", \"description\": \"...\", \"calories\": 0, \"time_start\": \"12:30\", \"time_end\": \"14:00\", \"ingredients\": [], \"instructions\": [] },
    \"dinner\":    { \"name\": \"...\", \"description\": \"...\", \"calories\": 0, \"time_start\": \"18:30\", \"time_end\": \"20:00\", \"ingredients\": [], \"instructions\": [] },
    \"snack\":     { \"name\": \"...\", \"description\": \"...\", \"time_start\": \"15:30\", \"time_end\": \"16:30\" }
  },
  {$fruitSchema}
  {$herbalSchema}
  {$workoutSchema}
  \"shopping_list\": [{ \"item\": \"...\", \"quantity\": \"...\", \"category\": \"Produce/Meat/Pantry\", \"where_to_buy\": \"...\" }],
  \"hydration_goal\": \"2 litres (8 glasses)\",
  \"daily_quote\": \"Motivational quote relevant to {$condLabel} journey\"
}";
    }

    public function getShoppingList($userId, $startDate, $endDate)
    {
        $sql = "SELECT plan_data FROM daily_plans WHERE user_id = :uid AND plan_date BETWEEN :start AND :end";
        $rows = $this->db->fetchAll($sql, [
            ':uid' => $userId,
            ':start' => $startDate,
            ':end' => $endDate
        ]);

        $shoppingList = [];

        foreach ($rows as $row) {
            $data = json_decode($row['plan_data'], true);
            if (isset($data['shopping_list']) && is_array($data['shopping_list'])) {
                foreach ($data['shopping_list'] as $item) {
                    $name = $item['item'] ?? 'Unknown Item';
                    $key = strtolower(trim($name));

                    if (!isset($shoppingList[$key])) {
                        $shoppingList[$key] = [
                            'item' => $name,
                            'category' => $item['category'] ?? 'Other',
                            'qty' => []
                        ];
                    }
                    if (!empty($item['quantity'])) {
                        $shoppingList[$key]['qty'][] = $item['quantity'];
                    }
                }
            }
        }

        return $shoppingList;
    }

    public function swapMeal($userId, $mealType)
    {
        $today = date('Y-m-d');
        $plan = $this->db->fetch("SELECT id, plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $today]);
        if (!$plan)
            return ['success' => false, 'error' => 'No plan found for today'];

        $planData = json_decode($plan['plan_data'], true);
        $currentMeal = $planData['meals'][$mealType] ?? null;
        if (!$currentMeal)
            return ['success' => false, 'error' => 'Meal type not found'];

        // Get Profile for AI context
        $profile = $this->db->fetch("SELECT * FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);

        $prompt = "The user wants to swap their $mealType: '{$currentMeal['name']}'.
            User PCOS Type: {$profile['pcos_type']}.
            Allergies: {$profile['allergies']}.

            Suggest ONE alternative meal that fits their protocol.
            Output ONLY valid JSON in this format:
            { \"name\": \"Meal Name\", \"description\": \"Short description\", \"calories\": 0, \"shopping_list\": [{
            \"item\": \"...\", \"category\": \"...\", \"quantity\": \"...\" }] }";

        $response = $this->ai->generateResponse('pcos_meal_planner', $prompt, []);
        $newMeal = null;
        if (is_string($response)) {
            $newMeal = json_decode($this->cleanJson($response), true);
        }

        if ($newMeal) {
            // Update the plan
            $planData['meals'][$mealType] = [
                'name' => $newMeal['name'],
                'description' => $newMeal['description'],
                'calories' => $newMeal['calories']
            ];
            // Merge shopping list
            if (isset($newMeal['shopping_list'])) {
                $planData['shopping_list'] = array_merge($planData['shopping_list'] ?? [], $newMeal['shopping_list']);
            }

            $this->db->update('daily_plans', ['plan_data' => json_encode($planData)], "id = :id", [':id' => $plan['id']]);
            return ['success' => true, 'meal' => $planData['meals'][$mealType]];
        }

        return ['success' => false, 'error' => 'AI failed to generate alternative'];
    }

    public function generateWeeklyPlanRange($userId, $startDate, $endDate)
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($start <= $end) {
            $currentDate = $start->format('Y-m-d');

            // Check if plan exists (basic check)
            $exists = $this->db->fetch(
                "SELECT id FROM daily_plans WHERE user_id = :uid AND plan_date = :date",
                [':uid' => $userId, ':date' => $currentDate]
            );

            if (!$exists) {
                // Generate
                try {
                    $this->generateDailyPlan($userId, $currentDate, 'manual_bulk');
                } catch (Exception $e) {
                    error_log("Failed to generate plan for $currentDate: " . $e->getMessage());
                }
                // Respect API rate limits slightly if doing bulk
                usleep(500000); // 0.5s pause
            }

            $start->modify('+1 day');
        }
    }

    public function ensurePlansExist($userId, $days = 3)
    {
        $today = new DateTime();
        $results = [];

        for ($i = 0; $i <= $days; $i++) {
            $date = clone $today;
            if ($i > 0)
                $date->modify("+$i day");
            $dateStr = $date->format('Y-m-d');

            // Check if REAL plan exists (not just a log/placeholder)
            $sql = "SELECT plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date";
            $row = $this->db->fetch($sql, [':uid' => $userId, ':date' => $dateStr]);

            $hasValidPlan = false;
            if ($row) {
                $data = json_decode($row['plan_data'], true);
                if (isset($data['meals']) && !empty($data['meals'])) {
                    $hasValidPlan = true;
                }
            }

            if (!$hasValidPlan) {
                try {
                    $this->generateDailyPlan($userId, $dateStr, 'proactive');
                    $results[$dateStr] = 'generated';
                } catch (Exception $e) {
                    $results[$dateStr] = 'failed: ' . $e->getMessage();
                }
            } else {
                $results[$dateStr] = 'exists';
            }
        }
        return $results;
    }
}