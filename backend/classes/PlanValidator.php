<?php

/**
 * PlanValidator — validates AI-generated plan JSON against per-condition schemas,
 * enforces herb dosage bounds, and classifies compliance.
 *
 * Usage:
 *   $result = PlanValidator::validate($planData, 'acne', $regionProfile);
 *   if (!$result['valid']) { handle errors or use fallback }
 */
class PlanValidator
{
    // Required top-level keys for every condition
    private const COMMON_REQUIRED = [
        'summary',
        'root_cause',
        'goals',
        'phase_1_title', 'phase_1_focus', 'phase_1_description', 'phase_1_weekly_actions',
        'phase_2_title', 'phase_2_focus', 'phase_2_description', 'phase_2_weekly_actions',
        'phase_3_title', 'phase_3_focus', 'phase_3_description', 'phase_3_weekly_actions',
        'morning_routine', 'afternoon_routine', 'evening_routine',
        'meal_plan',
        'supplements',
        'herbal_protocols',
        'lifestyle_tips',
        'tracking_guidance',
        'quick_win',
        'sourcing_guide',
        'encouragement',
    ];

    // Keys that must NOT appear in a given condition's plan
    private const FORBIDDEN_KEYS = [
        'pcos'   => ['skincare_routine', 'flare_playbook', 'skin_photo'],
        'acne'   => ['workout', 'movement_plan', 'exercise_plan', 'cycle_sync', 'strength_protocol'],
        'weight' => ['skincare_routine', 'flare_playbook', 'skin_photo', 'cycle_sync'],
        'mens'   => ['skincare_routine', 'flare_playbook', 'skin_photo', 'cycle_sync'],
    ];

    // Required condition-specific keys
    private const CONDITION_REQUIRED = [
        'pcos'   => ['herbal_protocols', 'tracking_guidance'],
        'acne'   => ['skincare_routine', 'flare_playbook'],
        'weight' => ['movement_plan', 'plateau_protocol'],
        'mens'   => ['sleep_recovery_protocol'],
    ];

    // Required tracking keys per condition
    private const REQUIRED_TRACKING_KEYS = [
        'pcos'   => ['cycle_day', 'energy', 'mood'],
        'acne'   => ['skin_photo', 'skin_clarity', 'flare_count'],
        'weight' => ['weight_daily', 'weight_7day', 'energy'],
        'mens'   => ['energy', 'libido', 'sleep_hours', 'sleep_quality', 'strength_done'],
    ];

    // Dosage bounds [min, max] in mg/day — null means no lower/upper bound check
    private const HERB_DOSAGE_BOUNDS = [
        'berberine'             => [null, 1500],
        'ashwagandha'           => [null, 600],
        "st john's wort"        => [null, 900],
        'st. john\'s wort'      => [null, 900],
        'vitex'                 => [null, 400],
        'valerian'              => [null, 900],
        'moringa'               => [null, 2000],
        'fenugreek'             => [null, 2000],
        'turmeric'              => [null, 3000],
        'curcumin'              => [null, 3000],
        'ginger'                => [null, 2000],
        'dim'                   => [null, 300],
        'milk thistle'          => [null, 420],
    ];

    // Herbs that require an interaction flag if user is on certain medications
    private const INTERACTION_FLAGS = [
        "st john's wort"    => ['contraceptives', 'antidepressants', 'hiv', 'warfarin', 'blood thinners'],
        'st. john\'s wort'  => ['contraceptives', 'antidepressants', 'hiv', 'warfarin', 'blood thinners'],
        'berberine'         => ['metformin', 'blood thinners'],
        'vitex'             => ['contraceptives'],
        'bitter melon'      => ['metformin', 'diabetes medications'],
        'ampalaya'          => ['metformin', 'diabetes medications'],
    ];

