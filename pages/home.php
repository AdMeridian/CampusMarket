<?php
// pages/home.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __('home.page_title');
$pageDescription = __('seo.home_description');
$seoJsonLd = seoWebsiteJsonLd();

require_once __DIR__ . '/../includes/header.php';

// Data for homepage
$recentProducts = getRecentProducts($pdo, HOME_RECENT_LISTING_LIMIT, HOME_RECENT_LISTING_DAYS);
$displayCats = getHomepageCategorySections($pdo, HOME_CATEGORY_SECTION_LIMIT, HOME_PRODUCTS_PER_CATEGORY);
?>

<!-- Hero Section with Background Carousel -->
<section class="hero">
    <div class="hero-carousel">
        <div class="hero-slide active" style="background-image: url('<?php echo BASE_URL; ?>public/images/hero/hero1.png');"></div>
        <div class="hero-slide" style="background-image: url('<?php echo BASE_URL; ?>public/images/hero/hero2.png');"></div>
        <div class="hero-slide" style="background-image: url('<?php echo BASE_URL; ?>public/images/hero/hero3.png');"></div>
        <div class="hero-slide" style="background-image: url('<?php echo BASE_URL; ?>public/images/hero/hero4.png');"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <div class="container text-center hero-content">
        <h1 class="hero-title"><?= __('home.hero_title') ?></h1>
        <p class="hero-subtitle"><?= __('home.hero_desc') ?></p>
        <div class="hero-actions">
            <a href="<?php echo BASE_URL; ?>pages/browse.php" class="btn hero-cta hero-cta--primary"><?= __('home.start_browsing') ?></a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo BASE_URL; ?>pages/create_listing.php" class="btn hero-cta hero-cta--secondary"><?= __('home.sell_an_item') ?></a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>pages/register.php" class="btn hero-cta hero-cta--secondary"><?= __('home.join_to_sell') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Hero Carousel Logic
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    let currentSlide = 0;
    
    function nextSlide() {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }
    
    // Change slide every 5 seconds
    setInterval(nextSlide, 5000);
});
</script>

