</div> <!-- End Main Container -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="<?php echo BASE_URL; ?>/" class="logo" style="font-size: 1.5rem; color: var(--primary); font-weight: 700;">CampusMarket</a>
                <p class="mt-4" style="color: var(--text-muted); font-size: 0.9rem;">
                    <?= __('footer.tagline') ?>
                </p>
            </div>
            
            <div class="footer-col">
                <h4><?= __('footer.quick_links') ?></h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/browse.php"><?= __('footer.browse_all') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/categories.php"><?= __('footer.categories') ?></a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/create_listing.php"><?= __('footer.post_item') ?></a></li>

                    <li><a href="<?php echo BASE_URL; ?>/pages/register.php"><?= __('footer.create_account') ?></a></li>
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

<script>const PWA_SW_URL = "<?php echo BASE_URL; ?>sw.js";</script>
<?php
    $pwaJsPath = __DIR__ . '/../public/js/pwa.js';
    $pwaJsVer = file_exists($pwaJsPath) ? filemtime($pwaJsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>public/js/pwa.js?v=<?php echo $pwaJsVer; ?>"></script>

</body>
</html>
