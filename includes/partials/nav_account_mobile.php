<?php
/**
 * Flat account links for the mobile nav drawer.
 * Desktop uses the .user-dropdown popup instead (see header.php).
 *
 * @var callable(string): string $navItemActive
 */
?>
<div class="mobile-account-nav lg-hidden">
    <div class="mobile-nav-section">
        <p class="mobile-nav-section-label"><?= __('nav.menu_shopping') ?></p>
        <a href="<?php echo BASE_URL; ?>pages/my_orders.php" class="mobile-nav-link<?php echo $navItemActive('my_orders.php'); ?>"><?= __('nav.my_orders') ?></a>
        <a href="<?php echo BASE_URL; ?>pages/my_reports.php" class="mobile-nav-link<?php echo $navItemActive('my_reports.php'); ?>"><?= __('nav.my_reports') ?></a>
        <a href="<?php echo BASE_URL; ?>pages/wishlist.php" class="mobile-nav-link<?php echo $navItemActive('wishlist.php'); ?>"><?= __('nav.wishlist') ?></a>
        <a href="<?php echo BASE_URL; ?>pages/promotions.php" class="mobile-nav-link<?php echo $navItemActive('promotions.php'); ?>"><?= __('nav.promotions') ?></a>
    </div>

    <div class="mobile-nav-section">
        <p class="mobile-nav-section-label"><?= __('nav.menu_account') ?></p>
        <a href="<?php echo BASE_URL; ?>pages/profile.php" class="mobile-nav-link<?php echo $navItemActive('profile.php'); ?>"><?= __('nav.my_profile') ?></a>
        <a href="<?php echo BASE_URL; ?>pages/edit_profile.php#preferred_language" class="mobile-nav-link<?php echo $navItemActive('edit_profile.php'); ?>"><?= __('nav.language_settings', ['lang' => SUPPORTED_LANGUAGES[i18nGetLocale()] ?? strtoupper(i18nGetLocale())]) ?></a>
    </div>

    <div class="mobile-nav-section mobile-nav-section--footer">
        <a href="<?php echo BASE_URL; ?>pages/messages.php?other_user_id=1&product_id=0" class="mobile-nav-link mobile-nav-link--support"><?= __('nav.contact_support') ?></a>
        <a href="<?php echo BASE_URL; ?>pages/logout.php" class="mobile-nav-link mobile-nav-link--danger"><?= __('nav.logout') ?></a>
    </div>
</div>
