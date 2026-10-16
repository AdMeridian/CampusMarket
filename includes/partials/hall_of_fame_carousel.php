<?php
/**
 * Hall of Fame donor display — continuous marquee or centered row if < 5 donors.
 *
 * Expects: $donors (array)
 */
if (empty($donors)) {
    return;
}

$donorCount = count($donors);
$isMarquee = $donorCount >= 5;
$hallDuration = max(24, min(60, $donorCount * 3));

static $hallCarouselCssLoaded = false;
if (!$hallCarouselCssLoaded) {
    $hallCarouselCssLoaded = true;
    $hallCssPath = ROOT_PATH . 'public/css/hall-carousel.css';
    $hallCssVer = assetVersion($hallCssPath);
    echo '<link rel="stylesheet" href="' . BASE_URL . 'public/css/hall-carousel.css?v=' . $hallCssVer . '">' . "\n";
}
?>

<?php if ($isMarquee): ?>
<div class="hall-marquee"
     aria-label="Community Hall of Fame supporters"
     style="--hall-duration: <?php echo (int)$hallDuration; ?>s;">
    <div class="hall-marquee-track">
        <?php for ($copy = 0; $copy < 2; $copy++): ?>
        <div class="hall-marquee-group"<?php echo $copy === 1 ? ' aria-hidden="true"' : ''; ?>>
            <?php foreach ($donors as $donor): ?>
            <div class="hall-marquee-card">
                <div class="hall-marquee-avatar-wrap">
                    <img src="<?php echo avatarUrl($donor['avatar']); ?>"
                         alt="<?php echo sanitize($donor['username']); ?>"
                         class="hall-marquee-avatar"
                         loading="lazy"
                         decoding="async">
                    <div class="hall-marquee-star" aria-hidden="true">★</div>
                </div>
                <p class="hall-marquee-name">@<?php echo sanitize($donor['username']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>
<?php else: ?>
<div class="hall-static-row" aria-label="Community Hall of Fame supporters">
    <?php foreach ($donors as $donor): ?>
    <div class="hall-marquee-card">
        <div class="hall-marquee-avatar-wrap">
            <img src="<?php echo avatarUrl($donor['avatar']); ?>"
                 alt="<?php echo sanitize($donor['username']); ?>"
                 class="hall-marquee-avatar"
                 loading="lazy"
                 decoding="async">
            <div class="hall-marquee-star" aria-hidden="true">★</div>
        </div>
        <p class="hall-marquee-name">@<?php echo sanitize($donor['username']); ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
