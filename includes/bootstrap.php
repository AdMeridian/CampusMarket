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

// CSRF token — generated once per session, validated on every POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
