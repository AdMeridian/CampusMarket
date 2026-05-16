</div> <!-- End Main Container -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="<?php echo BASE_URL; ?>/" class="logo" style="font-size: 1.5rem; color: var(--primary); font-weight: 800;">CampusMarket</a>
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
                    <li><a href="<?php echo BASE_URL; ?>/pages/donate.php" style="color: var(--primary); font-weight: 700;">❤️ Donate</a></li>
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
