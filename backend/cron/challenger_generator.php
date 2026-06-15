<?php
/**
 * Challenger generator — for any experiment that has been running > 7 days
 * with a clear winner, ask the AI to propose a new challenger variant
 * (different headline, pricing, or copy) so the bandit can keep learning.
 *
 * Cron schedule: weekly (Monday 08:00 UTC by default).
 *
 * Output: a new variants row with status='draft' and overrides JSON
 * filled in by the AI. The admin can review and promote it to 'active'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExperimentRepository.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/Bandit.php';

$script = basename(__FILE__);
$repo = new ExperimentRepository();

$lock = $repo->cronStart($script, 3600 * 6);
if (!$lock['acquired']) {
    fwrite(STDOUT, "[$script] skip — " . ($lock['reason'] ?? 'busy') . PHP_EOL);
    exit(0);
}

$ai = new AIOrchestrator();
$bandit = new Bandit();
$rows = 0;
$notes = [];

try {
    $experiments = $repo->listExperiments(null, 'running');
    foreach ($experiments as $exp) {
        $startedAt = strtotime($exp['created_at'] ?? 'now');
        if ($startedAt === false || (time() - $startedAt) < 86400 * 7)
            continue;

        $variants = $repo->listVariants((int) $exp['id']);
        if (count($variants) < 2)
            continue;

        $decision = $bandit->decide($exp, $variants);
        if (empty($decision['winner_variant_id']))
            continue;
        if (($decision['confidence'] ?? 0) < 0.9)
            continue;

        $winner = null;
        foreach ($variants as $v) {
            if ((int) $v['id'] === (int) $decision['winner_variant_id']) {
                $winner = $v;
                break;
            }
        }
        if (!$winner)
            continue;

        $vars = [
            'experiment_name' => $exp['name'],
            'funnel' => $exp['funnel'],
            'stage' => $exp['stage'] ?? 'landing',
            'winner_name' => $winner['name'],
            'winner_overrides' => json_decode($winner['overrides_json'] ?? '{}', true) ?: [],
            'confidence' => round(($decision['confidence'] ?? 0) * 100, 1),
        ];

        $raw = $ai->generateResponse(
            'challenger_generator',
            "You are optimising a winning funnel page. Output a single new "
            . "variant as JSON. Include one of: 'text', 'html', 'attr', "
            . "'style', or 'config' override. The new variant should differ "
            . "meaningfully (different headline angle, urgency mechanism, "
            . "social proof type, or pricing framing). Reply with JSON only.",
            $vars
        );

        if (!is_string($raw) || $raw === '')
            continue;

        // Strip code fences if present.
        $clean = trim(preg_replace('/^```(?:json)?/i', '', $raw));
        $clean = trim(preg_replace('/```$/', '', $clean));
        $overrides = json_decode($clean, true);
        if (!is_array($overrides) || empty($overrides)) {
            $notes[] = "exp#{$exp['id']} AI did not return parseable JSON";
            continue;
        }

        $repo->addVariant([
            'experiment_id' => (int) $exp['id'],
            'name' => 'Challenger ' . date('Y-m-d'),
            'slug' => 'challenger-' . substr(md5((string) microtime(true)), 0, 6),
            'type' => 'challenger',
            'overrides_json' => json_encode($overrides, JSON_UNESCAPED_SLASHES),
            'traffic_weight' => 10,
            'status' => 'draft',
        ]);
        $rows++;
        $notes[] = "exp#{$exp['id']} challenger drafted";
    }
} catch (Throwable $e) {
    $repo->cronFinish($script, $rows, $notes, false, $e->getMessage());
    fwrite(STDERR, "[$script] fatal: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$repo->cronFinish($script, $rows, $notes, true);
fwrite(STDOUT, "[$script] ok — drafted $rows challengers" . PHP_EOL);
