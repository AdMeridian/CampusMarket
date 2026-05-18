<?php
// index.php
require_once 'config/constants.php';
require_once 'includes/header.php';

$pageTitle = "Home";

// Data for homepage
$recentProducts = getRecentProducts($pdo, 8);
$topCategories = getTopCategories($pdo);

// Fetch categories and their products (5 each) — done in PHP before HTML output
$stmtCats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC LIMIT 4");
$displayCats = $stmtCats->fetchAll();
foreach ($displayCats as &$dcat) {
    $stmtCatP = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.image_path
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.category_id = ? AND p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmtCatP->execute([$dcat['id']]);
    $dcat['products'] = $stmtCatP->fetchAll();
}
unset($dcat);
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
    
    <div class="container text-center">
        <h1 style="font-size: 4rem; font-weight: 700; margin-bottom: 1.5rem; color: white;">The Campus Marketplace</h1>
        <p style="font-size: 1.5rem; max-width: 700px; margin: 0 auto 3rem; font-weight: 400; color: white; text-align: center;">
            The safest way to buy and sell within your university community.
        </p>
        <div class="flex flex-col sm-flex-row justify-center items-center gap-6">
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/browse.php" class="btn" style="background: white; color: var(--primary); padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 600; border-radius: var(--radius-md); width: fit-content;">Start Browsing</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/create_listing.php" class="btn btn-secondary" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 600; border-radius: var(--radius-md); width: fit-content;">Sell an Item</a>
            <?php else: ?>
                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/register.php" class="btn btn-secondary" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 600; border-radius: var(--radius-md); width: fit-content;">Join to Sell</a>
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
<section class="mt-12">
    <div class="container">
        <div class="flex justify-between items-end mb-8">
            <h2 class="mb-0">Shop by Category</h2>
            <a href="pages/categories.php" class="text-muted" style="font-weight: 500;">View all</a>
        </div>
        <div class="grid grid-cols-3 md-grid-cols-3 lg-grid-cols-3 gap-6">
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
                <a href="pages/browse.php?category=<?php echo $cat['id']; ?>" class="card card-hover p-6 flex flex-col items-center justify-center text-center">
                    <div style="color: var(--text-muted); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <?php echo $cat['icon']; ?>
                    </div>
                    <strong style="font-size: 1.1rem; margin-bottom: 0.25rem;"><?php echo $cat['name']; ?></strong>
                    <span class="text-muted small"><?php echo $count; ?> items available</span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-12 text-center">
            <a href="pages/categories.php" class="btn btn-outline" style="padding: 0.8rem 2.5rem; border-radius: var(--radius-lg); font-weight: 600; font-size: 1rem;">View All Categories</a>
        </div>
    </div>
</section>

<!-- Featured Spotlight (Paid Ads) -->
<?php 
$featuredProducts = getFeaturedProducts($pdo, 6);
if (!empty($featuredProducts)): 
?>
<section class="mt-16 py-12" style="background: rgba(99, 102, 241, 0.03); border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light);">
    <div class="container">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="mb-1" style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="color: var(--primary)"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    Featured Spotlight
                </h2>
                <p class="text-muted mb-0">Premium listings currently being promoted by our community</p>
            </div>
            <a href="pages/promotions.php" class="btn btn-outline btn-sm" style="font-size: 0.8rem; padding: 0.4rem 1rem;">Promote Your Listing</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
            <?php foreach ($featuredProducts as $prod): ?>
                <div class="featured-card-wrap">
                    <?php include 'includes/product_card_template.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Recent Products -->
