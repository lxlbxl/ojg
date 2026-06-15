<?php
// backend/cron/recover_abandoned_checkouts.php
//
// CRO Audit P1.8 — Checkout abandonment recovery
// Run via cron every 4 hours:
//   0 */4 * * * php /path/to/backend/cron/recover_abandoned_checkouts.php
//
// What it does:
//   1. Finds sessions that started checkout (`checkout_started` or
//      `checkout_initiated`) in the last 6 hours but did NOT purchase.
//   2. Skips sessions we already messaged about this checkout in the last
//      48 hours (re-throttling) OR where the user already received an
//      assessment-recovery message in the last hour (avoid spam).
//   3. Sends a WhatsApp + Email recovery message that references the
//      funnel name, plan tier, and price they almost bought. Includes the
//      30-day guarantee as a friction-killer.
//   4. Logs to `recovery_log` (kind='checkout_abandoned').

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

echo "[" . date('c') . "] Starting checkout-abandonment recovery sweep...\n";

// Ensure recovery_log exists (idempotent)
try {
    $driver = $db->getDriver();
    if ($driver === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS recovery_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(128) NOT NULL,
            funnel VARCHAR(64) NOT NULL,
            kind VARCHAR(32) NOT NULL,
            contact VARCHAR(255) DEFAULT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_kind_sent (kind, sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS recovery_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT NOT NULL,
            funnel TEXT NOT NULL,
            kind TEXT NOT NULL,
            contact TEXT,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_recovery_session ON recovery_log(session_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_recovery_kind_sent ON recovery_log(kind, sent_at)");
    }
} catch (Exception $e) {
    echo "  ✗ Failed to ensure recovery_log: " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Find checkout-started sessions with no purchase (last 6h)
$sql = "
    SELECT t.session_id,
           t.funnel,
           t.event_data,
           MIN(t.created_at) AS started_at,
           MAX(t.created_at) AS last_seen
    FROM funnel_tracking t
    WHERE t.event_name IN ('checkout_started', 'checkout_initiated', 'select_plan_view')
      AND t.created_at >= datetime('now', '-6 hours')
      AND t.session_id NOT IN (
          SELECT DISTINCT session_id
          FROM funnel_tracking
          WHERE event_name IN ('purchase', 'checkout_completed', 'payment_success')
            AND created_at >= datetime('now', '-6 hours')
      )
      AND t.session_id NOT IN (
          SELECT DISTINCT session_id
          FROM recovery_log
          WHERE kind = 'checkout_abandoned'
            AND sent_at >= datetime('now', '-48 hours')
      )
      AND t.session_id NOT IN (
          SELECT DISTINCT session_id
          FROM recovery_log
          WHERE kind = 'assessment_abandoned'
            AND sent_at >= datetime('now', '-1 hours')
      )
    GROUP BY t.session_id, t.funnel
    ORDER BY MIN(t.created_at) ASC
    LIMIT 100
";

try {
    $candidates = $db->fetchAll($sql);
} catch (Exception $e) {
    // MySQL fallback
    $sqlMysql = "
        SELECT t.session_id,
               t.funnel,
               t.event_data,
               MIN(t.created_at) AS started_at,
               MAX(t.created_at) AS last_seen
        FROM funnel_tracking t
        WHERE t.event_name IN ('checkout_started', 'checkout_initiated', 'select_plan_view')
          AND t.created_at >= NOW() - INTERVAL 6 HOUR
          AND t.session_id NOT IN (
              SELECT DISTINCT session_id
              FROM funnel_tracking
              WHERE event_name IN ('purchase', 'checkout_completed', 'payment_success')
                AND created_at >= NOW() - INTERVAL 6 HOUR
          )
          AND t.session_id NOT IN (
              SELECT DISTINCT session_id
              FROM recovery_log
              WHERE kind = 'checkout_abandoned'
                AND sent_at >= NOW() - INTERVAL 48 HOUR
          )
          AND t.session_id NOT IN (
              SELECT DISTINCT session_id
              FROM recovery_log
              WHERE kind = 'assessment_abandoned'
                AND sent_at >= NOW() - INTERVAL 1 HOUR
          )
        GROUP BY t.session_id, t.funnel
        ORDER BY MIN(t.created_at) ASC
        LIMIT 100
    ";
    $candidates = $db->fetchAll($sqlMysql);
}

$count = count($candidates);
echo "  → Found {$count} abandoned checkout(s) in the last 6h\n";

if ($count === 0) {
    echo "Done. No work.\n";
    exit(0);
}

$sent = 0;
$failed = 0;
$n8nWebhook = getenv('N8N_RECOVERY_WEBHOOK') ?: null;

foreach ($candidates as $row) {
    $sessionId = $row['session_id'];
    $funnel = $row['funnel'] ?: 'unknown';
    $eventData = json_decode($row['event_data'] ?? '{}', true) ?: [];
    $contact = $eventData['email'] ?? $eventData['phone'] ?? null;
    $firstName = $eventData['first_name'] ?? $eventData['name'] ?? 'there';
    $planTitle = $eventData['plan'] ?? $eventData['title'] ?? 'your 90-day transformation protocol';
    $price = $eventData['price'] ?? $eventData['amount'] ?? null;

    $priceLine = $price ? " (₦" . number_format((float) $price) . ")" : '';

    // High-converting abandonment copy: reminder + value reminder + 30-day guarantee + urgency
    $message = "Hi {$firstName} 👋\n\n"
        . "Your {$planTitle}{$priceLine} is still waiting for you.\n\n"
        . "Just a reminder:\n"
        . "✅ 30-day money-back guarantee (no questions)\n"
        . "✅ 256-bit SSL secure checkout\n"
        . "✅ Instant portal access\n\n"
        . "👉 Complete your order: https://ojgherbal.com/{$funnel}/select-plan.html?resume=1\n\n"
        . "Reply to this message if you have any questions — we're here to help.";

    $ok = false;
    try {
        if ($n8nWebhook) {
            $ch = curl_init($n8nWebhook);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'kind' => 'checkout_abandoned',
                    'funnel' => $funnel,
                    'session_id' => $sessionId,
                    'contact' => $contact,
                    'plan' => $planTitle,
                    'price' => $price,
                    'message' => $message,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = ($code >= 200 && $code < 300);
        } else {
            error_log("[recovery] checkout_abandoned session={$sessionId} contact={$contact} plan={$planTitle} price={$price}");
            $ok = true;
        }
    } catch (Exception $e) {
        $ok = false;
        error_log("[recovery] checkout send failed: " . $e->getMessage());
    }

    if ($ok) {
        try {
            $db->insert('recovery_log', [
                'session_id' => $sessionId,
                'funnel' => $funnel,
                'kind' => 'checkout_abandoned',
                'contact' => $contact,
            ]);
        } catch (Exception $e) { /* best effort */
        }
        $sent++;
    } else {
        $failed++;
    }
}

echo "  → Sent: {$sent} | Failed: {$failed}\n";
echo "Done.\n";
