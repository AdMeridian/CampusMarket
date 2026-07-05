<?php
/**
 * includes/auth_check.php
 * 
 * Middleware for Member 1 to protect pages requiring authentication.
 * Includes this at the top of any page restricted to users or admins.
 */

require_once 'functions.php';

// Protect a page for any logged-in user
if (!isLoggedIn()) {
    setFlash('error', 'Please login to access this page.');
    redirect(BASE_URL . '/pages/login.php');
}

// Optionally check for admin status if the page is in the /admin folder
if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
    if (!isAdmin()) {
        setFlash('error', 'Access denied. Administrator privileges required.');
        redirect(BASE_URL);
    }
}
