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
    <meta name="theme-color" content="<?php echo htmlspecialchars(APP_THEME_COLOR, ENT_QUOTES, 'UTF-8'); ?>">
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
    <style id="nav-critical-css">
      @media (max-width: 1023px) {
        .nav-desktop-dropdown,
        .navbar-desktop-actions,
        #nav-links .theme-toggle,
        #nav-links .nav-drawer-theme-toggle {
          display: none !important;
        }
        .mobile-account-nav { display: block !important; width: 100% !important; }
        #nav-links { gap: 0 !important; }
        #nav-links .mobile-nav-section {
          width: 100% !important;
          margin: 0 !important;
          padding: 0 !important;
          border-top: 1px solid var(--border-light, #e5e7eb) !important;
        }
        #nav-links .mobile-nav-section-label {
          display: block !important;
          margin: 0 !important;
          padding: 0.9rem 1rem 0.4rem !important;
          font-size: 0.7rem !important;
          font-weight: 700 !important;
          letter-spacing: 0.08em !important;
          text-transform: uppercase !important;
          color: var(--text-muted, #6b7280) !important;
          border: none !important;
          background: transparent !important;
        }
        #nav-links .mobile-nav-link {
          display: flex !important;
          align-items: center !important;
          justify-content: space-between !important;
          width: 100% !important;
          box-sizing: border-box !important;
          min-height: 48px !important;
          padding: 0.85rem 1rem !important;
          font-size: 1.05rem !important;
          font-weight: 500 !important;
          color: var(--text-main, #111827) !important;
          text-decoration: none !important;
          border: none !important;
          border-bottom: 1px solid var(--border-light, #e5e7eb) !important;
          border-radius: 0 !important;
          background: transparent !important;
        }
        #nav-links .mobile-nav-link.is-active {
          color: var(--primary, #4f46e5) !important;
          background: var(--primary-light, #eef2ff) !important;
        }
        #nav-links .mobile-nav-link--accent {
          color: var(--primary, #4f46e5) !important;
          font-weight: 600 !important;
        }
        #nav-links .mobile-nav-link--admin {
          color: var(--secondary, #059669) !important;
          font-weight: 600 !important;
        }
        #nav-links .mobile-nav-link--support {
          color: var(--secondary, #059669) !important;
          font-weight: 600 !important;
        }
        #nav-links .mobile-nav-link--danger {
          color: var(--error, #ef4444) !important;
          font-weight: 600 !important;
        }
        #nav-links .mobile-nav-link--cta {
          justify-content: center !important;
          margin: 0.75rem 1rem !important;
          width: calc(100% - 2rem) !important;
          border-radius: 0.5rem !important;
          border: none !important;
          background: var(--primary, #4f46e5) !important;
          color: #fff !important;
          font-weight: 600 !important;
        }
        #nav-links .mobile-nav-section--footer .mobile-nav-link:last-child {
          border-bottom: none !important;
        }
      }
      @media (min-width: 1024px) {
        .mobile-account-nav { display: none !important; }
        .navbar .container {
          flex-wrap: nowrap !important;
          align-items: center !important;
        }
        #nav-links {
          display: flex !important;
          flex-direction: row !important;
          flex-wrap: nowrap !important;
          align-items: center !important;
          gap: 0.75rem !important;
          position: static !important;
          width: auto !important;
          height: auto !important;
          padding: 0 !important;
          background: transparent !important;
          transform: none !important;
          visibility: visible !important;
          pointer-events: auto !important;
          overflow: visible !important;
          flex-shrink: 0 !important;
        }
        .navbar-desktop-actions {
          display: flex !important;
          align-items: center !important;
          gap: 0.5rem !important;
          flex-shrink: 0 !important;
        }
        #nav-links .mobile-nav-link {
          display: inline-flex !important;
          width: auto !important;
          min-height: 0 !important;
          padding: 0.5rem 0.75rem !important;
          border: none !important;
          white-space: nowrap !important;
        }
        #nav-links .mobile-nav-link--cta {
          padding: 0.6rem 1.25rem !important;
          width: auto !important;
          margin: 0 !important;
        }
        .nav-notifications-label { display: none !important; }
        .nav-notifications-icon { width: 18px !important; height: 18px !important; }
      }
    </style>
    
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
            <button class="nav-mobile-toggle" id="mobile-menu-btn" aria-label="Toggle Menu" aria-expanded="false" aria-controls="nav-links">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>

        <!-- Shared Search Bar (Desktop) -->
        <form action="<?php echo rtrim(BASE_URL, '/'); ?>/pages/search.php" method="GET" class="search-bar group lg-flex" style="margin: 0 1.25rem;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?php $placeholder = (isLoggedIn() && isAdmin()) ? __('nav.search_placeholder_admin') : __('nav.search_placeholder'); ?>
            <input type="text" name="q" value="<?php echo sanitize($_GET['q'] ?? ''); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input" autocomplete="off">
            <button type="submit" class="search-btn"><?= __('nav.search_btn') ?></button>
        </form>

        <!-- Navigation -->
        <div class="nav-links" id="nav-links">
            <!-- Mobile menu back/close button (only visible inside the mobile dropdown) -->
            <button id="mobile-menu-close" class="mobile-menu-close-btn" aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 2px;">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span><?= __('nav.mobile_menu_back') ?></span>
            </button>

            <a href="<?php echo BASE_URL; ?>pages/browse.php" class="mobile-nav-link"><?= __('nav.browse') ?></a>
            <?php if (isLoggedIn()): ?>
                <?php 
                    $unreadMessages = countUnreadMessages($pdo, currentUserId()); 
                    $unreadNotifs = countUnreadNotifications($pdo, currentUserId());
                ?>
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>admin/index.php" class="mobile-nav-link mobile-nav-link--admin"><?= __('nav.admin_panel') ?></a>
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" data-nav-badge="inbox" class="mobile-nav-link" title="<?= __('nav.inbox') ?>">
                        <span><?= __('nav.inbox') ?></span>
                        <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" data-nav-badge="notifications" class="mobile-nav-link nav-notifications-link" title="<?= __('nav.notifications') ?>" aria-label="<?= __('nav.notifications') ?>">
                        <span class="nav-notifications-label lg-hidden"><?= __('nav.notifications') ?></span>
                        <svg class="nav-notifications-icon lg-block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/logout.php" class="mobile-nav-link mobile-nav-link--danger"><?= __('nav.logout') ?></a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>pages/inbox.php" data-nav-badge="inbox" class="mobile-nav-link" title="<?= __('nav.inbox') ?>">
                        <span><?= __('nav.inbox') ?></span>
                        <?php if ($unreadMessages > 0): ?><span class="badge badge-primary"><?php echo $unreadMessages; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/notifications.php" data-nav-badge="notifications" class="mobile-nav-link nav-notifications-link" title="<?= __('nav.notifications') ?>" aria-label="<?= __('nav.notifications') ?>">
                        <span class="nav-notifications-label lg-hidden"><?= __('nav.notifications') ?></span>
                        <svg class="nav-notifications-icon lg-block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <?php if ($unreadNotifs > 0): ?><span class="badge badge-accent"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/create_listing.php" class="mobile-nav-link mobile-nav-link--accent"><?= __('nav.sell_item') ?></a>
                    
                    <?php
                        $navUsername = sanitize($_SESSION['username'] ?? __('nav.account'));
                        $navRequestPath = $_SERVER['REQUEST_URI'] ?? '';
                        $navItemActive = function (string $needle) use ($navRequestPath): string {
                            return str_contains($navRequestPath, $needle) ? ' is-active' : '';
                        };
                        require __DIR__ . '/partials/nav_account_mobile.php';
                    ?>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>pages/login.php" class="mobile-nav-link"><?= __('nav.login') ?></a>
                <a href="<?php echo BASE_URL; ?>pages/register.php" class="mobile-nav-link mobile-nav-link--cta btn btn-primary btn-sm"><?= __('nav.signup') ?></a>
            <?php endif; ?>

            <div class="navbar-desktop-actions lg-flex">
            <button id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
                <svg class="toggle-icon" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41zm-12.37 12.37c-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06c.39-.39.39-1.03 0-1.41z"/></svg>
            </button>
            <?php if (isLoggedIn() && !isAdmin()): ?>
            <div class="user-dropdown nav-desktop-dropdown">
                <button type="button" class="user-dropdown-btn" aria-expanded="false" aria-haspopup="true">
                    <span><?php echo $navUsername; ?></span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>

                <div class="user-dropdown-content">
                    <div class="user-dropdown-group">
                        <p class="user-dropdown-group-label"><?= __('nav.menu_shopping') ?></p>
                        <a href="<?php echo BASE_URL; ?>pages/my_orders.php" class="user-dropdown-item<?php echo $navItemActive('my_orders.php'); ?>"><?= __('nav.my_orders') ?></a>
                        <a href="<?php echo BASE_URL; ?>pages/my_reports.php" class="user-dropdown-item<?php echo $navItemActive('my_reports.php'); ?>"><?= __('nav.my_reports') ?></a>
                        <a href="<?php echo BASE_URL; ?>pages/wishlist.php" class="user-dropdown-item<?php echo $navItemActive('wishlist.php'); ?>"><?= __('nav.wishlist') ?></a>
                        <a href="<?php echo BASE_URL; ?>pages/promotions.php" class="user-dropdown-item<?php echo $navItemActive('promotions.php'); ?>"><?= __('nav.promotions') ?></a>
                    </div>

                    <div class="user-dropdown-group">
                        <p class="user-dropdown-group-label"><?= __('nav.menu_account') ?></p>
                        <a href="<?php echo BASE_URL; ?>pages/profile.php" class="user-dropdown-item<?php echo $navItemActive('profile.php'); ?>"><?= __('nav.my_profile') ?></a>
                        <a href="<?php echo BASE_URL; ?>pages/edit_profile.php#preferred_language" class="user-dropdown-item<?php echo $navItemActive('edit_profile.php'); ?>"><?= __('nav.language_settings', ['lang' => SUPPORTED_LANGUAGES[i18nGetLocale()] ?? strtoupper(i18nGetLocale())]) ?></a>
                    </div>

                    <div class="user-dropdown-footer">
                        <a href="<?php echo BASE_URL; ?>pages/messages.php?other_user_id=1&product_id=0" class="user-dropdown-item user-dropdown-item--support"><?= __('nav.contact_support') ?></a>
                        <a href="<?php echo BASE_URL; ?>pages/logout.php" class="user-dropdown-item user-dropdown-item--logout"><?= __('nav.logout') ?></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
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
<script src="<?php echo BASE_URL; ?>public/js/form-loading.js?v=<?php echo file_exists(__DIR__ . '/../public/js/form-loading.js') ? filemtime(__DIR__ . '/../public/js/form-loading.js') : '1'; ?>"></script>
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
