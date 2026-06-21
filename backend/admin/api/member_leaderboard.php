<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $driver = $db->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);

    $intervalExpr = $driver === 'pgsql'
        ? "CURRENT_DATE - INTERVAL '30 days'"
        : "DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

    // Leaderboard: members ranked by 30-day compliance rate
    $leaderboard = $db->fetchAll("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            mp.subscription_status,
            mp.pcos_type,
            mp.subscription_expiry,
            COUNT(al.id) AS total_activities,
            SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN al.status = 'missed'    THEN 1 ELSE 0 END) AS missed,
            COUNT(DISTINCT al.plan_date) AS days_active,
            MAX(al.plan_date) AS last_active_date,
            ROUND(
                CASE WHEN COUNT(al.id) > 0
                THEN 100.0 * SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END) / COUNT(al.id)
                ELSE 0 END, 1
            ) AS compliance_rate
        FROM users u
        INNER JOIN member_profiles mp ON mp.user_id = u.id
        LEFT JOIN activity_logs al
            ON al.user_id = u.id
            AND al.plan_date >= {$intervalExpr}
        WHERE u.type = 'customer'
        GROUP BY u.id, u.first_name, u.last_name, u.email,
                 mp.subscription_status, mp.pcos_type, mp.subscription_expiry
        ORDER BY compliance_rate DESC, completed DESC
        LIMIT 100
    ");

    // Summary stats for the last 30 days
    $summary = $db->fetch("
        SELECT
            COUNT(DISTINCT al.user_id)                                          AS active_members,
            COUNT(al.id)                                                        AS total_logs,
            SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END)           AS total_completed,
            SUM(CASE WHEN al.status = 'missed'    THEN 1 ELSE 0 END)           AS total_missed,
            ROUND(
                CASE WHEN COUNT(al.id) > 0
                THEN 100.0 * SUM(CASE WHEN al.status = 'completed' THEN 1 ELSE 0 END) / COUNT(al.id)
                ELSE 0 END, 1
            )                                                                   AS overall_compliance
        FROM activity_logs al
        WHERE al.plan_date >= {$intervalExpr}
    ");

    echo json_encode([
        'success'     => true,
        'leaderboard' => $leaderboard,
        'summary'     => $summary
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
