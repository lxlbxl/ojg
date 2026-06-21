<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // ── Self-healing: ensure tables exist ─────────────────────────────────────
    if ($driver === 'pgsql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ab_experiments (
                id          SERIAL PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                funnel      VARCHAR(50),
                metric      VARCHAR(50)  DEFAULT 'conversion',
                description TEXT,
                status      VARCHAR(20)  DEFAULT 'draft',
                started_at  TIMESTAMP,
                stopped_at  TIMESTAMP,
                created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS ab_variants (
                id              SERIAL PRIMARY KEY,
                experiment_id   INTEGER NOT NULL REFERENCES ab_experiments(id) ON DELETE CASCADE,
                name            VARCHAR(100) NOT NULL,
                weight          DECIMAL(5,2) DEFAULT 50.0,
                impressions     INTEGER      DEFAULT 0,
                conversions     INTEGER      DEFAULT 0,
                created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ab_experiments (
                id          INTEGER PRIMARY KEY AUTO_INCREMENT,
                name        VARCHAR(255) NOT NULL,
                funnel      VARCHAR(50),
                metric      VARCHAR(50)  DEFAULT 'conversion',
                description TEXT,
                status      VARCHAR(20)  DEFAULT 'draft',
                started_at  DATETIME,
                stopped_at  DATETIME,
                created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS ab_variants (
                id              INTEGER PRIMARY KEY AUTO_INCREMENT,
                experiment_id   INTEGER NOT NULL,
                name            VARCHAR(100) NOT NULL,
                weight          DECIMAL(5,2) DEFAULT 50.0,
                impressions     INTEGER      DEFAULT 0,
                conversions     INTEGER      DEFAULT 0,
                created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (experiment_id) REFERENCES ab_experiments(id) ON DELETE CASCADE
            );
        ");
    }

    $sub = $_GET['sub'] ?? $_POST['sub'] ?? 'list';

    // ── LIST ─────────────────────────────────────────────────────────────────
    if ($sub === 'list') {
        $funnel = $_GET['funnel'] ?? null;
        $status = $_GET['status'] ?? null;

        $where = [];
        $params = [];
        if ($funnel) { $where[] = 'funnel = :funnel'; $params[':funnel'] = $funnel; }
        if ($status) { $where[] = 'status = :status'; $params[':status'] = $status; }
        $sql = 'SELECT * FROM ab_experiments' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $experiments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach variants and compute winner
        foreach ($experiments as &$exp) {
            $vs = $pdo->prepare('SELECT * FROM ab_variants WHERE experiment_id = :id ORDER BY conversions DESC');
            $vs->execute([':id' => $exp['id']]);
            $variants = $vs->fetchAll(PDO::FETCH_ASSOC);
            $exp['variants'] = $variants;

            // Simple winner: variant with highest conversion rate (min 10 impressions)
            $exp['decision'] = null;
            $best = null; $bestRate = -1;
            foreach ($variants as $v) {
                if ($v['impressions'] >= 10) {
                    $rate = $v['conversions'] / $v['impressions'];
                    if ($rate > $bestRate) { $bestRate = $rate; $best = $v; }
                }
            }
            if ($best) {
                $exp['decision'] = ['variant_id' => $best['id'], 'variant_name' => $best['name'], 'rate' => round($bestRate * 100, 1)];
            }
        }

        echo json_encode(['success' => true, 'data' => $experiments]);
        exit;
    }

    // ── CREATE ────────────────────────────────────────────────────────────────
    if ($sub === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $funnel = trim($_POST['funnel'] ?? 'pcos');
        $metric = trim($_POST['metric'] ?? 'conversion');
        $desc   = trim($_POST['description'] ?? '');
        if (!$name) { echo json_encode(['success' => false, 'error' => 'Name is required']); exit; }

        $stmt = $pdo->prepare("
            INSERT INTO ab_experiments (name, funnel, metric, description, status, started_at)
            VALUES (:name, :funnel, :metric, :desc, 'running', NOW())
        ");
        $stmt->execute([':name' => $name, ':funnel' => $funnel, ':metric' => $metric, ':desc' => $desc]);
        $expId = $pdo->lastInsertId();

        // Create two default variants
        $vs = $pdo->prepare("INSERT INTO ab_variants (experiment_id, name, weight) VALUES (:eid, :name, 50)");
        $vs->execute([':eid' => $expId, ':name' => 'Control']);
        $vs->execute([':eid' => $expId, ':name' => 'Variant A']);

        echo json_encode(['success' => true, 'id' => $expId]);
        exit;
    }

    // ── STOP ─────────────────────────────────────────────────────────────────
    if ($sub === 'stop') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
        $stmt = $pdo->prepare("UPDATE ab_experiments SET status = 'completed', stopped_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    if ($sub === 'update') {
        $id   = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || !$name) { echo json_encode(['success' => false, 'error' => 'Missing fields']); exit; }
        $stmt = $pdo->prepare("UPDATE ab_experiments SET name=:name, funnel=:funnel, metric=:metric, description=:desc, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':name' => $name, ':funnel' => $_POST['funnel'] ?? 'pcos', ':metric' => $_POST['metric'] ?? 'conversion', ':desc' => $_POST['description'] ?? '', ':id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => "Unknown sub-action: $sub"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
