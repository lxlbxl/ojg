<?php
/**
 * PHP built-in server router for OJG.
 * php -S 0.0.0.0:8091 -t /root/deployments/ojg /root/deployments/ojg/serve.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = '/' . ltrim($uri, '/');
$root = __DIR__;

// Helper: require a PHP file with its directory as CWD so relative include paths work
function safe_require(string $file): void {
    $dir = dirname($file);
    $cwd = getcwd();
    chdir($dir);
    try {
        require $file;
    } finally {
        chdir($cwd);
    }
}

// Root serves index.html if present, otherwise redirect to pcos
if ($uri === '/') {
    if (is_file($root . '/index.html')) {
        return false; // let built-in server serve index.html
    }
    header('Location: /pcos/', true, 302);
    exit;
}

// Serve existing files directly
$file = $root . $uri;
if ($uri !== '/' && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        safe_require($file);
        return true;
    }
    return false; // let built-in server serve static files
}

// Run the router for experiment tracking on funnel pages
if (preg_match('#^/([a-z0-9]+)__([a-z0-9]+)(/.*)?$#i', $uri, $m)) {
    $funnel = strtolower($m[1]);
    $variant = strtolower($m[2]);
    $sub = $m[3] ?? '/index.html';
    $target = "/{$funnel}{$sub}";
    
    if (!isset($_COOKIE['ojg_exp_variant_hint'])) {
        setcookie('ojg_exp_variant_hint', $variant, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
    }
    
    $targetFile = $root . $target;
    if (is_file($targetFile) && !str_ends_with($targetFile, '.php')) {
        return false; // serve static file
    }
}

// Admin SPA
if ($uri === '/admin' || str_starts_with($uri, '/admin/')) {
    safe_require($root . '/backend/admin/index.php');
    return true;
}

// API routes
if (str_starts_with($uri, '/backend/api/')) {
    $apiPath = $root . $uri;
    if (is_file($apiPath)) {
        safe_require($apiPath);
        return true;
    }
    if (is_file($apiPath . '.php')) {
        safe_require($apiPath . '.php');
        return true;
    }
}

// Backend routes (non-API)
if (str_starts_with($uri, '/backend/')) {
    $backendPath = $root . $uri;
    if (is_file($backendPath) && str_ends_with($backendPath, '.php')) {
        safe_require($backendPath);
        return true;
    }
    if (is_file($backendPath . '.php')) {
        safe_require($backendPath . '.php');
        return true;
    }
    safe_require($root . '/backend/index.php');
    return true;
}

// Default: funnel pages
$funnels = ['pcos', 'acne', 'weight', 'mens', 'egbon'];
foreach ($funnels as $funnel) {
    if (str_starts_with($uri, '/' . $funnel . '/')) {
        $funnelFile = $root . $uri;
        if (is_file($funnelFile) && !str_ends_with($funnelFile, '.php')) {
            return false; // serve static file
        }
        if (is_file($funnelFile . '.html')) {
            return false;
        }
        if (is_file($funnelFile . 'index.html')) {
            return false;
        }
        // Fallback to funnel index
        $funnelIndex = $root . '/' . $funnel . '/index.html';
        if (is_file($funnelIndex)) {
            return false;
        }
    }
}

// Serve /pcos/ etc as index.html
foreach ($funnels as $funnel) {
    if ($uri === '/' . $funnel || $uri === '/' . $funnel . '/') {
        $indexFile = $root . '/' . $funnel . '/index.html';
        if (is_file($indexFile)) {
            return false;
        }
    }
}

// Privacy policy etc - static files
if (is_file($root . $uri)) {
    return false;
}

if (is_file($root . $uri . '.html')) {
    return false;
}

// Default to index.html
if (is_file($root . '/index.html')) {
    return false;
}

// 404
return false;
