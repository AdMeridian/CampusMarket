<?php
// pages/logout.php — Member 2
// Destroy the session properly and redirect to login.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

unset($_SESSION['supabase_access_token'], $_SESSION['supabase_refresh_token']);

// Clear all session data.
$_SESSION = [];

// Invalidate the session cookie.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Start a fresh session to carry the goodbye flash.
session_name(SESSION_NAME);
session_start();
setFlash('success', 'You have been logged out.');

redirect(BASE_URL . 'pages/login.php');
