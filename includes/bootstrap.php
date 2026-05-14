<?php
// ============================================================
// CampusMarket — Bootstrap (included at the top of every page)
// ============================================================

require_once __DIR__ . '/../config/constants.php';
session_name(SESSION_NAME);
session_start();

if (file_exists(ROOT_PATH . 'config/secrets.php')) {
    require_once ROOT_PATH . 'config/secrets.php';
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
