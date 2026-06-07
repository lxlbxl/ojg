<?php
/**
 * Detect Currency from IP Address
 * Returns visitor's country code and recommended currency for Flutterwave
 *
 * Endpoint: GET /backend/api/detect-currency.php
 * Response: { "country": "NG", "currency": "NGN", "currency_symbol": "₦", "currency_name": "Nigerian Naira" }
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins) || empty($allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─────────────────────────────────────────────
// Country → Currency mapping
// ─────────────────────────────────────────────
$COUNTRY_CURRENCY = [
    // Africa
    'NG' => ['currency' => 'NGN', 'symbol' => '₦',   'name' => 'Nigerian Naira'],
    'GH' => ['currency' => 'GHS', 'symbol' => 'GH₵', 'name' => 'Ghanaian Cedi'],
    'KE' => ['currency' => 'KES', 'symbol' => 'KSh', 'name' => 'Kenyan Shilling'],
    'ZA' => ['currency' => 'ZAR', 'symbol' => 'R',   'name' => 'South African Rand'],
    'TZ' => ['currency' => 'TZS', 'symbol' => 'TSh', 'name' => 'Tanzanian Shilling'],
    'UG' => ['currency' => 'UGX', 'symbol' => 'USh', 'name' => 'Ugandan Shilling'],
    'RW' => ['currency' => 'RWF', 'symbol' => 'RF',  'name' => 'Rwandan Franc'],
    'ZM' => ['currency' => 'ZMW', 'symbol' => 'ZK',  'name' => 'Zambian Kwacha'],
    'ET' => ['currency' => 'ETB', 'symbol' => 'Br',  'name' => 'Ethiopian Birr'],
    'EG' => ['currency' => 'EGP', 'symbol' => 'E£',  'name' => 'Egyptian Pound'],
    'MA' => ['currency' => 'MAD', 'symbol' => 'MAD', 'name' => 'Moroccan Dirham'],
    'SL' => ['currency' => 'SLL', 'symbol' => 'Le',  'name' => 'Sierra Leonean Leone'],
    'MW' => ['currency' => 'MWK', 'symbol' => 'MK',  'name' => 'Malawian Kwacha'],
    // CFA Countries
    'CM' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'CF' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'TD' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'CG' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'GQ' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'GA' => ['currency' => 'XAF', 'symbol' => 'FCFA','name' => 'Central African Franc'],
    'BJ' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'BF' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'CI' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'GW' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'ML' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'NE' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'SN' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    'TG' => ['currency' => 'XOF', 'symbol' => 'CFA', 'name' => 'West African Franc'],
    // Europe
    'GB' => ['currency' => 'GBP', 'symbol' => '£',   'name' => 'British Pound'],
    'DE' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'FR' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'IT' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'ES' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'NL' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'BE' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'AT' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'PT' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'IE' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'FI' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    'GR' => ['currency' => 'EUR', 'symbol' => '€',   'name' => 'Euro'],
    // Americas
    'US' => ['currency' => 'USD', 'symbol' => '$',   'name' => 'US Dollar'],
    'CA' => ['currency' => 'CAD', 'symbol' => 'CA$', 'name' => 'Canadian Dollar'],
    // Oceania
    'AU' => ['currency' => 'AUD', 'symbol' => 'A$',  'name' => 'Australian Dollar'],
    'NZ' => ['currency' => 'AUD', 'symbol' => 'A$',  'name' => 'Australian Dollar'],
];

/**
 * Get real visitor IP address (handles proxies/Cloudflare)
 */
function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    // Return REMOTE_ADDR even if private (for localhost dev)
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Look up country from IP using multiple methods.
 */
function detectCountry(string $ip): string {
    // 1. Try PHP geoip extension if available
    if (function_exists('geoip_country_code_by_name')) {
        $country = @geoip_country_code_by_name($ip);
        if ($country) return $country;
    }

    // 2. Try Cloudflare header (when behind CF)
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        return strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
    }

    // 3. Try ip-api.com (free, 45 req/min)
    $url = "http://ip-api.com/json/{$ip}?fields=countryCode";
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if (!empty($data['countryCode'])) {
            return strtoupper($data['countryCode']);
        }
    }

    // 4. Fallback to US
    return 'US';
}

// ─────────────────────────────────────────────
// Main Logic
// ─────────────────────────────────────────────
try {
    $ip = getClientIP();
    
    // Allow override for testing: ?ip=x.x.x.x
    if (!empty($_GET['ip']) && filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
        $ip = $_GET['ip'];
    }

    $country = detectCountry($ip);
    $currencyInfo = $COUNTRY_CURRENCY[$country] ?? ['currency' => 'USD', 'symbol' => '$', 'name' => 'US Dollar'];

    echo json_encode([
        'success'         => true,
        'ip'              => $ip,
        'country'         => $country,
        'currency'        => $currencyInfo['currency'],
        'currency_symbol' => $currencyInfo['symbol'],
        'currency_name'   => $currencyInfo['name'],
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success'         => false,
        'country'         => 'US',
        'currency'        => 'USD',
        'currency_symbol' => '$',
        'currency_name'   => 'US Dollar',
        'error'           => $e->getMessage(),
    ]);
}
