<?php
/**
 * Golden-output CI test runner.
 *
 * Usage (from project root):
 *   php tests/golden_output/run_fixture_tests.php
 *
 * Each fixture JSON defines assertions that a real AI-generated plan must satisfy.
 * This script validates the assertion contract itself (structure) — not real AI output.
 * To run with real AI output, pipe plan JSON via stdin or pass a plan file path:
 *   php run_fixture_tests.php --plan=path/to/plan.json --fixture=acne_serbia
 */

require_once __DIR__ . '/../../backend/classes/PlanValidator.php';

$fixtures = [
    'acne_serbia'      => __DIR__ . '/acne_serbia_fixture.json',
    'pcos_kenya'       => __DIR__ . '/pcos_kenya_fixture.json',
    'weight_usa'       => __DIR__ . '/weight_usa_fixture.json',
    'mens_philippines' => __DIR__ . '/mens_philippines_fixture.json',
];

$passed  = 0;
$failed  = 0;
$results = [];

// Parse CLI args
$opts      = getopt('', ['plan:', 'fixture:']);
$planFile  = $opts['plan'] ?? null;
$fixtureId = $opts['fixture'] ?? null;

if ($planFile && $fixtureId) {
    // Single-plan validation mode
    if (!isset($fixtures[$fixtureId])) {
        die("Unknown fixture '$fixtureId'. Available: " . implode(', ', array_keys($fixtures)) . "\n");
    }
    $plan    = json_decode(file_get_contents($planFile), true);
    $fixture = json_decode(file_get_contents($fixtures[$fixtureId]), true);

    if (!$plan) {
        die("Failed to parse plan JSON from $planFile\n");
    }

    $result = runFixtureAssertions($plan, $fixture, $fixtureId);
    printResult($fixtureId, $result);
    exit($result['pass'] ? 0 : 1);
}

// Fixture contract self-validation mode (CI structural check)
echo "=== OJG Herbal — Golden Output CI Fixture Validation ===\n\n";

foreach ($fixtures as $id => $path) {
    $fixture = json_decode(file_get_contents($path), true);
    if (!$fixture) {
        $results[$id] = ['pass' => false, 'errors' => ["Invalid JSON in fixture file: $path"]];
        $failed++;
        continue;
    }

    $contractErrors = validateFixtureContract($fixture, $id);
    if (empty($contractErrors)) {
        $results[$id] = ['pass' => true, 'errors' => [], 'warnings' => []];
        $passed++;
    } else {
        $results[$id] = ['pass' => false, 'errors' => $contractErrors];
        $failed++;
    }
}

// Print summary
foreach ($results as $id => $r) {
    $status = $r['pass'] ? '✅ PASS' : '❌ FAIL';
    echo "$status  $id\n";
    foreach ($r['errors'] ?? [] as $err) {
        echo "       ERROR: $err\n";
    }
    foreach ($r['warnings'] ?? [] as $w) {
        echo "       WARN:  $w\n";
    }
}

echo "\n--- $passed passed, $failed failed ---\n";
exit($failed > 0 ? 1 : 0);

// ---------------------------------------------------------------------------

/**
 * Validate that a fixture JSON has all required contract fields.
 */
function validateFixtureContract(array $fixture, string $id): array
{
    $errors = [];

    $required = ['_meta', 'required_keys', 'forbidden_keys', 'tracking_required_keys', 'localisation_assertions', 'structure_assertions'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $fixture)) {
            $errors[] = "Fixture '$id' missing top-level key: '$key'";
        }
    }

    // Meta fields
    foreach (['fixture', 'condition', 'region', 'country'] as $m) {
        if (empty($fixture['_meta'][$m])) {
            $errors[] = "Fixture '$id' _meta missing '$m'";
        }
    }

    // Localisation assertions must specify meal_plan_days
    if (isset($fixture['localisation_assertions']) && empty($fixture['localisation_assertions']['meal_plan_days'])) {
        $errors[] = "Fixture '$id' localisation_assertions missing 'meal_plan_days'";
    }

    return $errors;
}

