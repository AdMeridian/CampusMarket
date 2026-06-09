<?php
// includes/header.php
require_once __DIR__ . '/bootstrap.php';

// Fetch categories for global search
$navCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo i18nGetLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - CampusMarket' : 'CampusMarket'; ?></title>
    <meta name="theme-color" content="#4f46e5">
    <meta name="application-name" content="CampusMarket">
    <meta name="mobile-web-app-capable" content="yes">
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
    
    <!-- Chatbot Stylesheet -->
    <?php
        $chatbotCssPath = __DIR__ . '/../public/css/chatbot.css';
        $chatbotCssVer = file_exists($chatbotCssPath) ? filemtime($chatbotCssPath) : '1';
    ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/chatbot.css?v=<?php echo $chatbotCssVer; ?>">

    <?php if (isAdmin()): ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/admin.css">
    <?php endif; ?>
    
    <!-- Theme Initialization -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', () => document.body.classList.add('dark-mode'));
            }

            // Intercept Supabase auth callback hash fragments on any page (like home page fallbacks)
            const hash = window.location.hash;
            if (hash && (hash.includes('access_token=') || hash.includes('type=recovery') || hash.includes('type=signup') || hash.includes('type=email'))) {
                const params = new URLSearchParams(hash.replace(/^#/, ''));
                const type = params.get('type') || '';
                const accessToken = params.get('access_token') || '';

                if (accessToken !== '') {
                    if ((type === 'recovery' || hash.includes('type=recovery')) && !window.location.pathname.includes('reset_password')) {
                        window.location.href = '<?php echo BASE_URL; ?>pages/reset_password' + hash;
                    } else if ((type === 'signup' || type === 'email' || hash.includes('type=signup') || hash.includes('type=email')) && !window.location.pathname.includes('verify_email')) {
                        window.location.href = '<?php echo BASE_URL; ?>pages/verify_email?source=supabase&access_token=' + encodeURIComponent(accessToken) + '&type=' + encodeURIComponent(type || 'email');
                    }
                }
            }
        })();
    </script>
    <?php if (isSupabaseConfigured()): ?>
    <script>
        window.__env = {
            SUPABASE_URL: <?php echo json_encode(supabaseUrl()); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(supabaseAnonKey()); ?>,
            WEB_PUSH_PUBLIC_KEY: <?php echo json_encode(WEB_PUSH_PUBLIC_KEY); ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="<?php echo BASE_URL; ?>public/js/supabase-client.js"></script>
    <?php endif; ?>
    <script>window.__csrfToken = <?php echo json_encode(csrfToken()); ?>;</script>
    
    <!-- i18n Client Data -->
    <script>
        window.__baseUrl = <?php echo json_encode(BASE_URL); ?>;
        window.__locale = <?php echo json_encode(i18nGetLocale()); ?>;
        window.__languages = <?php echo json_encode(SUPPORTED_LANGUAGES); ?>;
        window.__i18n = <?php echo json_encode(i18nGetAllStrings()); ?>;
    </script>
    
    <!-- Vercel Web Analytics -->
    <script defer src="https://cdn.vercel-insights.com/v1/script.js"></script>
    <!-- Vercel Speed Insights -->
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    
    <!-- Language Selector Styles -->
    <style>
        .lang-dropdown { position: relative; display: inline-flex; }
        .lang-dropdown-btn {
            background: var(--bg-main);
            border: 1px solid var(--border-light);
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: var(--radius-md);
            transition: var(--transition);
            font-size: 0.82rem;
            font-weight: 700;
            height: 38px;
            box-sizing: border-box;
        }
        #lang-dropdown-mobile .lang-dropdown-btn {
            width: 38px;
            justify-content: center;
            padding: 0;
        }
        #lang-dropdown-desktop .lang-dropdown-btn {
            padding: 0 0.75rem;
        }
        .lang-dropdown-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary);
        }
        .lang-dropdown-content {
            display: none; position: absolute; top: 100%; right: 0; z-index: 1000;
            min-width: 150px; background: var(--bg-surface); border: 1px solid var(--border-light);
            border-radius: var(--radius-md); box-shadow: var(--shadow-lg); overflow: hidden;
            margin-top: 0.35rem; animation: langDropIn 0.15s ease;
        }
        @keyframes langDropIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
        .lang-dropdown:hover .lang-dropdown-content,
        .lang-dropdown.open .lang-dropdown-content { display: block; }
        .lang-option {
            display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 1rem;
            color: var(--text-main); text-decoration: none; font-size: 0.88rem;
            font-weight: 500; transition: background 0.15s ease; cursor: pointer; border: none;
            background: none; width: 100%; text-align: left;
        }
        .lang-option:hover { background: rgba(99,102,241,0.06); }
        .lang-option.active { color: var(--primary); font-weight: 700; background: rgba(99,102,241,0.04); }
        .lang-option-check { width: 16px; text-align: center; font-size: 0.75rem; }
    </style>

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

            <!-- Language Selector (Mobile) -->
            <div class="lang-dropdown" id="lang-dropdown-mobile">
                <button type="button" class="lang-dropdown-btn" aria-label="<?= __('lang.selector_label') ?>" onclick="this.parentElement.classList.toggle('open')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </button>
                <div class="lang-dropdown-content">
                    <?php foreach (SUPPORTED_LANGUAGES as $code => $name): ?>
                    <button type="button" class="lang-option <?= $code === i18nGetLocale() ? 'active' : '' ?>" onclick="changeLang('<?= $code ?>')">
                        <span class="lang-option-check"><?= $code === i18nGetLocale() ? '✓' : '' ?></span>
                        <span><?= htmlspecialchars($name) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <button id="theme-toggle-mobile" class="theme-toggle" aria-label="Toggle dark mode">
                <svg class="toggle-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
            </button>
            <button class="nav-mobile-toggle" id="mobile-menu-btn" aria-label="Toggle Menu">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>

        <!-- Shared Search Bar (Desktop) -->
        <form action="<?php echo rtrim(BASE_URL, '/'); ?>/pages/search.php" method="GET" class="search-bar group lg-flex" style="flex: 1; max-width: 450px; margin: 0 2rem;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?php $placeholder = (isLoggedIn() && isAdmin()) ? __('nav.search_placeholder_admin') : __('nav.search_placeholder'); ?>
            <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input" autocomplete="off">
            <button type="submit" class="search-btn"><?= __('nav.search_btn') ?></button>
        </form>

        <!-- Navigation Links -->
        <div class="nav-links" id="nav-links">
            <!-- Mobile menu back/close button (only visible inside the mobile dropdown) -->
            <button id="mobile-menu-close" class="mobile-menu-close-btn" aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 2px;">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Back</span>
            </button>
            <div class="flex" style="align-items: center; gap: 0.25rem;">
                <!-- Language Selector (Desktop) -->
                <div class="lang-dropdown" id="lang-dropdown-desktop">
                    <button type="button" class="lang-dropdown-btn" aria-label="<?= __('lang.selector_label') ?>" onclick="this.parentElement.classList.toggle('open')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 17px; height: 17px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;"><?= strtoupper(i18nGetLocale()) ?></span>
                    </button>
                    <div class="lang-dropdown-content">
                        <?php foreach (SUPPORTED_LANGUAGES as $code => $name): ?>
                        <button type="button" class="lang-option <?= $code === i18nGetLocale() ? 'active' : '' ?>" onclick="changeLang('<?= $code ?>')">
                            <span class="lang-option-check"><?= $code === i18nGetLocale() ? '✓' : '' ?></span>
                            <span><?= htmlspecialchars($name) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
                    <svg class="toggle-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
                </button>
            </div>

            <a href="<?php echo BASE_URL; ?>pages/browse.php"><?= __('nav.browse') ?></a>
            <?php if (isLoggedIn()): ?>
                <?php 
                    $unreadMessages = countUnreadMessages($pdo, currentUserId()); 
                    $unreadNotifs = countUnreadNotifications($pdo, currentUserId());
                ?>
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>admin/index.php" style="color: var(--secondary); font-weight: bold;"><?= __('nav.admin_panel') ?></a>
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" class="flex items-center gap-1" title="<?= __('nav.inbox') ?>">
                        <?= __('nav.inbox') ?> <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" class="flex items-center gap-1" title="<?= __('nav.notifications') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error); font-weight: 500;"><?= __('nav.logout') ?></a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" class="flex items-center gap-1" title="<?= __('nav.inbox') ?>">
                        <?= __('nav.inbox') ?> <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" class="flex items-center gap-1" title="<?= __('nav.notifications') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/create_listing.php" style="font-weight: 700; color: var(--primary);"><?= __('nav.sell_item') ?></a>
                    
                    <!-- User Account Dropdown -->
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-btn" aria-expanded="false" aria-haspopup="true">
                            <span><?php echo sanitize($_SESSION['username'] ?? __('nav.account')); ?></span>
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                        <div class="user-dropdown-content">
                            <a href="<?php echo BASE_URL; ?>pages/my_orders.php"><?= __('nav.my_orders') ?></a>
                            <a href="<?php echo BASE_URL; ?>pages/wishlist.php"><?= __('nav.wishlist') ?></a>
                            <a href="<?php echo BASE_URL; ?>pages/promotions.php"><?= __('nav.promotions') ?></a>
                            <a href="<?php echo BASE_URL; ?>pages/profile.php"><?= __('nav.my_profile') ?></a>
                            <a href="<?php echo BASE_URL; ?>pages/messages.php?other_user_id=1&product_id=0" style="color: var(--secondary); font-weight: bold;"><?= __('nav.contact_support') ?></a>
                            <div style="border-top: 1px solid var(--border-light); margin: 0.5rem 0;"></div>
                            <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error);"><?= __('nav.logout') ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>pages/login.php"><?= __('nav.login') ?></a>
                <a href="<?php echo BASE_URL; ?>pages/register.php" class="btn btn-primary btn-sm" style="color: white !important;"><?= __('nav.signup') ?></a>
            <?php endif; ?>
    </div>
