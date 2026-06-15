<?php
/**
 * Weekly AI diagnostics — interpret experiment data via OpenRouter and
 * surface an insight row for the admin dashboard.
 *
 * Cron schedule: weekly (Monday 06:00 UTC by default).
 *
 * For each running experiment with at least 100 exposures, it asks the AI
 * orchestrator to write a short plain-English report covering: winner
 * confidence, AOV uplift, segment hints, and next-step recommendation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExperimentRepository.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/Bandit.php';

$script = basename(__FILE__);
$repo = new ExperimentRepository();

$lock = $repo->cronStart($script, 3600 * 6); // 6h debounce
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
        $variants = $repo->listVariants((int) $exp['id']);
        $summary = $repo->summaryForExperiment((int) $exp['id']);
        $totalExposures = array_sum(array_column($summary, 'exposures'));
        if ($totalExposures < 100)
            continue;

        $decision = $bandit->decide($exp, $variants);
        $winnerName = 'no clear winner';
        if (!empty($decision['winner_variant_id'])) {
            foreach ($variants as $v) {
                if ((int) $v['id'] === (int) $decision['winner_variant_id']) {
                    $winnerName = $v['name'];
                }
            }
        }

        $vars = [
            'experiment_name' => $exp['name'],
            'funnel' => $exp['funnel'],
            'stage' => $exp['stage'] ?? 'landing',
            'variants' => array_map(function ($v) use ($summary) {
                $row = null;
                foreach ($summary as $s) {
                    if ((int) $s['variant_id'] === (int) $v['id']) {
                        $row = $s;
                        break;
                    }
                }
                return [
                    'name' => $v['name'],
                    'exposures' => (int) ($row['exposures'] ?? 0),
                    'conversions' => (int) ($row['conversions'] ?? 0),
                    'revenue' => (float) ($row['revenue'] ?? 0),
                ];
            }, $variants),
            'winner' => $winnerName,
            'confidence' => round(($decision['confidence'] ?? 0) * 100, 1),
            'expected_loss' => $decision['expected_loss'] ?? null,
        ];

        $insight = $ai->generateResponse(
            'experiment_diagnostic',
            "Analyse this experiment and write a 4-6 sentence plain-English "
            . "diagnostic for the marketing team. Cover: winner, confidence, "
            . "AOV/copy variants to consider next, and any segment surprises.",
            $vars
        );

        if (is_string($insight) && $insight !== '') {
            $repo->saveInsight([
                'experiment_id' => (int) $exp['id'],
                'insight_type' => 'weekly_diagnostic',
                'title' => "Weekly diagnostic: {$exp['name']}",
                'body' => $insight,
                'confidence' => $decision['confidence'] ?? null,
                'generated_by' => 'ai_diagnostics',
            ]);
            $rows++;
            $notes[] = "exp#{$exp['id']} diagnostic saved";
        } else {
            $notes[] = "exp#{$exp['id']} AI returned no usable output";
        }
    }
} catch (Throwable $e) {
    $repo->cronFinish($script, $rows, $notes, false, $e->getMessage());
    fwrite(STDERR, "[$script] fatal: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$repo->cronFinish($script, $rows, $notes, true);
fwrite(STDOUT, "[$script] ok — generated $rows insights" . PHP_EOL);
