<?php
// backend/cron/recover_abandoned_assessments.php
//
// CRO Audit P1.6 — Assessment abandonment recovery
// Run via cron every 6 hours:
//   0 */6 * * * php /path/to/backend/cron/recover_abandoned_assessments.php
//
// What it does:
//   1. Finds sessions that started an assessment (`assessment_started` event)
//      in the last 24 hours but did NOT complete it (no `assessment_completed`,
//      `contact_captured`, `checkout_started`, `checkout_initiated`, or
//      `purchase` event).
//   2. Looks up the latest `assessment_progress` event for each session to
//      find the question they were on, so the recovery message can include
//      a targeted "?resume=1&last_q=N" deep-link (P1.6 sunk-cost flow).
//   3. Throttles: skips sessions we already messaged in the last 7 days.
//   4. Sends a WhatsApp + Email recovery message via the same channels as
//      the rest of the system (n8n webhook / Twilio / Mailgun).
//   5. Logs the recovery attempt to `recovery_log` so we can measure lift.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

echo "[" . date('c') . "] Starting assessment-abandonment recovery sweep...\n";

// 1. Make sure recovery_log exists (idempotent CREATE TABLE IF NOT EXISTS)
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
    echo "  ✓ recovery_log table ready\n";
} catch (Exception $e) {
    echo "  ✗ Failed to ensure recovery_log: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Find candidates: assessment_started within 24h with no completion event.
//    contact_captured is treated as complete because P1.6 sinks the email
//    capture BEFORE the result is shown — a session that gave us their email
//    is no longer abandoned (we have a way to reach them and they are moments
//    away from results.html). This query works for SQLite; the MySQL fallback
//    below uses NOW()/INTERVAL.
$sql = "
    SELECT t.session_id,
           t.funnel,
           t.event_data,
           MIN(t.created_at) AS started_at,
           MAX(t.created_at) AS last_seen
    FROM funnel_tracking t
    WHERE t.event_name = 'assessment_started'
      AND t.created_at >= datetime('now', '-24 hours')
      AND t.session_id NOT IN (
          SELECT DISTINCT session_id
          FROM funnel_tracking
          WHERE event_name IN ('assessment_completed', 'contact_captured', 'checkout_started', 'checkout_initiated', 'purchase')
            AND created_at >= datetime('now', '-24 hours')
      )
      AND t.session_id NOT IN (
          SELECT DISTINCT session_id
          FROM recovery_log
          WHERE kind = 'assessment_abandoned'
            AND sent_at >= datetime('now', '-7 days')
      )
    GROUP BY t.session_id, t.funnel
    ORDER BY MIN(t.created_at) ASC
    LIMIT 100
";

try {
    $candidates = $db->fetchAll($sql);
} catch (Exception $e) {
    // Fallback: MySQL uses NOW() / INTERVAL syntax
    $sqlMysql = "
        SELECT t.session_id,
               t.funnel,
               t.event_data,
               MIN(t.created_at) AS started_at,
               MAX(t.created_at) AS last_seen
        FROM funnel_tracking t
        WHERE t.event_name = 'assessment_started'
          AND t.created_at >= NOW() - INTERVAL 24 HOUR
          AND t.session_id NOT IN (
              SELECT DISTINCT session_id
              FROM funnel_tracking
              WHERE event_name IN ('assessment_completed', 'contact_captured', 'checkout_started', 'checkout_initiated', 'purchase')
                AND created_at >= NOW() - INTERVAL 24 HOUR
          )
          AND t.session_id NOT IN (
              SELECT DISTINCT session_id
              FROM recovery_log
              WHERE kind = 'assessment_abandoned'
                AND sent_at >= NOW() - INTERVAL 7 DAY
          )
        GROUP BY t.session_id, t.funnel
        ORDER BY MIN(t.created_at) ASC
        LIMIT 100
    ";
    $candidates = $db->fetchAll($sqlMysql);
}

$count = count($candidates);
echo "  → Found {$count} abandoned assessment session(s) in the last 24h\n";

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

    // Pick first-name if we have it
    $firstName = $eventData['first_name'] ?? $eventData['name'] ?? 'there';
    $funnelTitle = ucfirst($funnel);

    // P1.6 — Look up the last question the user reached so the deep-link can
    // be targeted ("You're 3 questions away" vs generic "finish the assessment").
    // Reads the latest assessment_progress event's event_data.metadata. Best-effort:
    // falls back to a generic ?resume=1 link if no progress data is available
    // (e.g. user bounced after the very first question, before any heartbeat).
    $lastQuestion = null;
    $totalQuestions = null;
    $sessionIdEsc = str_replace("'", "''", $sessionId);
    try {
        $progRows = $db->fetchAll(
            "SELECT event_data FROM funnel_tracking
             WHERE session_id = '{$sessionIdEsc}'
               AND event_name = 'assessment_progress'
             ORDER BY created_at DESC LIMIT 1"
        );
        if (!empty($progRows)) {
            $progData = json_decode($progRows[0]['event_data'] ?? '{}', true) ?: [];
            $meta = $progData['metadata'] ?? [];
            if (isset($meta['question_index']) && is_numeric($meta['question_index'])) {
                $lastQuestion = (int) $meta['question_index'];
            }
            if (isset($meta['total_questions']) && is_numeric($meta['total_questions'])) {
                $totalQuestions = (int) $meta['total_questions'];
            }
        }
    } catch (Exception $e) {
        // best-effort — fall back to a generic resume link
    }

    // Build the deep-link. ?resume=1 triggers localStorage hydration on the
    // assessment page (P1.6). last_q is for analytics + a future "jump to
    // question" feature on devices without localStorage.
    $resumeUrl = "https://ojgherbal.com/{$funnel}/assessment.html?resume=1";
    if ($lastQuestion !== null) {
        $resumeUrl .= "&last_q={$lastQuestion}";
    }

    // Tailor the progress copy: "You're N questions away" if we know the
    // position, otherwise the original generic copy.
    if ($lastQuestion !== null && $totalQuestions !== null && $totalQuestions > $lastQuestion) {
        $remaining = $totalQuestions - $lastQuestion; // next question to answer
        $progressNote = "You're only {$remaining} question" . ($remaining === 1 ? '' : 's') . " away from your personalized results.";
    } else {
        $progressNote = "Your personalized results are waiting \u{2014} takes just 2 more minutes.";
    }

    // Build the recovery message — short, value-led, single CTA
    $message = "Hi {$firstName} \u{1F44B}\n\n"
        . "We noticed you started your {$funnelTitle} assessment but didn't finish. "
        . "{$progressNote}\n\n"
        . "\u{1F449} Resume here: {$resumeUrl}\n\n"
        . "Questions? Reply to this message and we'll help.";

    $ok = false;
    try {
        // Path 1: n8n webhook (preferred — same channel as the rest of the stack)
        if ($n8nWebhook) {
            $ch = curl_init($n8nWebhook);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'kind' => 'assessment_abandoned',
                    'funnel' => $funnel,
                    'session_id' => $sessionId,
                    'contact' => $contact,
                    'message' => $message,
                    'resume_url' => $resumeUrl,
                    'last_question' => $lastQuestion,
                    'total_questions' => $totalQuestions,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = ($code >= 200 && $code < 300);
        } else {
            // Path 2: log only (dev / staging) — still log to recovery_log
            error_log("[recovery] assessment_abandoned session={$sessionId} contact={$contact} last_q={$lastQuestion} msg=" . str_replace("\n", ' | ', $message));
            $ok = true;
        }
    } catch (Exception $e) {
        $ok = false;
        error_log("[recovery] send failed: " . $e->getMessage());
    }

    if ($ok) {
        try {
            $db->insert('recovery_log', [
                'session_id' => $sessionId,
                'funnel' => $funnel,
                'kind' => 'assessment_abandoned',
                'contact' => $contact,
            ]);
        } catch (Exception $e) {
            // best-effort log
        }
        $sent++;
    } else {
        $failed++;
    }
}

echo "  → Sent: {$sent} | Failed: {$failed}\n";
echo "Done.\n";
