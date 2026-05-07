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
    
    <!-- Member 5: Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
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
    
</head>
<body>
 
<nav class="navbar">
    <div class="container flex justify-between items-center">
        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>index.php" class="logo" style="display: flex; align-items: center; gap: 0.6rem;">
            <img src="<?php echo BASE_URL; ?>public/images/logo.png" alt="CampusMarket Logo" style="height: 42px; width: auto; object-fit: contain;">
            <span>CampusMarket</span>
        </a>
        
        <!-- Shared Search Bar -->
        <form action="<?php echo BASE_URL; ?>pages/search.php" method="GET" class="search-bar group" style="flex: 1; max-width: 450px;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?php $placeholder = (isLoggedIn() && isAdmin()) ? "Search items..." : "Search items, books, tech..."; ?>
            <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input">
            <button type="submit" class="search-btn">Search</button>
        </form>

        <!-- Navigation Links -->
        <div class="nav-links">
            <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
                <svg class="sun-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
                <svg class="moon-icon" viewBox="0 0 24 24"><path d="M12.12 22a9.66 9.66 0 0 1-7.07-2.93 9.66 9.66 0 0 1 0-13.64 9.66 9.66 0 0 1 13.64 0c.39.39.39 1.02 0 1.41a1 1 0 0 1-1.41 0 7.66 7.66 0 0 0-10.82 0 7.66 7.66 0 0 0 0 10.82 7.66 7.66 0 0 0 10.82 0 1 1 0 0 1 1.41 0c.39.39.39 1.02 0 1.41a9.66 9.66 0 0 1-6.57 2.93z"/></svg>
            </button>
            <a href="<?php echo BASE_URL; ?>pages/browse.php">Browse</a>
            <?php if (isLoggedIn() && isAdmin()): ?>
                <!-- Admin-only nav: no marketplace actions -->
                <div class="flex items-center gap-4" style="border-left: 1px solid var(--border-light); padding-left: 1.5rem; margin-left: 0.25rem;">
                    <span style="font-size: 0.85rem; color: var(--text-main); font-weight: 600; padding: 0.35rem 0.85rem; background: var(--warning-bg); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; text-align: center; gap: 0.4rem; box-shadow: var(--shadow-sm);">
                        🛡️ Admin Mode
                    </span>
                    <a href="<?php echo BASE_URL; ?>admin/index.php" style="color: var(--primary); font-weight: 700; font-size: 0.95rem;">Admin Panel</a>
                    <a href="<?php echo BASE_URL; ?>pages/logout.php" class="btn btn-danger btn-sm" style="border-radius: var(--radius-full); padding: 0.45rem 1.2rem; font-weight: 600;">Logout</a>
                </div>
            <?php elseif (isLoggedIn()): ?>
                <?php $unreadNotifications = countUnreadNotifications($pdo, currentUserId()); ?>
                <a href="<?php echo BASE_URL; ?>/pages/inbox.php" class="flex items-center gap-1">
                    Inbox
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="badge" style="background: var(--accent); color: white; padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>pages/create_listing.php" style="font-weight: 600; color: var(--primary);">Sell Item</a>
                
                <!-- User Account Dropdown -->
                <div class="user-dropdown">
                    <button class="user-dropdown-btn">
                        <span>Account</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div class="user-dropdown-content">
                        <a href="<?php echo BASE_URL; ?>pages/my_orders.php">My Orders</a>
                        <a href="<?php echo BASE_URL; ?>pages/wishlist.php">Wishlist</a>
                        <a href="<?php echo BASE_URL; ?>pages/profile.php">Profile Settings</a>
                        <div style="border-top: 1px solid var(--border-light); margin: 0.5rem 0;"></div>
                        <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error);">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>pages/login.php">Login</a>
                <a href="<?php echo BASE_URL; ?>pages/register.php" class="btn btn-primary btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script src="<?php echo BASE_URL; ?>public/js/theme.js"></script>

<div class="container">
    <?php if ($flash = getFlash()): ?>
        <div class="mt-4 flex items-center flash flash-<?php echo sanitize($flash['type']); ?>">
            <?php echo sanitize($flash['message']); ?>
        </div>
    <?php endif; ?>