    /**
     * Validate a decoded plan array against condition-specific rules.
     *
     * @param array       $plan          Decoded plan JSON
     * @param string      $condition     'pcos'|'acne'|'weight'|'mens'
     * @param array|null  $regionProfile Resolved region profile (optional, used for localisation checks)
     * @return array {
     *   valid: bool,
     *   score: int (0-100),
     *   classification: 'pass'|'warn'|'fail',
     *   errors: string[],
     *   warnings: string[],
     * }
     */
    public static function validate(array $plan, string $condition, ?array $regionProfile = null): array
    {
        $condition = strtolower(trim($condition));
        $errors    = [];
        $warnings  = [];

        // 1. Common required keys
        foreach (self::COMMON_REQUIRED as $key) {
            if (!array_key_exists($key, $plan)) {
                $errors[] = "Missing required key: '$key'";
            }
        }

        // 2. Condition-specific required keys
        $condRequired = self::CONDITION_REQUIRED[$condition] ?? [];
        foreach ($condRequired as $key) {
            if (!array_key_exists($key, $plan)) {
                $errors[] = "Missing condition-required key for $condition: '$key'";
            }
        }

        // 3. Forbidden key check
        $forbidden = self::FORBIDDEN_KEYS[$condition] ?? [];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $plan) && !empty($plan[$key])) {
                $errors[] = "Forbidden key present for $condition: '$key' — must not appear in this condition's plan";
            }
        }

        // 4. meal_plan: must have 7 days
        if (isset($plan['meal_plan']) && is_array($plan['meal_plan'])) {
            $dayCount = count($plan['meal_plan']);
            if ($dayCount < 7) {
                $errors[] = "meal_plan must have 7 days; found $dayCount";
            }
            foreach ($plan['meal_plan'] as $i => $day) {
                foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
                    if (empty($day[$meal])) {
                        $warnings[] = "meal_plan day " . ($i + 1) . " missing '$meal'";
                    }
                }
            }
        }

        // 5. Phase weekly actions: each phase must have 4 weeks
        foreach ([1, 2, 3] as $phase) {
            $key = "phase_{$phase}_weekly_actions";
            if (isset($plan[$key]) && is_array($plan[$key])) {
                if (count($plan[$key]) < 4) {
                    $warnings[] = "$key has fewer than 4 weekly entries";
                }
                foreach ($plan[$key] as $w) {
                    if (empty($w['actions']) || !is_array($w['actions'])) {
                        $warnings[] = "$key entry missing 'actions' array";
                    }
                    if (empty($w['milestone'])) {
                        $warnings[] = "$key entry missing 'milestone'";
                    }
                }
            }
        }

        // 6. tracking_guidance: validate schema and required keys
        if (isset($plan['tracking_guidance']) && is_array($plan['tracking_guidance'])) {
            $foundKeys = [];
            foreach ($plan['tracking_guidance'] as $i => $item) {
                if (isset($item['key'])) {
                    $foundKeys[] = $item['key'];
                    // Validate structured schema fields
                    foreach (['key', 'label', 'type', 'frequency', 'how', 'why', 'chart'] as $field) {
                        if (!isset($item[$field])) {
                            $warnings[] = "tracking_guidance[$i] missing field '$field' (key: {$item['key']})";
                        }
                    }
                } elseif (isset($item['what'])) {
                    // Legacy schema — acceptable with a warning
                    $warnings[] = "tracking_guidance[$i] uses legacy 'what' schema instead of structured key/label/type schema";
                } else {
                    $errors[] = "tracking_guidance[$i] has neither 'key' (new schema) nor 'what' (legacy schema)";
                }
            }

            // Check required tracking keys per condition
            $requiredTracking = self::REQUIRED_TRACKING_KEYS[$condition] ?? [];
            foreach ($requiredTracking as $requiredKey) {
                if (!in_array($requiredKey, $foundKeys, true)) {
                    $errors[] = "tracking_guidance missing required key '$requiredKey' for condition '$condition'";
                }
            }
        }

        // 7. Acne-specific: skincare_routine must have am and pm
        if ($condition === 'acne' && isset($plan['skincare_routine'])) {
            foreach (['am', 'pm'] as $slot) {
                if (empty($plan['skincare_routine'][$slot]) || !is_array($plan['skincare_routine'][$slot])) {
                    $errors[] = "skincare_routine.$slot is missing or empty";
                }
            }
        }

        // 8. Acne-specific: flare_playbook must be present and non-empty
        if ($condition === 'acne') {
            if (empty($plan['flare_playbook']) || !is_array($plan['flare_playbook'])) {
                $errors[] = "flare_playbook is required for acne plans and must be a non-empty array";
            }
        }

        // 9. Weight-specific: plateau_protocol must be present
        if ($condition === 'weight') {
            if (empty($plan['plateau_protocol']) || !is_array($plan['plateau_protocol'])) {
                $errors[] = "plateau_protocol is required for weight plans";
            }
            if (empty($plan['movement_plan']['weeks']) || !is_array($plan['movement_plan']['weeks'])) {
                $errors[] = "movement_plan.weeks must be a non-empty array for weight plans";
            }
        }

        // 10. Mens-specific: sleep_recovery_protocol must be present
        if ($condition === 'mens') {
            if (empty($plan['sleep_recovery_protocol']) || !is_array($plan['sleep_recovery_protocol'])) {
                $errors[] = "sleep_recovery_protocol is required for mens plans";
            }
        }

        // 11. Herb safety: dosage bounds gate
        if (isset($plan['supplements']) && is_array($plan['supplements'])) {
            foreach ($plan['supplements'] as $i => $supp) {
                if (empty($supp['name'])) {
                    continue;
                }
                $warnings = array_merge($warnings, self::checkHerbDosage($supp['name'], $supp['dosage'] ?? '', $i, 'supplements'));
            }
        }
        if (isset($plan['herbal_protocols']) && is_array($plan['herbal_protocols'])) {
            foreach ($plan['herbal_protocols'] as $i => $herb) {
                if (empty($herb['herb'])) {
                    continue;
                }
                $warnings = array_merge($warnings, self::checkHerbDosage($herb['herb'], $herb['dosage'] ?? '', $i, 'herbal_protocols'));

                // Local name required when region profile is present
                if ($regionProfile && empty($herb['local_name'])) {
                    $warnings[] = "herbal_protocols[$i] ({$herb['herb']}) missing local_name — region profile was provided";
                }
            }
        }

        // 12. quick_win: must be present and non-empty
        if (empty($plan['quick_win']['title'])) {
            $warnings[] = "quick_win.title is empty or missing";
        }

        // 13. sourcing_guide: at least one entry
        if (empty($plan['sourcing_guide']) || !is_array($plan['sourcing_guide'])) {
            $warnings[] = "sourcing_guide is missing or empty";
        }

        // 14. Localisation check: if region profile provided, flag generic Nigerian foods
        if ($regionProfile && isset($plan['meal_plan'])) {
            $nigerianMarkers = ['egusi', 'eba', 'akara', 'suya'];
            $countryCode = $regionProfile['country_code'] ?? '';
            if ($countryCode !== 'NG') {
                $planText = strtolower(json_encode($plan['meal_plan']));
                foreach ($nigerianMarkers as $marker) {
                    if (str_contains($planText, $marker)) {
                        $warnings[] = "Localisation issue: Nigerian food marker '$marker' found in meal_plan but country is $countryCode — verify localisation";
                    }
                }
            }
        }

        // Compute score and classification
        $errorPenalty   = count($errors) * 20;
        $warningPenalty = count($warnings) * 5;
        $score          = max(0, 100 - $errorPenalty - $warningPenalty);

        $classification = 'pass';
        if (!empty($errors) || $score < 50) {
            $classification = 'fail';
        } elseif (!empty($warnings) || $score < 80) {
            $classification = 'warn';
        }

        return [
            'valid'          => empty($errors),
            'score'          => $score,
            'classification' => $classification,
            'errors'         => $errors,
            'warnings'       => $warnings,
        ];
    }

    /**
     * Check a single herb/supplement against dosage bounds.
     * Returns array of warning strings (empty if safe).
     */
    private static function checkHerbDosage(string $name, string $dosageRaw, int $index, string $section): array
    {
        $warnings = [];
        $nameLower = strtolower(trim($name));

        foreach (self::HERB_DOSAGE_BOUNDS as $herb => $bounds) {
            if (!str_contains($nameLower, $herb)) {
                continue;
            }
            [, $maxMg] = $bounds;

            // Extract numeric value from dosage string (e.g. "1500mg", "500 mg", "1.5g")
            $parsed = self::parseDosageMg($dosageRaw);
            if ($parsed === null) {
                continue;
            }

            if ($maxMg !== null && $parsed > $maxMg) {
                $warnings[] = "$section[$index] ({$name}): dosage {$dosageRaw} exceeds maximum safe limit of {$maxMg}mg/day — REDUCE";
            }
        }

        return $warnings;
    }

    /**
     * Parse a dosage string to milligrams. Returns null if unparseable.
     * Handles: "500mg", "500 mg", "1.5g", "1500 mg/day", "2 capsules 250mg"
     */
    private static function parseDosageMg(string $dosage): ?float
    {
        // Match grams first (e.g. "1.5g" or "2 g")
        if (preg_match('/(\d+(?:\.\d+)?)\s*g(?:ram)?(?:s)?/i', $dosage, $m)) {
            return (float) $m[1] * 1000;
        }
        // Match milligrams (e.g. "500mg", "500 mg")
        if (preg_match('/(\d+(?:\.\d+)?)\s*mg/i', $dosage, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * Classify validation result for logging.
     * Returns 'pass'|'warn'|'fail'
     */
    public static function classify(array $validationResult): string
    {
        return $validationResult['classification'] ?? 'fail';
    }

    /**
     * Check medication interactions for herbs in a plan.
     * Returns array of interaction warning strings.
     */
    public static function checkInteractions(array $plan, string $medications): array
    {
        $warnings  = [];
        $medsLower = strtolower($medications);

        $herbs = array_merge(
            array_column($plan['herbal_protocols'] ?? [], 'herb'),
            array_column($plan['supplements'] ?? [], 'name')
        );

        foreach ($herbs as $herb) {
            $herbLower = strtolower($herb);
            foreach (self::INTERACTION_FLAGS as $flagHerb => $interactsWith) {
                if (!str_contains($herbLower, $flagHerb)) {
                    continue;
                }
                foreach ($interactsWith as $drug) {
                    if (str_contains($medsLower, $drug)) {
                        $warnings[] = "INTERACTION: {$herb} has a known interaction with '{$drug}' (found in client medications) — flag for medical review";
                    }
                }
            }
        }

        return $warnings;
    }
}