/**
 * Run a fixture's assertions against a real plan array.
 */
function runFixtureAssertions(array $plan, array $fixture, string $id): array
{
    $errors   = [];
    $warnings = [];
    $meta     = $fixture['_meta'];
    $condition = $meta['condition'];
    $country   = $meta['country'];

    // Use PlanValidator first
    $regionProfile = ['country_code' => $meta['region'], 'country_name' => $country];
    $validation    = PlanValidator::validate($plan, $condition, $regionProfile);
    $errors        = array_merge($errors, $validation['errors']);
    $warnings      = array_merge($warnings, $validation['warnings']);

    // Required keys
    foreach ($fixture['required_keys'] ?? [] as $key) {
        if (!array_key_exists($key, $plan)) {
            $errors[] = "Missing required key '$key'";
        }
    }

    // Forbidden keys
    foreach ($fixture['forbidden_keys'] ?? [] as $key) {
        if (!empty($plan[$key])) {
            $errors[] = "Forbidden key '$key' present and non-empty for $condition/$country";
        }
    }

    // Tracking keys
    if (isset($plan['tracking_guidance']) && is_array($plan['tracking_guidance'])) {
        $trackingKeys = array_column($plan['tracking_guidance'], 'key');
        foreach ($fixture['tracking_required_keys'] ?? [] as $reqKey) {
            if (!in_array($reqKey, $trackingKeys, true)) {
                $errors[] = "Missing required tracking key '$reqKey'";
            }
        }
    }

    // Localisation assertions
    $loc = $fixture['localisation_assertions'] ?? [];
    if (!empty($loc['meal_plan_days'])) {
        $dayCount = count($plan['meal_plan'] ?? []);
        if ($dayCount < $loc['meal_plan_days']) {
            $errors[] = "meal_plan has $dayCount days; expected {$loc['meal_plan_days']}";
        }
    }

    if (!empty($loc['forbidden_food_markers'])) {
        $mealText = strtolower(json_encode($plan['meal_plan'] ?? []));
        foreach ($loc['forbidden_food_markers'] as $marker) {
            if (str_contains($mealText, $marker)) {
                $errors[] = "Localisation failure: forbidden food marker '$marker' found in meal_plan for $country";
            }
        }
    }

    if (!empty($loc['measurement_system'])) {
        $planText = strtolower(json_encode($plan));
        $system   = $loc['measurement_system'];
        if ($system === 'imperial') {
            if (str_contains($planText, ' kg') && !str_contains($planText, 'lbs')) {
                $warnings[] = "Localisation: plan may be using metric (kg) for imperial country ($country)";
            }
        }
    }

    // Structure assertions
    $struct = $fixture['structure_assertions'] ?? [];
    if (!empty($struct['supplements_min'])) {
        $count = count($plan['supplements'] ?? []);
        if ($count < $struct['supplements_min']) {
            $warnings[] = "supplements has $count entries; expected at least {$struct['supplements_min']}";
        }
    }
    if (!empty($struct['herbal_protocols_min'])) {
        $count = count($plan['herbal_protocols'] ?? []);
        if ($count < $struct['herbal_protocols_min']) {
            $warnings[] = "herbal_protocols has $count entries; expected at least {$struct['herbal_protocols_min']}";
        }
    }

    return [
        'pass'     => empty($errors),
        'errors'   => $errors,
        'warnings' => $warnings,
    ];
}

function printResult(string $id, array $result): void
{
    $status = $result['pass'] ? '✅ PASS' : '❌ FAIL';
    echo "$status  $id\n";
    foreach ($result['errors'] as $e) {
        echo "  ERROR: $e\n";
    }
    foreach ($result['warnings'] as $w) {
        echo "  WARN:  $w\n";
    }
}
