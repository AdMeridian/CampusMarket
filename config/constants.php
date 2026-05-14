<?php
// ============================================================
// CampusMarket — App-Wide Constants
// ============================================================

// Base URL — Dynamic detection for Local vs Production
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$envBaseUrl = getenv('BASE_URL');

if ($envBaseUrl) {
    $base_url = rtrim($envBaseUrl, '/');
    // Guard against misconfigured BASE_URL values like .../pages or .../api.
    $base_url = preg_replace('#/(pages|admin|actions|api)$#i', '', $base_url) ?: $base_url;
} else {
    $isLocalHost = in_array(strtolower($host), ['localhost', '127.0.0.1'], true)
        || str_starts_with(strtolower($host), 'localhost:')
        || str_starts_with($host, '127.0.0.1:');

    if ($isLocalHost) {
        // Resolve app base path from the current script path.
        // Examples:
        // /CampusMarket/index.php         -> /CampusMarket/
        // /CampusMarket/pages/browse.php  -> /CampusMarket/
        // /CampusMarket/admin/index.php   -> /CampusMarket/
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        if (
            str_contains($scriptName, '/pages/')
            || str_contains($scriptName, '/admin/')
            || str_contains($scriptName, '/actions/')
        ) {
            $appBasePath = dirname(dirname($scriptName));
        } else {
            $appBasePath = dirname($scriptName);
        }

        $appBasePath = '/' . trim(str_replace('\\', '/', $appBasePath), '/');
        if ($appBasePath === '/.') {
            $appBasePath = '';
        }
        $base_url = $protocol . $host . $appBasePath . '/';
    } else {
        $base_url = $protocol . $host . '/';
    }
}

// Ensure trailing slash
if (substr($base_url, -1) !== '/') {
    $base_url .= '/';
}

define('BASE_URL',    $base_url);

// File Paths
define('ROOT_PATH',   __DIR__ . '/../');
define('UPLOAD_PATH', ROOT_PATH . 'public/uploads/');
define('UPLOAD_URL',  BASE_URL  . 'public/uploads/');

// Upload Limits
define('MAX_FILE_SIZE',    5 * 1024 * 1024); // 5 MB
define('ALLOWED_TYPES',    ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('MAX_IMAGES',       5);               // max images per product

// Pagination
define('ITEMS_PER_PAGE',   12);

// App Meta
define('APP_NAME',         'CampusMarket');
define('APP_TAGLINE',      'Buy & Sell Within Your Campus');
define('APP_CURRENCY',     '₺');
define('LISTING_DISCOUNT_MIN_DAYS', 14);
define('LISTING_DISCOUNT_MAX_PERCENT', 50);

// Session name
define('SESSION_NAME',     'campusmarket_session');

// Stripe Settings (Sandbox/Test Mode)
if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY');
}
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY',      'sk_test_YOUR_SECRET_KEY');
}
