<?php
// ============================================================
// CampusMarket — Bootstrap (included at the top of every page)
// ============================================================

require_once __DIR__ . '/../config/constants.php';
if (session_status() === PHP_SESSION_NONE) {
    // Security: harden session cookies
    ini_set('session.cookie_httponly', 1);
    $isSecureRequest = (
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (isset($isSecure) && $isSecure)
    );
    ini_set('session.cookie_secure', $isSecureRequest ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    ini_set('session.use_strict_mode', 1);

    session_name(SESSION_NAME);
    session_start();
}

// CSRF token — double-submit cookie pattern (stateless, works on Vercel serverless).
// The browser sends the cookie back on every POST, so no server-side session is needed.
if (empty($_COOKIE['csrf_token'])) {
    $csrfToken = bin2hex(random_bytes(32));
    setcookie('csrf_token', $csrfToken, [
        'expires'  => time() + 7200,
        'path'     => '/',
        'secure'   => $isSecureRequest,
        'samesite' => 'Lax',
        'httponly' => false, // Must be JS-readable for AJAX X-CSRF-Token header
    ]);
    $_COOKIE['csrf_token'] = $csrfToken; // Available for this request's PHP code
} else {
    $csrfToken = $_COOKIE['csrf_token'];
}
// Mirror into session for pages/environments that still rely on it
$_SESSION['csrf_token'] = $csrfToken;

// Simple .env parser for local development
$envFile = ROOT_PATH . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
require_once ROOT_PATH . 'config/supabase.php';
require_once ROOT_PATH . 'config/db.php';
require_once ROOT_PATH . 'includes/functions.php';
require_once ROOT_PATH . 'includes/i18n.php';
require_once ROOT_PATH . 'includes/translation.php';

// ─── Stateless Cookie-Based Session Replication (Vercel Serverless Compatibility) ───
$isSecureRequest = (
    !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    || (isset($isSecure) && $isSecure)
);

if (empty($_SESSION['user_id']) && !empty($_COOKIE['campusmarket_sess_stateless'])) {
    $parts = explode('.', $_COOKIE['campusmarket_sess_stateless'], 2);
    if (count($parts) === 2) {
        $json = base64_decode($parts[0], true);
        $signature = $parts[1];
        $secret = supabaseAnonKey() ?: 'campusmarket_fallback_secret_key_12345';
        if ($json !== false && hash_equals(hash_hmac('sha256', $json, $secret), $signature)) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['user_id'])) {
                $_SESSION['user_id'] = (int)$data['user_id'];
                $_SESSION['role'] = $data['role'] ?? 'user';
                $_SESSION['username'] = $data['username'] ?? '';
                $_SESSION['supabase_access_token'] = $data['supabase_access_token'] ?? '';
                $_SESSION['supabase_refresh_token'] = $data['supabase_refresh_token'] ?? '';
                $_SESSION['preferred_language'] = $data['preferred_language'] ?? DEFAULT_LANGUAGE;
            }
        }
    }
}

// Register a shutdown function to automatically sync any session changes back to the secure cookie
register_shutdown_function(function() use ($isSecureRequest) {
    if (!headers_sent()) {
        if (!empty($_SESSION['user_id'])) {
            $sessionData = [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'] ?? 'user',
                'username' => $_SESSION['username'] ?? '',
                'supabase_access_token' => $_SESSION['supabase_access_token'] ?? '',
                'supabase_refresh_token' => $_SESSION['supabase_refresh_token'] ?? '',
                'preferred_language' => $_SESSION['preferred_language'] ?? DEFAULT_LANGUAGE,
            ];
            $json = json_encode($sessionData);
            $secret = supabaseAnonKey() ?: 'campusmarket_fallback_secret_key_12345';
            $signature = hash_hmac('sha256', $json, $secret);
            $cookieValue = base64_encode($json) . '.' . $signature;
            
            setcookie('campusmarket_sess_stateless', $cookieValue, [
                'expires' => time() + 86400 * 30, // 30 days
                'path' => '/',
                'secure' => $isSecureRequest,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            // Delete the stateless session cookie if the session was cleared
            setcookie('campusmarket_sess_stateless', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isSecureRequest,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
});

// Session Validation (Prevent stale sessions after re-seeds)
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    if (!$stmt->fetch()) {
        session_unset();
        session_destroy();
        // Start a new session for flash messages
        session_name(SESSION_NAME);
        session_start();
    }
}

// ─── i18n Initialization ─────────────────────────────────────
// Determine user's preferred language: session > DB > browser > default
$_userLang = $_SESSION['preferred_language'] ?? null;
if (!$_userLang && isLoggedIn()) {
    $_userLang = getUserPreferredLanguage($pdo, currentUserId());
    $_SESSION['preferred_language'] = $_userLang;
}
if (!$_userLang) {
    $_userLang = i18nDetectFromBrowser();
}
i18nInit($_userLang);
