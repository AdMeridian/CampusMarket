</div> <!-- End Main Container -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="<?php echo BASE_URL; ?>/" class="logo" style="font-size: 1.5rem; color: var(--primary); font-weight: 700;">CampusMarket</a>
                <p class="mt-4" style="color: var(--text-muted); font-size: 0.9rem;">
                    <?= __('footer.tagline') ?>
                </p>
                <?php if (defined('INSTAGRAM_URL') && INSTAGRAM_URL !== ''): ?>
                <div class="footer-social">
                    <p class="footer-social-label"><?= __('footer.follow_us') ?></p>
                    <a href="<?php echo htmlspecialchars(INSTAGRAM_URL, ENT_QUOTES, 'UTF-8'); ?>"
                       class="footer-social-link"
                       target="_blank"
                       rel="noopener noreferrer"
                       aria-label="<?= htmlspecialchars(__('footer.instagram_aria')) ?>">
                        <span class="footer-social-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" focusable="false">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                            </svg>
                        </span>
                        <span class="footer-social-text"><?= __('footer.instagram') ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="footer-col">
                <h4><?= __('footer.quick_links') ?></h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/browse.php"><?= __('footer.browse_all') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/categories.php"><?= __('footer.categories') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/create_listing.php"><?= __('footer.post_item') ?></a></li>

                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo BASE_URL; ?>/pages/profile.php"><?= __('nav.my_profile') ?></a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>/pages/register.php"><?= __('footer.create_account') ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4><?= __('footer.support') ?></h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/donate.php" class="flex items-center gap-1" style="color: var(--primary); font-weight: 700;">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg> <?= __('footer.donate') ?>
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/safety.php"><?= __('footer.safety') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/report.php"><?= __('footer.report') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/messages.php?other_user_id=1&product_id=0"><?= __('footer.contact') ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= __('footer.legal') ?></h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/terms.php"><?= __('footer.terms') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/privacy.php"><?= __('footer.privacy') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/rules.php"><?= __('footer.rules') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/cookies.php"><?= __('footer.cookies') ?></a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p><?php echo str_replace('{year}', date('Y'), __('footer.copyright')); ?></p>
        </div>
    </div>
</footer>

<script>
const PWA_SW_URL = "<?php echo BASE_URL; ?>sw.js";
</script>
<?php
    $pwaJsPath = __DIR__ . '/../public/js/pwa.js';
    $pwaJsVer = assetVersion($pwaJsPath);
?>
<script src="<?php echo BASE_URL; ?>public/js/pwa.js?v=<?php echo $pwaJsVer; ?>"></script>
<?php
    $searchJsPath = __DIR__ . '/../public/js/search-suggestions.js';
    $searchJsVer = assetVersion($searchJsPath);
?>
<script src="<?php echo BASE_URL; ?>public/js/search-suggestions.js?v=<?php echo $searchJsVer; ?>"></script>

<!-- Floating AI Chatbot Widget -->
<?php
    // Admin exclusion: do not show chatbot in the /admin/ panel since admins don't need it
    $isAdminPanel = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    if (!$isAdminPanel):
        // Fetch First Admin ID dynamically for the chatbot support panel
        $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $chatbotAdminId = (int)($adminStmt->fetchColumn() ?: 1);
?>
<button id="cm-chatbot-fab" class="cm-chatbot-fab" aria-label="Open support chat">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 28px; height: 28px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10z"></path></svg>
</button>

<div id="cm-chatbot-window" class="cm-chatbot-window" data-admin-id="<?php echo $chatbotAdminId; ?>">
    <div class="cm-chatbot-header">
        <div class="cm-chatbot-profile">
            <div class="cm-chatbot-avatar">🤖</div>
            <div class="cm-chatbot-info">
                <h3>CampusMarket AI</h3>
                <div class="cm-chatbot-status">
                    <span class="cm-chatbot-status-dot"></span>
                    <span>Online</span>
                </div>
            </div>
        </div>
        <button id="cm-chatbot-close" class="cm-chatbot-close" aria-label="Close chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    
    <div id="cm-chatbot-messages" class="cm-chatbot-messages">
        <!-- Message bubbles are dynamically injected here -->
    </div>
    
    <form id="cm-chatbot-form" class="cm-chatbot-input-bar">
        <input type="text" id="cm-chatbot-input" class="cm-chatbot-input" placeholder="Type a message..." required autocomplete="off">
        <button type="submit" class="cm-chatbot-submit" aria-label="Send message">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 20px; height: 20px; transform: rotate(45deg);"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
    </form>
</div>

<?php
    $chatbotJsPath = __DIR__ . '/../public/js/chatbot.js';
    $chatbotJsVer = assetVersion($chatbotJsPath);
?>
<script src="<?php echo BASE_URL; ?>public/js/chatbot.js?v=<?php echo $chatbotJsVer; ?>"></script>
<?php endif; ?>

</body>
</html>