</nav>

<!-- Mobile Search Row (Visible only on mobile, pushed below the fixed navbar) -->
<div class="lg-hidden mobile-search-row">
    <form action="<?php echo BASE_URL; ?>pages/search.php" method="GET" class="search-bar" style="max-width: 500px; margin: 0 auto;">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <?php $placeholder = (isLoggedIn() && isAdmin()) ? __('nav.search_placeholder_admin') : __('nav.search_placeholder'); ?>
        <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input" autocomplete="off">
        <button type="submit" class="search-btn"><?= __('nav.search_btn') ?></button>
    </form>
</div>

<?php
    $themeJsPath = __DIR__ . '/../public/js/theme.js';
    $themeJsVer = file_exists($themeJsPath) ? filemtime($themeJsPath) : '1';
    $menuJsPath = __DIR__ . '/../public/js/mobile-menu.js';
    $menuJsVer = file_exists($menuJsPath) ? filemtime($menuJsPath) : '1';
    $i18nJsPath = __DIR__ . '/../public/js/i18n-client.js';
    $i18nJsVer = file_exists($i18nJsPath) ? filemtime($i18nJsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>public/js/theme.js?v=<?php echo $themeJsVer; ?>"></script>
<script src="<?php echo BASE_URL; ?>public/js/mobile-menu.js?v=<?php echo $menuJsVer; ?>"></script>
<script src="<?php echo BASE_URL; ?>public/js/i18n-client.js?v=<?php echo $i18nJsVer; ?>"></script>
<?php if (isLoggedIn()): ?>
<script src="<?php echo BASE_URL; ?>public/js/notifications-realtime.js"></script>
<?php endif; ?>

<script>
function changeLang(langCode) {
    const formData = new FormData();
    formData.append('action', 'set_language');
    formData.append('language', langCode);
    formData.append('csrf_token', window.__csrfToken || '');

    fetch(window.__baseUrl + 'pages/api_messages.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(err => console.error('Language change error:', err));
}

// Close lang dropdowns when clicking outside
document.addEventListener('click', function(e) {
    document.querySelectorAll('.lang-dropdown.open').forEach(function(dd) {
        if (!dd.contains(e.target)) {
            dd.classList.remove('open');
        }
    });
});
</script>

<?php if ($flash = getFlash()): ?>
<div class="flash-toast-container">
    <div class="flash flash-<?php echo sanitize($flash['type']); ?>">
        <div style="flex-grow: 1; display: flex; align-items: center; gap: 0.75rem;">
            <?php if ($flash['type'] === 'success'): ?>
                <svg style="width: 20px; height: 20px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <?php else: ?>
                <svg style="width: 20px; height: 20px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1-1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <?php endif; ?>
            <span><?php echo sanitize($flash['message']); ?></span>
        </div>
        <button onclick="this.closest('.flash-toast-container').remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0.25rem; display: flex; align-items: center; justify-content: center; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">
            <svg style="width: 18px; height: 18px;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
    </div>
</div>
<?php endif; ?>
