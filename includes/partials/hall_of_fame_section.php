<?php
/**
 * Hall of Fame section — shared layout for homepage and donate page.
 *
 * Expects: $donors (array)
 * Optional: $hallShowCta (bool) — show "Become a Supporter" button (homepage only)
 */
if (empty($donors)) {
    return;
}

$hallShowCta = !empty($hallShowCta);
$hallSectionClass = trim($hallSectionClass ?? '');
?>
<section class="hall-of-fame-section mb-12<?php echo $hallSectionClass !== '' ? ' ' . htmlspecialchars($hallSectionClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
    <div class="container">
        <div class="hall-of-fame-panel glass-panel text-center">
            <div class="hall-of-fame-eyebrow inline-flex items-center gap-2 font-bold">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                <?= __('home.wall_supporters') ?>
            </div>

            <h2 class="hall-of-fame-title font-bold"><?= __('home.hall_of_fame') ?></h2>
            <p class="hall-of-fame-desc text-muted">
                <?= __('home.hall_of_fame_desc') ?>
            </p>

            <div class="hall-of-fame-carousel-wrap">
                <?php include __DIR__ . '/hall_of_fame_carousel.php'; ?>
            </div>

            <?php if ($hallShowCta): ?>
            <div class="hall-of-fame-cta">
                <a href="pages/donate.php" class="btn btn-primary hall-of-fame-cta-btn">
                    <?= __('home.become_supporter') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
