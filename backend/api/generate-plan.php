<?php
/**
 * API Endpoint: Generate Condition-Specific Plan PDF
 *
 * POST /api/generate-plan.php
 * Body: { email, name, assessment: { condition, ...conditionFields }, region?: { country_code, ... } }
 * Returns: PDF binary (application/pdf)
 *
 * Routes to the correct generator by assessment.condition:
 *   pcos   → PcosProtocolGenerator   (CycleSync)
 *   acne   → AcneProtocolGenerator   (GlowClear)
 *   weight → WeightProtocolGenerator (LeanFlow)
 *   mens   → MensProtocolGenerator   (Vitale)
 */

require_once __DIR__ . '/../config/config.php';

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Bootstrap
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/RegionProfile.php';
require_once __DIR__ . '/../classes/ProtocolGeneratorFactory.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$name       = $input['name']       ?? 'Friend';
$email      = $input['email']      ?? '';
$assessment = $input['assessment'] ?? [];

if (empty($assessment)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing assessment data']);
    exit;
}

try {
    set_time_limit(180);
    ini_set('memory_limit', '256M');

    // ── 1. Resolve condition ────────────────────────────────────────────────
    // Accept from: assessment.condition, assessment.funnel_name, or top-level input.condition
    $condition = strtolower(trim(
        $assessment['condition']
        ?? $assessment['funnel_name']
        ?? $input['condition']
        ?? 'pcos'          // safe default: existing buyers are PCOS
    ));

    error_log("[generate-plan] condition=$condition name=$name email=$email");

    // ── 2. Resolve region profile (geo layer) ───────────────────────────────
    $regionProfile = [];
    try {
        $regionResolver = new RegionProfile();

        // Priority: explicit region in request → user record → IP geo
        if (!empty($input['region'])) {
            $regionProfile = $regionResolver->resolveFromData($input['region']);
        } elseif (!empty($assessment['country_code']) || !empty($assessment['country'])) {
            $regionProfile = $regionResolver->resolveFromData([
                'country_code' => $assessment['country_code'] ?? null,
                'country'      => $assessment['country'] ?? null,
                'region_city'  => $assessment['region_city'] ?? null,
                'cuisine_pref' => $assessment['cuisinePref'] ?? null,
            ]);
        } else {
            // Fall back to IP geolocation (best-effort, user can override later)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $regionProfile = $regionResolver->resolveFromIp($ip);
        }
    } catch (Exception $e) {
        error_log("[generate-plan] RegionProfile resolution failed (non-fatal): " . $e->getMessage());
        // Proceed without region profile — generators have safe defaults
    }

    // ── 3. Route to correct generator ───────────────────────────────────────
    $generator = ProtocolGeneratorFactory::for($condition);
    $pdfBinary = $generator->generate($assessment, $name, $email, $regionProfile);

    // ── 4. Build filename ───────────────────────────────────────────────────
    $safeName   = preg_replace('/\s+/', '_', $name);
    $labelMap   = [
        'pcos'   => '90Day_CycleSync_PCOS_Protocol',
        'acne'   => '90Day_GlowClear_Acne_Protocol',
        'weight' => '90Day_LeanFlow_Protocol',
        'mens'   => '90Day_Vitale_Mens_Protocol',
    ];
    $planLabel  = $labelMap[$condition] ?? '90Day_Protocol';
    $filename   = "{$safeName}_{$planLabel}.pdf";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;

} catch (Exception $e) {
    error_log('[generate-plan] Error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate plan. Please try again.']);
}
