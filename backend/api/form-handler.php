<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

function setSecureCorsHeaders()
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];

    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? ''));
    }
}

setSecureCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function validateCsrfToken($token)
{
    // Allow bypass for local development if defined
    if (defined('APP_ENV') && APP_ENV === 'development') {
        return true;
    }

    if (empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// checkRateLimit function removed in favor of RateLimiter class

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

// Assessment, tracking, and nurture submissions come from unauthenticated public forms
// and have no session — CSRF does not apply to them.
$publicFormTypes = ['assessment', 'tracking', 'nurture_queue'];
$formTypeRaw = $data['form_type'] ?? 'unknown';
$clientToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!in_array($formTypeRaw, $publicFormTypes) && !validateCsrfToken($clientToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
require_once __DIR__ . '/../classes/RateLimiter.php';
$rateLimiter = new RateLimiter();
$limit = defined('API_RATE_LIMIT') ? API_RATE_LIMIT : 60;
if (!$rateLimiter->checkApiRateLimit($clientIp, $limit)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

$formType = sanitizeInput($data['form_type'] ?? 'unknown');
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $db = Database::getInstance();
    switch ($formType) {
        case 'assessment':
            $response = handleAssessment($data, $db);
            break;
        case 'sales':
            $response = handleSales($data, $db);
            break;
        case 'tracking':
            $response = handleTracking($data, $db);
            break;
        case 'nurture_queue':
            $response = handleNurtureQueue($data, $db);
            break;
        default:
            $response = ['success' => false, 'message' => 'Unknown form type'];
    }
} catch (Exception $e) {
    error_log("Form handler error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'An error occurred. Please try again later.'];
}

echo json_encode($response);

function handleAssessment($data, $db)
{
    $requiredFields = ['email', 'assessment_data'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }

    $email = sanitizeInput($data['email']);
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    $assessmentType = sanitizeInput($data['assessment_type'] ?? 'general');
    $allowedTypes = ['general', 'pcos', 'weight', 'acne', 'mens', 'egbon'];
    if (!in_array($assessmentType, $allowedTypes)) {
        $assessmentType = 'general';
    }

    $name = sanitizeInput($data['name'] ?? explode('@', $email)[0]);
    $phone = sanitizeInput($data['phone'] ?? '');

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

    if (!$user) {
        // Create user
        $userId = $db->insert('users', [
            'first_name' => $name,
            'name' => $name,  // Also set name for admin display
            'email' => $email,
            'phone' => $phone,
            'type' => 'lead',
            'condition_type' => $assessmentType,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $userId = $user['id'];
        // Update condition_type if not set
        if (empty($user['condition_type'])) {
            $db->update('users', ['condition_type' => $assessmentType], 'id = :id', [':id' => $userId]);
        }
    }

    // Parse assessment data for score extraction
    $assessmentDataParsed = is_string($data['assessment_data'])
        ? json_decode($data['assessment_data'], true)
        : $data['assessment_data'];

    // Extract score if available (from pcosType.scores or direct score)
    $score = null;
    if (isset($assessmentDataParsed['pcosType']['scores'])) {
        $score = array_sum($assessmentDataParsed['pcosType']['scores']);
    } elseif (isset($assessmentDataParsed['score'])) {
        $score = $assessmentDataParsed['score'];
    }

    // Create assessment with all required fields
    $assessmentId = uniqid('assess_');
    $assessmentData = is_string($data['assessment_data']) ? $data['assessment_data'] : json_encode($data['assessment_data']);

    // Extract pcos_type: prefer explicit top-level field, fall back to embedded pcosType in assessment_data
    $validPcosTypes = ['Insulin Resistant', 'Adrenal', 'Inflammatory', 'Post-Pill'];
    $pcosType = sanitizeInput($data['pcos_type'] ?? '');
    if (!in_array($pcosType, $validPcosTypes) && isset($assessmentDataParsed['pcosType']['primary'])) {
        $pcosType = sanitizeInput($assessmentDataParsed['pcosType']['primary']);
    }
    if (!in_array($pcosType, $validPcosTypes)) {
        $pcosType = '';
    }

    $db->insert('assessments', [
        'id' => $assessmentId,
        'user_id' => $userId,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'assessment_type' => $assessmentType,
        'assessment_data' => $assessmentData,
        'score' => $score,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // If a valid PCOS subtype was determined, persist it immediately to member_profiles
    // so the onboarding modal can skip the type-selection step for paying members.
    if ($pcosType && $assessmentType === 'pcos') {
        $profile = $db->fetch("SELECT id FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
        if ($profile) {
            $db->update('member_profiles', [
                'pcos_type' => $pcosType,
                'updated_at' => date('Y-m-d H:i:s')
            ], "user_id = :uid", [':uid' => $userId]);
        }
    }

    return [
        'success' => true,
        'message' => 'Assessment saved successfully',
        'data' => [
            'assessment_id' => $assessmentId,
            'email' => $email,
            'type' => $assessmentType,
            'pcos_type' => $pcosType ?: null
        ]
    ];
}

function handleSales($data, $db)
{
    $requiredFields = ['email', 'amount'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }

    $email = sanitizeInput($data['email']);
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    $amount = floatval($data['amount']);
    if ($amount <= 0 || $amount > 1000000) {
        return ['success' => false, 'message' => 'Invalid amount'];
    }

    $name = sanitizeInput($data['name'] ?? explode('@', $email)[0]);
    $phone = sanitizeInput($data['phone'] ?? '');
    $productType = sanitizeInput($data['product_type'] ?? 'general');
    $productName = sanitizeInput($data['product_name'] ?? $data['product'] ?? ucfirst($productType) . ' Plan');

    // Determine plan duration from product name or explicit field
    $planDuration = intval($data['plan_duration'] ?? 0);
    if ($planDuration <= 0) {
        if (stripos($productName, '90') !== false) {
            $planDuration = 90;
        } elseif (stripos($productName, '30') !== false) {
            $planDuration = 30;
        } else {
            $planDuration = ($amount > 40000) ? 90 : 30;
        }
    }

    $planStartDate = date('Y-m-d H:i:s');
    $planEndDate = date('Y-m-d H:i:s', strtotime("+{$planDuration} days"));

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

    if (!$user) {
        $userId = $db->insert('users', [
            'first_name' => $name,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'type' => 'customer',
            'status' => 'active',
            'condition_type' => $productType,
            'plan_duration' => $planDuration,
            'plan_start_date' => $planStartDate,
            'plan_end_date' => $planEndDate,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $userId = $user['id'];
        // Upgrade lead to customer and activate
        $db->update('users', [
            'type' => 'customer',
            'status' => 'active',
            'plan_duration' => $planDuration,
            'plan_start_date' => $planStartDate,
            'plan_end_date' => $planEndDate
        ], 'id = :id', [':id' => $userId]);
    }

    $saleId = uniqid('sale_');
    $db->insert('sales', [
        'id' => $saleId,
        'user_id' => $userId,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'product_type' => $productType,
        'product_name' => $productName,
        'amount' => $data['amount'],
        'currency' => $data['currency'] ?? 'NGN',
        'payment_status' => $data['payment_status'] ?? 'pending',
        'tx_ref' => $data['tx_ref'] ?? $data['transaction_id'] ?? '',
        'plan_duration' => $planDuration,
        'plan_start_date' => $planStartDate,
        'plan_end_date' => $planEndDate,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return [
        'success' => true,
        'message' => 'Sale recorded successfully',
        'data' => [
            'sale_id' => $saleId,
            'transaction_id' => $data['transaction_id'] ?? '',
            'plan_duration' => $planDuration,
            'plan_end_date' => $planEndDate
        ]
    ];
}

function handleTracking($data, $db)
{
    try {
        $emailInput = $data['email'] ?? null;
        if ($emailInput && !validateEmail($emailInput)) {
            $emailInput = null;
        }

        $allowedFunnels = ['pcos', 'weight', 'acne', 'mens', 'egbon', 'sales', 'nurture'];
        $funnelName = sanitizeInput($data['funnel_name'] ?? 'unknown');
        if (!in_array($funnelName, $allowedFunnels)) {
            $funnelName = 'unknown';
        }

        $allowedEvents = ['view', 'assessment_start', 'assessment_complete', 'results_view', 'plan_select', 'checkout_init', 'purchase', 'click', 'submit', 'exit'];
        $eventType = sanitizeInput($data['event_type'] ?? 'view');
        if (!in_array($eventType, $allowedEvents)) {
            $eventType = 'view';
        }

        $db->insert('funnel_tracking', [
            'session_id' => sanitizeInput($data['session_id'] ?? uniqid('sess_')),
            'user_id' => $data['user_id'] ?? null,
            'email' => $emailInput ? sanitizeInput($emailInput) : null,
            'funnel_name' => $funnelName,
            'step_name' => sanitizeInput($data['step_name'] ?? 'unknown'),
            'event_type' => $eventType,
            'metadata' => is_string($data['metadata'] ?? null) ? $data['metadata'] : json_encode($data['metadata'] ?? []),
            'url' => sanitizeInput($data['url'] ?? ''),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'message' => 'Tracking saved'];
    } catch (Exception $e) {
        error_log("Tracking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Tracking failed'];
    }
}

function handleNurtureQueue($data, $db)
{
    try {
        $email = sanitizeInput($data['email'] ?? '');
        if (empty($email) || !validateEmail($email)) {
            return ['success' => false, 'message' => 'Valid email is required for nurture'];
        }

        $existing = $db->fetch("SELECT id FROM nurture_queue WHERE email = ? AND status = 'pending'", [$email]);
        if ($existing) {
            return ['success' => true, 'message' => 'Already in nurture queue'];
        }

        $customer = $db->fetch("SELECT id FROM users WHERE email = ? AND type = 'customer'", [$email]);
        if ($customer) {
            return ['success' => true, 'message' => 'Already a customer, skipping nurture'];
        }

        $db->insert('nurture_queue', [
            'email' => $email,
            'name' => sanitizeInput($data['name'] ?? ''),
            'phone' => sanitizeInput($data['phone'] ?? ''),
            'pcos_type' => sanitizeInput($data['pcos_type'] ?? ''),
            'confidence' => sanitizeInput($data['confidence'] ?? ''),
            'funnel' => sanitizeInput($data['funnel'] ?? 'pcos'),
            'session_id' => sanitizeInput($data['session_id'] ?? ''),
            'assessment_completed_at' => $data['assessment_completed_at'] ?? date('Y-m-d H:i:s'),
            'sales_page_viewed_at' => $data['sales_page_viewed_at'] ?? date('Y-m-d H:i:s'),
            'status' => 'pending',
            'nurture_step' => 0,
            'next_contact_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'message' => 'Added to nurture queue'];
    } catch (Exception $e) {
        error_log("Nurture queue error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Nurture queue failed. Please try again later.'];
    }
}
