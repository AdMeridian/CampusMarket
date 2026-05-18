</div> <!-- End Main Container -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="<?php echo BASE_URL; ?>/" class="logo" style="font-size: 1.5rem; color: var(--primary); font-weight: 700;">CampusMarket</a>
                <p class="mt-4" style="color: var(--text-muted); font-size: 0.9rem;">
                    The premier student-to-student marketplace. Buy, sell, and trade safely within your university community.
                </p>
            </div>
            
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/browse.php">Browse All</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/categories.php">Categories</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/create_listing.php">Post an Item</a></li>

                    <li><a href="<?php echo BASE_URL; ?>/pages/register.php">Create Account</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/donate.php" class="flex items-center gap-1" style="color: var(--primary); font-weight: 700;">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg> Donate
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/safety.php">Safety Guidelines</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/safety.php#meeting-points">Meeting Points</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/report.php">Report an Issue</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/terms.php">Terms of Service</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/privacy.php">Privacy Policy</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/rules.php">Community Rules</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/safety.php">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> CampusMarket. All rights reserved.</p>
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
