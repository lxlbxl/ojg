<?php
/**
 * OJG front controller.
 *
 * The root .htaccess falls back to this file when mod_rewrite cannot statically
 * resolve a request. Two responsibilities:
 *
 *   1. Resolve structural variant URLs (e.g. /pcos__b/ -> /pcos/?variant=b)
 *      so a single set of funnels can be served from sibling directories
 *      without duplicating assets.
 *
 *   2. Emit a server-side `view` event for every funnel HTML page hit,
 *      carrying the current experiment/variant assignment from the
 *      `ojg_exp` cookie. This guarantees exposure counts are recorded even
 *      when the JS applier is blocked or fails to load.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/ExperimentRepository.php';
require_once __DIR__ . '/classes/AssignmentService.php';
require_once __DIR__ . '/classes/ExperimentTracker.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = '/' . ltrim($uri, '/');

// Strip query string noise; we only care about the path here.
$path = $uri;

// ---------------------------------------------------------------------------
// Structural variant detection: /pcos__b/index.html -> /pcos/index.html?variant=b
// ---------------------------------------------------------------------------
$variant = null;
$funnel = null;

if (preg_match('#^/([a-z0-9]+)__([a-z0-9]+)(/.*)?$#i', $path, $m)) {
    $funnel = strtolower($m[1]);
    $variant = strtolower($m[2]);
    // Rewrite the path to the canonical funnel path.
    $newPath = '/' . $funnel . ($m[3] ?? '/index.html');
    $variantQuery = '?variant=' . rawurlencode($variant);

    // Internal redirect by setting the request URI and re-including.
    $_SERVER['REQUEST_URI'] = $newPath . $variantQuery;
    $_GET['variant'] = $variant;
    $path = $newPath;

    // If the actual file exists in the sibling dir, let Apache serve it.
    $sibling = __DIR__ . $path;
    if (is_file($sibling)) {
        // Set variant cookie hint for the JS applier.
        if (!isset($_COOKIE['ojg_exp_variant_hint'])) {
            setcookie('ojg_exp_variant_hint', $variant, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'samesite' => 'Lax',
            ]);
        }
        return false; // tell Apache to serve the file
    }
}

// ---------------------------------------------------------------------------
// Map path -> (funnel, stage) for view tracking.
// ---------------------------------------------------------------------------
$trackingContext = null;
if (preg_match('#^/([a-z0-9]+)/(.*)$#i', $path, $m)) {
    $candidate = strtolower($m[1]);
    $allowed = ['pcos', 'acne', 'weight', 'mens', 'egbon'];
    if (in_array($candidate, $allowed, true)) {
        $rest = strtolower($m[2]);
        $stage = 'landing';
        if (str_contains($rest, 'assessment'))
            $stage = 'assessment';
        elseif (str_contains($rest, 'result'))
            $stage = 'results';
        elseif (str_contains($rest, 'select-plan') || str_contains($rest, 'select_plan'))
            $stage = 'select_plan';
        elseif (str_contains($rest, 'checkout') || str_contains($rest, 'digital-plan'))
            $stage = 'checkout';
        elseif (str_contains($rest, 'thank'))
            $stage = 'thank_you';

        $trackingContext = [
            'funnel' => $candidate,
            'stage' => $stage,
            'path' => $path,
        ];
    }
}

// ---------------------------------------------------------------------------
// Emit server-side `view` event. Fire-and-forget; never block the page.
// ---------------------------------------------------------------------------
if ($trackingContext !== null) {
    $sessionId = $_COOKIE['ojg_sid']
        ?? $_COOKIE['ojg_session']
        ?? '';

    if ($sessionId === '') {
        $sessionId = AssignmentService::newSessionId();
        setcookie('ojg_sid', $sessionId, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
    }

    try {
        $tracker = new ExperimentTracker();
        $tracker->track([
            'event_type' => 'view',
            'funnel' => $trackingContext['funnel'],
            'stage' => $trackingContext['stage'],
            'session_id' => $sessionId,
            'metadata' => [
                'source' => 'router.php',
                'path' => $trackingContext['path'],
                'variant' => $variant,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[router] tracker error: ' . $e->getMessage());
    }
}

// Fall through: let Apache continue serving static files normally.
return true;