<section class="mt-16 mb-16">
    <div class="container">
        <div class="flex justify-between items-end mb-8">
            <h2 class="mb-0">Recent Listings</h2>
            <a href="pages/browse.php" class="btn btn-secondary btn-sm">See everything</a>
        </div>

        <div class="grid grid-cols-1 sm-grid-cols-2 md-grid-cols-3 lg-grid-cols-4 gap-6">
            <?php if (empty($recentProducts)): ?>
                <div class="col-span-full text-center py-12 bg-white rounded-lg border">
                    <p class="text-muted">No products listed yet. Be the first to sell something!</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="pages/create_listing.php" class="btn btn-primary">Create Listing</a>
                    <?php else: ?>
                        <a href="pages/register.php" class="btn btn-primary">Join & Sell</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($recentProducts as $prod): ?>
                    <?php include 'includes/product_card_template.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-12 text-center">
            <a href="pages/browse.php" class="btn btn-primary" style="padding: 0.9rem 3rem; border-radius: var(--radius-md); font-weight: 600;">Explore All Listings</a>
        </div>
    </div>
</section>

<!-- Category Highlights -->
<section class="mt-20">
    <div class="container">
        <?php foreach ($displayCats as $cat): ?>
            <?php if (empty($cat['products'])) continue; ?>
            <div class="mb-16">
                <div class="flex justify-between items-end mb-6" style="border-bottom: 2px solid #f1f5f9; padding-bottom: 1rem;">
                    <h2 class="mb-0"><?php echo htmlspecialchars($cat['name']); ?></h2>
                    <a href="pages/browse.php?category=<?php echo $cat['id']; ?>" class="text-primary font-bold">See all <?php echo htmlspecialchars($cat['name']); ?> &rarr;</a>
                </div>
                <div class="grid grid-cols-1 sm-grid-cols-2 md-grid-cols-3 lg-grid-cols-5 gap-6">
                    <?php foreach ($cat['products'] as $prod): ?>
                        <?php include 'includes/product_card_template.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Donation Hall of Fame -->
<?php 
$donors = getDonors($pdo, 12);
if (!empty($donors)): 
?>
<section class="mb-24">
    <div class="container">
        <div class="glass-panel py-16 px-8 text-center" style="border-radius: var(--radius-lg); background: var(--bg-card); border: 1px solid var(--border-light); position: relative; overflow: hidden; text-align: center;">
            
            <div class="inline-flex items-center gap-2 mb-6 font-bold" style="font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                Wall of Supporters
            </div>
            
            <h2 class="font-bold text-4xl mb-4" style="color: var(--text-main);">Community Hall of Fame</h2>
            <p class="text-muted text-lg mb-8" style="line-height: 1.6; text-align: center; width: 100%;">
                Our platform thrives because of the generosity of our students. Join these incredible individuals in keeping CampusMarket free for everyone.
            </p>
            
            <div class="flex flex-wrap justify-center gap-8 md:gap-12" style="min-height: 120px; align-items: center;">
                <?php foreach ($donors as $donor): ?>
                    <div class="donor-card" style="transition: var(--transition); cursor: pointer;">
                        <div style="position: relative; display: inline-block;">
                            <img src="<?php echo avatarUrl($donor['avatar']); ?>" 
                                 alt="<?php echo sanitize($donor['username']); ?>"
                                 style="width: 80px; height: 80px; border-radius: 22px; border: 3px solid white; box-shadow: var(--shadow-lg); object-fit: cover; transform: rotate(-3deg); transition: var(--transition); background: white;">
                            <div style="position: absolute; top: -8px; right: -8px; background: #fbbf24; color: white; width: 26px; height: 26px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border: 2px solid white; box-shadow: var(--shadow-sm); z-index: 2;">
                                ★
                            </div>
                        </div>
                        <p style="font-weight: 800; font-size: 0.9rem; color: var(--text-main); margin-top: 1rem; letter-spacing: -0.01em;">@<?php echo sanitize($donor['username']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-10" style="padding-bottom: 2rem;">
                <a href="pages/donate.php" class="btn btn-primary" style="padding: 1rem 3.5rem; border-radius: var(--radius-md); font-weight: 600;">
                    Become a Supporter
                </a>
            </div>
        </div>
    </div>
</section>

<style>
.donor-card:hover {
    transform: translateY(-6px);
}
.donor-card:hover img {
    transform: rotate(0deg);
    border-color: var(--primary-light);
}
</style>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
