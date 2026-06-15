<?php
// session_start(); // Handled by config.php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../../database/SqliteDB.php';
require_once __DIR__ . '/../../classes/ExperimentRepository.php';
require_once __DIR__ . '/../../classes/Bandit.php';

// Helper function for dashboard conversion data
function processConversionData($rawData, $days)
{
    $labels = [];
    $funnels = ['pcos', 'acne', 'weight', 'egbon'];
    $datasets = [];
    $colors = [
        'pcos' => ['assessments' => 'rgba(255, 99, 132, 0.5)', 'sales' => 'rgba(255, 99, 132, 1)'],
        'acne' => ['assessments' => 'rgba(54, 162, 235, 0.5)', 'sales' => 'rgba(54, 162, 235, 1)'],
        'weight' => ['assessments' => 'rgba(255, 206, 86, 0.5)', 'sales' => 'rgba(255, 206, 86, 1)'],
        'egbon' => ['assessments' => 'rgba(75, 192, 192, 0.5)', 'sales' => 'rgba(75, 192, 192, 1)'],
    ];

    foreach ($funnels as $funnel) {
        $datasets[$funnel . '_assessments'] = [
            'label' => ucfirst($funnel) . ' Assessments',
            'data' => array_fill(0, $days, 0),
            'backgroundColor' => $colors[$funnel]['assessments'],
            'stack' => 'assessments',
        ];
        $datasets[$funnel . '_sales'] = [
            'label' => ucfirst($funnel) . ' Sales',
            'data' => array_fill(0, $days, 0),
            'backgroundColor' => $colors[$funnel]['sales'],
            'stack' => 'sales',
        ];
    }

    $dateMap = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('M d', strtotime($date));
        $dateMap[$date] = $days - 1 - $i;
    }

    if (isset($rawData['assessments'])) {
        foreach ($rawData['assessments'] as $date => $entries) {
            if (isset($dateMap[$date])) {
                $index = $dateMap[$date];
                foreach ($entries as $entry) {
                    $funnel = $entry['assessment_type'];
                    if (isset($datasets[$funnel . '_assessments'])) {
                        $datasets[$funnel . '_assessments']['data'][$index] += (int) $entry['count'];
                    }
                }
            }
        }
    }

    if (isset($rawData['sales'])) {
        foreach ($rawData['sales'] as $date => $entries) {
            if (isset($dateMap[$date])) {
                $index = $dateMap[$date];
                foreach ($entries as $entry) {
                    $funnel = $entry['assessment_type'];
                    if (isset($datasets[$funnel . '_sales'])) {
                        $datasets[$funnel . '_sales']['data'][$index] += (int) $entry['count'];
                    }
                }
            }
        }
    }

    return ['labels' => $labels, 'datasets' => array_values($datasets)];
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = new SqliteDB();

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            $days = 7;
            $stats = $db->getDashboardStats();
            $rawConversionData = $db->getDailyConversionData($days);
            $dailyConversion = processConversionData($rawConversionData, $days);
            $recentActivities = $db->getAdminActivityLogs(10);

            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'chart_labels' => $dailyConversion['labels'],
                'daily_conversion' => $dailyConversion['datasets'],
                'recent_activity' => $recentActivities,
                'system' => [
                    'storage' => 'Database',
                    'status' => 'Online'
                ]
            ]);
            break;

        case 'users':
            $params = [
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];
            $users = $db->getUsers($params);
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'audit':
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
            $logs = $db->getAdminActivityLogs($limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;

        case 'user_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $user = $db->getUserById($id);
            if (!$user)
                throw new Exception('User not found');
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'update_user':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';

            if (!$id || !$name || !$email)
                throw new Exception('Missing required fields');

            $db->updateUser($id, ['name' => $name, 'email' => $email, 'phone' => $phone, 'user_type' => $_POST['user_type']]);
            $db->logAdminActivity($_SESSION['admin_id'], 'update_user', "Updated user ID: $id");
            echo json_encode(['success' => true, 'message' => 'User updated']);
            break;

        case 'delete_user':
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $db->deleteUser($id);
            $db->logAdminActivity($_SESSION['admin_id'], 'delete_user', "Deleted user ID: $id");
            echo json_encode(['success' => true, 'message' => 'User deleted']);
            break;

        case 'assessments':
            $params = [
                'funnel' => $_GET['funnel'] ?? null,
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];

            $assessments = $db->getAssessments($params);
            $total = $db->getAssessmentCount($params);

            echo json_encode([
                'success' => true,
                'data' => $assessments,
                'total' => $total
            ]);
            break;

        case 'assessment_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $assessment = $db->getAssessmentById($id);
            if (!$assessment)
                throw new Exception('Assessment not found');
            echo json_encode(['success' => true, 'assessment' => $assessment]);
            break;

        case 'update_assessment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                throw new Exception('POST required');
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? null;
            $note = $_POST['note'] ?? null;

            if (!$id)
                throw new Exception('ID required');

            $updates = [];
            if ($status)
                $updates['status'] = $status;
            if ($note)
                $updates['note'] = [
                    'note' => $note,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $_SESSION['admin_username'] ?? 'Admin'
                ];

            if (!empty($updates)) {
                $db->updateAssessment($id, $updates);
                $db->logAdminActivity($_SESSION['admin_id'], 'update_assessment', "Updated assessment ID: $id");
            }
            break;

        case 'tracking':
            $data = $db->getTrackingData();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'delete_assessment':
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $db->deleteAssessment($id);
            $db->logAdminActivity($_SESSION['admin_id'], 'delete_assessment', "Deleted assessment ID: $id");
            echo json_encode(['success' => true, 'message' => 'Assessment deleted']);
            break;

        case 'sales':
            $params = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100
            ];

            $sales = $db->getSales($params);
            $total = $db->getSalesCount($params);

            echo json_encode([
                'success' => true,
                'data' => $sales,
                'total' => $total
            ]);
            break;

        case 'sale_details':
            $id = $_GET['id'] ?? '';
            if (!$id)
                throw new Exception('ID required');
            $sale = $db->getSaleById($id);
            if (!$sale)
                throw new Exception('Sale not found');
            echo json_encode(['success' => true, 'sale' => $sale]);
            break;

        case 'update_sale':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? null;
            $note = $_POST['note'] ?? null;

            if (!$id)
                throw new Exception('ID required');

            if ($status) {
                $db->updateSale($id, ['payment_status' => $status]);
                $db->logAdminActivity($_SESSION['admin_id'], 'update_sale_status', "Updated sale ID: $id to status: $status");
            }

            if ($note) {
                $db->addSaleNote($id, $note, $_SESSION['admin_username']);
                $db->logAdminActivity($_SESSION['admin_id'], 'add_sale_note', "Added note to sale ID: $id");
            }

            echo json_encode(['success' => true, 'message' => 'Sale updated']);
            break;

        case 'tracking_logs':
            $funnel = $_GET['funnel'] ?? null;
            $event = $_GET['event'] ?? null;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
            $logs = $db->getTrackingLogs($funnel, $event, $limit);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;

        /* =========================================================
         *  A/B TESTING ENGINE  (Experiments + Bandits)
         *  Action root: 'experiments'
         *  Sub-actions: list, create, stop, summary, insights, events, force_decide
         * ========================================================= */
        case 'experiments':
            // Reflect the SqliteDB connection into the new repository
            // Use the public getPdo() accessor (added for the A/B engine).
            $pdo = $db->getPdo();
            $repo = new ExperimentRepository($pdo);
            $bandit = new Bandit($pdo);
            $sub = $_GET['sub'] ?? $_POST['sub'] ?? 'list';

            if ($sub === 'list') {
                $funnel = $_GET['funnel'] ?? null;
                $status = $_GET['status'] ?? null;
                $rows = $repo->listExperiments($funnel, $status);
                // Annotate each row with variant counts + decision preview
                foreach ($rows as &$row) {
                    $row['variants'] = $repo->listVariants($row['id']);
                    $decision = $bandit->decide($row, $row['variants']);
                    $row['decision_ready'] = $decision !== null;
                    $row['decision'] = $decision;
                }
                echo json_encode(['success' => true, 'data' => $rows]);
                break;
            }

            if ($sub === 'get') {
                $id = (int) ($_GET['id'] ?? 0);
                if (!$id)
                    throw new Exception('id required');
                $exp = $repo->getExperiment($id);
                if (!$exp)
                    throw new Exception('Experiment not found');
                $exp['variants'] = $repo->listVariants($id);
                $exp['decision'] = $bandit->decide($exp, $exp['variants']);
                $exp['summary'] = $repo->summaryForExperiment($id);
                $exp['recent_events'] = $repo->recentEvents($id, 50);
                $exp['insights'] = $repo->listInsights($id, null, 10);
                echo json_encode(['success' => true, 'data' => $exp]);
                break;
            }

            if ($sub === 'create') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                    throw new Exception('POST required');
                $payload = $_POST;
                // Decode variants JSON if present
                $variants = [];
                if (!empty($payload['variants_json'])) {
                    $variants = json_decode($payload['variants_json'], true) ?: [];
                }
                unset($payload['variants_json'], $payload['sub']);

                $expId = $repo->createExperiment($payload);
                foreach ($variants as $v) {
                    $v['experiment_id'] = $expId;
                    if (isset($v['overrides']) && is_array($v['overrides'])) {
                        $v['overrides'] = json_encode($v['overrides']);
                    }
                    $v['alpha'] = $v['alpha'] ?? 1;
                    $v['beta'] = $v['beta'] ?? 1;
                    $v['status'] = $v['status'] ?? 'active';
                    $v['source'] = $v['source'] ?? 'human';
                    $repo->addVariant($v);
                }
                $db->logAdminActivity($_SESSION['admin_id'], 'create_experiment', "Created experiment ID: $expId");
                echo json_encode(['success' => true, 'id' => $expId]);
                break;
            }

            if ($sub === 'stop') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                    throw new Exception('POST required');
                $id = (int) ($_POST['id'] ?? 0);
                $winner = $_POST['winner_variant_id'] ?? null;
                if (!$id)
                    throw new Exception('id required');
                $repo->updateExperimentStatus($id, 'concluded', $winner ?: null);
                $db->logAdminActivity($_SESSION['admin_id'], 'stop_experiment', "Concluded experiment ID: $id");
                echo json_encode(['success' => true]);
                break;
            }

            if ($sub === 'force_decide') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST')
                    throw new Exception('POST required');
                $id = (int) ($_POST['id'] ?? 0);
                if (!$id)
                    throw new Exception('id required');
                $exp = $repo->getExperiment($id);
                $variants = $repo->listVariants($id);
                $decision = $bandit->decide($exp, $variants);
                echo json_encode(['success' => true, 'decision' => $decision]);
                break;
            }

            if ($sub === 'insights') {
                $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
                $funnel = $_GET['funnel'] ?? null;
                $insights = $repo->listInsights($id, $funnel, 50);
                echo json_encode(['success' => true, 'data' => $insights]);
                break;
            }

            if ($sub === 'events') {
                $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
                $events = $repo->recentEvents($id, 100);
                echo json_encode(['success' => true, 'data' => $events]);
                break;
            }

            if ($sub === 'summary') {
                $rows = $repo->listExperiments(null, null);
                $active = 0;
                $decided = 0;
                foreach ($rows as $r) {
                    if (($r['status'] ?? '') === 'active') {
                        $active++;
                    }
                    if (!empty($r['decision_variant_id']) || !empty($r['concluded_at'])) {
                        $decided++;
                    }
                }
                $stmt = $pdo->query('SELECT COUNT(*) AS c FROM experiment_assignments');
                $assignments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM experiment_events WHERE event_name = 'purchase'");
                $stmt->execute();
                $conversions = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'active' => $active,
                        'decided' => $decided,
                        'assignments' => $assignments,
                        'conversions' => $conversions,
                    ],
                ]);
                break;
            }

            if ($sub === 'cron_runs') {
                $stmt = $pdo->prepare("SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT 25");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows]);
                break;
            }

            throw new Exception('Unknown experiments sub-action: ' . $sub);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
