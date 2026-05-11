<?php
// ============================================================
// CampusMarket — App-Wide Constants
// ============================================================

// Base URL — change if you rename the folder
define('BASE_URL',    'http://localhost/campusmarket/');

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
