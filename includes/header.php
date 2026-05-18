<?php
// includes/header.php
require_once __DIR__ . '/bootstrap.php';

// Fetch categories for global search
$navCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - CampusMarket' : 'CampusMarket'; ?></title>
    <meta name="theme-color" content="#4f46e5">
    <meta name="application-name" content="CampusMarket">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CampusMarket">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.webmanifest">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>public/images/logo.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>public/images/logo.png">
    <?php if (isLoggedIn()): ?>
    <meta name="user-id" content="<?php echo currentUserId(); ?>">
    <?php endif; ?>
    
    <!-- Member 5: Design System -->
    <?php 
        $cssPath = __DIR__ . '/../public/css/style.css';
        $cssVer = file_exists($cssPath) ? filemtime($cssPath) : '1';
    ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css?v=<?php echo $cssVer; ?>">
    <?php if (isAdmin()): ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/admin.css">
    <?php endif; ?>
    
    <!-- Theme Initialization -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', () => document.body.classList.add('dark-mode'));
            }
        })();
    </script>
    <?php if (isSupabaseConfigured()): ?>
    <script>
        window.__env = {
            SUPABASE_URL: <?php echo json_encode(supabaseUrl()); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(supabaseAnonKey()); ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="<?php echo BASE_URL; ?>public/js/supabase-client.js"></script>
    <?php endif; ?>
    <script>window.__csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;</script>
    
    <!-- Vercel Web Analytics -->
    <script defer src="https://cdn.vercel-insights.com/v1/script.js"></script>
    <!-- Vercel Speed Insights -->
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    
</head>
<body>
 
<nav class="navbar">
    <div class="container flex justify-between items-center">
        <!-- Logo -->
        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/index.php" class="logo" style="display: flex; align-items: center; gap: 0.6rem;">
            <img src="<?php echo rtrim(BASE_URL, '/'); ?>/public/images/logo.png" alt="CampusMarket Logo" style="height: 42px; width: auto; object-fit: contain;">
            <span>CampusMarket</span>
        </a>
        
        <!-- Mobile Tools (Visible only on mobile next to the logo) -->
        <div class="lg-hidden flex items-center gap-2" style="margin-left: auto;">
            <button id="theme-toggle-mobile" class="theme-toggle" aria-label="Toggle dark mode">
                <svg class="toggle-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
            </button>
            <button class="nav-mobile-toggle" id="mobile-menu-btn" aria-label="Toggle Menu">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>

        <!-- Shared Search Bar (Desktop) -->
        <form action="<?php echo rtrim(BASE_URL, '/'); ?>/pages/search.php" method="GET" class="search-bar group lg-flex" style="flex: 1; max-width: 450px; margin: 0 auto;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?php $placeholder = (isLoggedIn() && isAdmin()) ? "Search items..." : "Search items, books, tech..."; ?>
            <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input">
            <button type="submit" class="search-btn">Search</button>
        </form>

        <!-- Navigation Links -->
        <div class="nav-links" id="nav-links">
            <div class="hidden lg-block">
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
                    <svg class="toggle-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
                </button>
            </div>

            <a href="<?php echo BASE_URL; ?>pages/browse.php">Browse</a>
            <?php if (isLoggedIn() && isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>admin/index.php">Admin Panel</a>
                <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error);">Logout</a>
            <?php elseif (isLoggedIn()): ?>
                <?php 
                    $unreadMessages = countUnreadMessages($pdo, currentUserId()); 
                    $unreadNotifs = countUnreadNotifications($pdo, currentUserId());
                ?>
                <a href="<?php echo BASE_URL; ?>/pages/inbox.php" class="flex items-center gap-1" title="Messages">
                    Inbox <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/notifications.php" class="flex items-center gap-1" title="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>pages/create_listing.php" style="font-weight: 600; color: var(--primary);">Sell Item</a>
                
                <!-- User Account Dropdown -->
                <div class="user-dropdown">
                    <button type="button" class="user-dropdown-btn" aria-expanded="false" aria-haspopup="true">
                        <span><?php echo sanitize($_SESSION['username'] ?? 'Account'); ?></span>
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div class="user-dropdown-content">
                        <a href="<?php echo BASE_URL; ?>pages/my_orders.php">My Orders</a>
                        <a href="<?php echo BASE_URL; ?>pages/wishlist.php">Wishlist</a>
                        <a href="<?php echo BASE_URL; ?>pages/promotions.php">Promotions</a>
                        <a href="<?php echo BASE_URL; ?>pages/profile.php">My Profile</a>
                        <a href="<?php echo BASE_URL; ?>pages/messages.php?other_user_id=1&product_id=0" style="color: var(--secondary); font-weight: bold;">Contact Support</a>
                        <div style="border-top: 1px solid var(--border-light); margin: 0.5rem 0;"></div>
                        <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error);">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>pages/login.php">Login</a>
                <a href="<?php echo BASE_URL; ?>pages/register.php" class="btn btn-primary btn-sm" style="color: white !important;">Sign Up</a>
            <?php endif; ?>
    </div>
</nav>

<!-- Mobile Search Row (Visible only on mobile, pushed below the fixed navbar) -->
<div class="lg-hidden" style="margin-top: 62px; background: var(--bg-surface); padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-light);">
    <form action="<?php echo BASE_URL; ?>pages/search.php" method="GET" class="search-bar" style="width: 88%; max-width: 500px; margin: 0 auto;">
        <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="Search items, books, tech..." class="search-input" style="padding: 0.6rem 1rem; font-size: 0.95rem;">
        <button type="submit" class="search-btn" style="padding: 0.6rem 1.25rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
    </form>
</div>

<?php
    $themeJsPath = __DIR__ . '/../public/js/theme.js';
    $themeJsVer = file_exists($themeJsPath) ? filemtime($themeJsPath) : '1';
    $menuJsPath = __DIR__ . '/../public/js/mobile-menu.js';
    $menuJsVer = file_exists($menuJsPath) ? filemtime($menuJsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>public/js/theme.js?v=<?php echo $themeJsVer; ?>"></script>
<script src="<?php echo BASE_URL; ?>public/js/mobile-menu.js?v=<?php echo $menuJsVer; ?>"></script>
<?php if (isLoggedIn()): ?>
<script src="<?php echo BASE_URL; ?>public/js/notifications-realtime.js"></script>
<?php endif; ?>

<div class="container">
    <?php if ($flash = getFlash()): ?>
        <div class="mt-4 flex items-center flash flash-<?php echo sanitize($flash['type']); ?>">
            <?php echo sanitize($flash['message']); ?>
        </div>
    <?php endif; ?>
