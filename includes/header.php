<?php
// includes/header.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/seo.php';

// Fetch categories for global search (cached)
$navCategories = getNavCategories($pdo);
?>
<!DOCTYPE html>
<html lang="<?php echo i18nGetLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(seoFullTitle($pageTitle ?? null), ENT_QUOTES, 'UTF-8'); ?></title>
    <?php seoRenderHeadTags(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if (isSupabaseConfigured()): ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="<?php echo htmlspecialchars(parse_url(supabaseUrl(), PHP_URL_SCHEME) . '://' . parse_url(supabaseUrl(), PHP_URL_HOST), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#4f46e5">
    <meta name="application-name" content="CampusMarket">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CampusMarket">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>public/images/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo BASE_URL; ?>public/images/icon-192.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>public/images/apple-touch-icon.png">
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
    <?php
        $adminCssPath = __DIR__ . '/../public/css/admin.css';
        $adminCssVer = file_exists($adminCssPath) ? filemtime($adminCssPath) : '1';
    ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/admin.css?v=<?php echo $adminCssVer; ?>">
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
        <?php if (isLoggedIn() && !empty($_SESSION['supabase_access_token'])): ?>
        window.__supabaseSession = {
            access_token: <?php echo json_encode($_SESSION['supabase_access_token']); ?>
        };
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="<?php echo BASE_URL; ?>public/js/supabase-client.js"></script>
    <?php endif; ?>
    <script>window.__csrfToken = <?php echo json_encode(csrfToken()); ?>;</script>
    <script>window.__isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;</script>
    <?php if (isLoggedIn() && !empty($_SESSION['prompt_push'])): ?>
    <script>window.__promptPush = true;</script>
    <?php unset($_SESSION['prompt_push']); ?>
    <?php endif; ?>
    
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
    
    <?php if (!IS_LOCALHOST): ?>
    <!-- PostHog Product Analytics (EU) -->
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageviewId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('phc_CGTgXyUm6ZhZatVcHfrJfXXNzVaVLce6yBh5N446HV6Z', {
            api_host: 'https://eu.posthog.com',
            person_profiles: 'identified_only'
        });
    </script>
    <?php if (isLoggedIn()): ?>
    <script>
        posthog.identify(
            <?php echo json_encode((string)currentUserId()); ?>,
            { email: <?php echo json_encode($_SESSION['user_email'] ?? ''); ?> }
        );
    </script>
    <?php endif; ?>
    <?php if (!empty($_SESSION['posthog_event'])): ?>
    <script>
        posthog.capture(
            <?php echo json_encode($_SESSION['posthog_event']['name']); ?>,
            <?php echo json_encode($_SESSION['posthog_event']['properties'] ?? new stdClass()); ?>
        );
    </script>
    <?php unset($_SESSION['posthog_event']); ?>
    <?php endif; ?>
    <?php endif; ?>
    
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
                <span><?= __('nav.mobile_menu_back') ?></span>
            </button>
            <div class="flex" style="align-items: center; gap: 0.25rem;">
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
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" data-nav-badge="inbox" class="flex items-center gap-1" title="<?= __('nav.inbox') ?>">
                        <?= __('nav.inbox') ?> <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" data-nav-badge="notifications" class="flex items-center gap-1" title="<?= __('nav.notifications') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/logout.php" style="color: var(--error); font-weight: 500;"><?= __('nav.logout') ?></a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" data-nav-badge="inbox" class="flex items-center gap-1" title="<?= __('nav.inbox') ?>">
                        <?= __('nav.inbox') ?> <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" data-nav-badge="notifications" class="flex items-center gap-1" title="<?= __('nav.notifications') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/create_listing.php" style="font-weight: 700; color: var(--primary);"><?= __('nav.sell_item') ?></a>
                    
                    <?php
                        $navUsername = sanitize($_SESSION['username'] ?? __('nav.account'));
                        $navRequestPath = $_SERVER['REQUEST_URI'] ?? '';
                        $navItemActive = function (string $needle) use ($navRequestPath): string {
                            return str_contains($navRequestPath, $needle) ? ' is-active' : '';
                        };
                    ?>
                    <!-- User Account Dropdown -->
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-btn lg-flex" aria-expanded="false" aria-haspopup="true">
                            <span><?php echo $navUsername; ?></span>
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>

                        <a href="<?php echo BASE_URL; ?>pages/profile.php" class="user-dropdown-profile lg-hidden">
                            <span class="user-dropdown-avatar" aria-hidden="true"><?php echo strtoupper(substr($navUsername, 0, 1)); ?></span>
                            <span class="user-dropdown-profile-text">
                                <strong>@<?php echo $navUsername; ?></strong>
                                <span><?= __('nav.view_profile') ?></span>
                            </span>
                            <svg class="user-dropdown-profile-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>

                        <div class="user-dropdown-content user-dropdown-panel">
                            <div class="user-dropdown-group">
                                <p class="user-dropdown-group-label"><?= __('nav.menu_shopping') ?></p>
                                <a href="<?php echo BASE_URL; ?>pages/my_orders.php" class="user-dropdown-item<?php echo $navItemActive('my_orders.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                    <span><?= __('nav.my_orders') ?></span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>pages/my_reports.php" class="user-dropdown-item<?php echo $navItemActive('my_reports.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    <span><?= __('nav.my_reports') ?></span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>pages/wishlist.php" class="user-dropdown-item<?php echo $navItemActive('wishlist.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    <span><?= __('nav.wishlist') ?></span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>pages/promotions.php" class="user-dropdown-item<?php echo $navItemActive('promotions.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                    <span><?= __('nav.promotions') ?></span>
                                </a>
                            </div>

                            <div class="user-dropdown-group">
                                <p class="user-dropdown-group-label"><?= __('nav.menu_account') ?></p>
                                <a href="<?php echo BASE_URL; ?>pages/profile.php" class="user-dropdown-item<?php echo $navItemActive('profile.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <span><?= __('nav.my_profile') ?></span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>pages/edit_profile.php#preferred_language" class="user-dropdown-item<?php echo $navItemActive('edit_profile.php'); ?>">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                    <span><?= __('nav.language_settings', ['lang' => SUPPORTED_LANGUAGES[i18nGetLocale()] ?? strtoupper(i18nGetLocale())]) ?></span>
                                </a>
                            </div>

                            <div class="user-dropdown-footer">
                                <a href="<?php echo BASE_URL; ?>pages/messages.php?other_user_id=1&product_id=0" class="user-dropdown-item user-dropdown-item--support">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    <span><?= __('nav.contact_support') ?></span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>pages/logout.php" class="user-dropdown-item user-dropdown-item--logout">
                                    <svg class="user-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    <span><?= __('nav.logout') ?></span>
                                </a>
                            </div>
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
<?php
    $notifJsPath = __DIR__ . '/../public/js/notifications-realtime.js';
    $notifJsVer = file_exists($notifJsPath) ? filemtime($notifJsPath) : '1';
    $pushJsPath = __DIR__ . '/../public/js/push-notifications.js';
    $pushJsVer = file_exists($pushJsPath) ? filemtime($pushJsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>public/js/notifications-realtime.js?v=<?php echo $notifJsVer; ?>"></script>
    <?php if (WEB_PUSH_PUBLIC_KEY !== ''): ?>
<script>
window.__pushI18n = <?php echo json_encode([
    'titlePwa' => __('push.toast_title_pwa'),
    'titleBrowser' => __('push.toast_title_browser'),
    'bodyPwa' => __('push.toast_body_pwa'),
    'bodyBrowser' => __('push.toast_body_browser'),
    'enable' => __('push.enable_btn'),
    'notNow' => __('push.not_now_btn'),
    'enabling' => __('push.enabling_btn'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo BASE_URL; ?>public/js/push-notifications.js?v=<?php echo $pushJsVer; ?>"></script>
<?php endif; ?>
<?php endif; ?>

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

<?php if (isLoggedIn() && WEB_PUSH_PUBLIC_KEY !== ''): ?>
<div id="cm-push-prompt" class="flash-toast-container cm-push-toast-container" hidden aria-hidden="true"></div>
<?php endif; ?>