<!-- Category Quick Access -->
<section class="home-section mt-12">
    <div class="container">
        <div class="home-section__header">
            <h2 class="home-section__title mb-0"><?= __('home.shop_by_category') ?></h2>
            <a href="categories.php" class="text-muted" style="font-weight: 500;"><?= __('home.view_all') ?></a>
        </div>
        <div class="scroll-row">
            <?php 
            // Hardcoded categories as requested
            $hardcodedCategories = [
                ['id' => 5,  'name' => 'Kitchen essentials',            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>'],
                ['id' => 1,  'name' => 'Electronics and accessories',    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>'],
                ['id' => 4,  'name' => 'Clothing and fashion',          'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a8 8 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>'],
                ['id' => 3,  'name' => 'Dorms and living essentials',   'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
                ['id' => 10, 'name' => 'Transportation',                'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"/></svg>'],
                ['id' => 2,  'name' => 'Books and study materials',     'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>']
            ];

            foreach ($hardcodedCategories as $cat): 
                // Fetch real count for each hardcoded category
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 'active'");
                $stmt->execute([$cat['id']]);
                $count = $stmt->fetchColumn();
            ?>
                <a href="browse.php?category=<?php echo $cat['id']; ?>" class="card card-hover p-6 flex flex-col items-center justify-center text-center">
                    <div style="color: var(--text-muted); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <?php echo $cat['icon']; ?>
                    </div>
                    <strong class="home-category-card__name"><?php echo translateCategory($cat['name']); ?></strong>
                    <span class="text-muted small"><?= __('home.items_available', ['count' => $count]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-12 text-center">
            <a href="categories.php" class="btn btn-outline" style="padding: 0.8rem 2.5rem; border-radius: var(--radius-lg); font-weight: 600; font-size: 1rem;"><?= __('home.view_all_categories') ?></a>
        </div>
    </div>
</section>

<!-- Featured Spotlight (Paid Ads) -->
<?php 
$featuredProducts = getFeaturedProducts($pdo, 6);
if (!empty($featuredProducts)): 
?>
<section class="home-section home-section--featured mt-12">
    <div class="container">
        <div class="home-section__header home-section__header--center">
            <div>
                <h2 class="home-section__title home-section__title--with-icon mb-1">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="color: var(--primary)"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    <?= __('home.featured_spotlight') ?>
                </h2>
                <p class="text-muted mb-0"><?= __('home.featured_desc') ?></p>
            </div>
            <a href="promotions.php" class="btn btn-outline btn-sm" style="font-size: 0.8rem; padding: 0.4rem 1rem;"><?= __('home.promote_listing') ?></a>
        </div>

        <div class="scroll-row">
            <?php foreach ($featuredProducts as $prod): ?>
                <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Recent Products -->
<section class="home-section mt-12 mb-12">
    <div class="container">
        <div class="home-section__header">
            <h2 class="home-section__title mb-0"><?= __('home.recent_listings') ?></h2>
            <a href="browse.php" class="btn btn-secondary btn-sm"><?= __('home.see_everything') ?></a>
        </div>

        <?php if (empty($recentProducts)): ?>
            <div class="col-span-full text-center py-12 bg-white rounded-lg border">
                <p class="text-muted"><?= __('home.no_products_desc') ?></p>
                <?php if (isLoggedIn()): ?>
                    <a href="create_listing.php" class="btn btn-primary"><?= __('home.create_listing') ?></a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary"><?= __('home.join_sell') ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
                <div
                    class="listing-carousel"
                    data-listing-carousel
                    data-label-prev="<?= htmlspecialchars(__('home.carousel_prev'), ENT_QUOTES, 'UTF-8') ?>"
                    data-label-next="<?= htmlspecialchars(__('home.carousel_next'), ENT_QUOTES, 'UTF-8') ?>"
                >
                    <button type="button" class="listing-carousel__nav listing-carousel__nav--prev" aria-label="<?= htmlspecialchars(__('home.carousel_prev'), ENT_QUOTES, 'UTF-8') ?>" hidden>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <div class="scroll-row listing-carousel__track">
                        <?php foreach ($recentProducts as $prod): ?>
                            <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="listing-carousel__nav listing-carousel__nav--next" aria-label="<?= htmlspecialchars(__('home.carousel_next'), ENT_QUOTES, 'UTF-8') ?>" hidden>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
            </div>
        <?php endif; ?>

        <div class="mt-12 text-center">
            <a href="browse.php" class="btn btn-primary" style="padding: 0.9rem 3rem; border-radius: var(--radius-md); font-weight: 600;"><?= __('home.explore_all') ?></a>
        </div>
    </div>
</section>

<!-- Category Highlights -->
<section class="home-section mt-12">
    <div class="container">
        <?php foreach ($displayCats as $cat): ?>
            <div class="home-category-block mb-12">
                <div class="home-section__header home-section__header--bordered">
                    <h2 class="home-section__title mb-0"><?php echo htmlspecialchars(translateCategory($cat['name'])); ?></h2>
                </div>
                <div class="scroll-row">
                    <?php foreach ($cat['products'] as $prod): ?>
                        <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
                    <?php endforeach; ?>
                </div>
                <a href="browse.php?category=<?php echo $cat['id']; ?>" class="home-section__see-all text-primary"><?= __('home.see_all_category', ['category' => translateCategory($cat['name'])]) ?></a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Donation Hall of Fame -->
<?php
$donors = getDonors($pdo, 12);
$hallShowCta = true;
if (!empty($donors)) {
    include __DIR__ . '/../includes/partials/hall_of_fame_section.php';
}
?>

<?php
$listingCarouselJsPath = __DIR__ . '/../public/js/listing-carousel.js';
$listingCarouselJsVer = file_exists($listingCarouselJsPath) ? filemtime($listingCarouselJsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>public/js/listing-carousel.js?v=<?php echo $listingCarouselJsVer; ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
