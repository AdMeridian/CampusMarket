<?php
// ============================================================
// CampusMarket — Bootstrap (included at the top of every page)
// ============================================================

require_once __DIR__ . '/../config/constants.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
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
