<?php
// index.php
require_once 'config/constants.php';
require_once 'includes/header.php';

$pageTitle = "Home";

// Data for homepage
$recentProducts = getRecentProducts($pdo, 8);
$topCategories = getTopCategories($pdo);
?>

<!-- Hero Section with Background Carousel -->
<section class="hero">
    <div class="hero-carousel">
        <div class="hero-slide active" style="background-image: url('public/images/hero/hero1.png');"></div>
        <div class="hero-slide" style="background-image: url('public/images/hero/hero2.png');"></div>
        <div class="hero-slide" style="background-image: url('public/images/hero/hero3.png');"></div>
        <div class="hero-slide" style="background-image: url('public/images/hero/hero4.png');"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <div class="container text-center">
        <h1 style="font-size: 4rem; font-weight: 800; margin-bottom: 1.5rem; text-shadow: 0 4px 12px rgba(0,0,0,0.3); color: white;">The Campus Marketplace</h1>
        <p style="font-size: 1.5rem; max-width: 700px; margin: 0 auto 3rem; font-weight: 500; text-shadow: 0 2px 8px rgba(0,0,0,0.3); color: white; text-align: center;">
            The safest way to buy and sell within your university community.
        </p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-6">
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/browse.php" class="btn" style="background: white; color: var(--primary); padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 700; border-radius: 1rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); width: fit-content;">Start Browsing</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/create_listing.php" class="btn btn-secondary" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 700; border-radius: 1rem; backdrop-filter: blur(8px); width: fit-content;">Sell an Item</a>
            <?php else: ?>
                <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/register.php" class="btn btn-secondary" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 700; border-radius: 1rem; backdrop-filter: blur(8px); width: fit-content;">Join to Sell</a>
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
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-6">
            <?php 
            // Hardcoded categories as requested
            $hardcodedCategories = [
                ['id' => 5,  'name' => 'Kitchen essentials',            'icon' => '🍳'],
                ['id' => 1,  'name' => 'Electronics and accessories',    'icon' => '💻'],
                ['id' => 4,  'name' => 'Clothing and fashion',          'icon' => '👕'],
                ['id' => 3,  'name' => 'Dorms and living essentials',   'icon' => '🪑'],
                ['id' => 10, 'name' => 'Transportation (bikes and scooter)', 'icon' => '🚲'],
                ['id' => 2,  'name' => 'Books and study materials',     'icon' => '📚']
            ];

            foreach ($hardcodedCategories as $cat): 
                // Fetch real count for each hardcoded category
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 'active'");
                $stmt->execute([$cat['id']]);
                $count = $stmt->fetchColumn();
            ?>
                <a href="pages/browse.php?category=<?php echo $cat['id']; ?>" class="card card-hover p-6 flex flex-col items-center justify-center text-center">
                    <div style="background: var(--bg-main); width: 56px; height: 56px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.75rem;">
                        <?php echo $cat['icon']; ?>
                    </div>
                    <strong style="font-size: 1.1rem; margin-bottom: 0.25rem;"><?php echo $cat['name']; ?></strong>
                    <span class="text-muted small"><?php echo $count; ?> items available</span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-12 text-center">
            <a href="pages/categories.php" class="btn btn-outline" style="padding: 0.8rem 2.5rem; border-radius: var(--radius-full); font-weight: 600; font-size: 1rem;">View All Categories</a>
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
                    <span style="background: var(--primary); color: white; padding: 0.4rem; border-radius: var(--radius-md); display: flex;">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </span>
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
            <a href="pages/browse.php" class="btn btn-primary" style="padding: 0.9rem 3rem; border-radius: var(--radius-full); font-weight: 700; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Explore All Listings</a>
        </div>
    </div>
</section>

<!-- Donation Hall of Fame -->
<?php 
$donors = getDonors($pdo, 12);
if (!empty($donors)): 
?>
<section class="mb-24">
    <div class="container">
        <div class="glass-panel py-16 px-8 text-center" style="border-radius: var(--radius-3xl); background: linear-gradient(135deg, rgba(99, 102, 241, 0.04), rgba(168, 85, 247, 0.04)); border: 1px solid rgba(0,0,0,0.03); position: relative; overflow: hidden; text-align: center;">
            
            <div class="inline-flex items-center gap-2 mb-6 font-bold" style="font-size: 0.9rem; color: var(--primary); letter-spacing: 0.05em; text-transform: uppercase;">
                <span style="font-size: 1.1rem; animation: pulse 2s infinite;">❤️</span>
                Wall of Supporters
            </div>
            
            <h2 class="font-bold text-4xl mb-4" style="color: var(--text-main); letter-spacing: -0.02em;">Community Hall of Fame</h2>
            <p class="text-muted text-lg mb-8" style="line-height: 1.6; opacity: 0.8; text-align: center; width: 100%;">
                Our platform thrives because of the generosity of our students. Join these incredible individuals in keeping CampusMarket free for everyone.
            </p>
            
            <div class="flex flex-wrap justify-center gap-8 md:gap-12" style="min-height: 120px; align-items: center;">
                <?php foreach ($donors as $donor): ?>
                    <div class="donor-card" style="transition: var(--transition); cursor: pointer;">
                        <div style="position: relative; display: inline-block;">
                            <img src="<?php echo avatarUrl($donor['avatar']); ?>" 
                                 alt="<?php echo sanitize($donor['username']); ?>"
                                 style="width: 80px; height: 80px; border-radius: 22px; border: 3px solid white; box-shadow: var(--shadow-lg); object-fit: cover; transform: rotate(-3deg); transition: var(--transition); background: white;">
                            <div style="position: absolute; top: -8px; right: -8px; background: #fbbf24; color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border: 2px solid white; box-shadow: var(--shadow-sm); z-index: 2;">
                                ★
                            </div>
                        </div>
                        <p style="font-weight: 800; font-size: 0.9rem; color: var(--text-main); margin-top: 1rem; letter-spacing: -0.01em;">@<?php echo sanitize($donor['username']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-10" style="padding-bottom: 2rem;">
                <a href="pages/donate.php" class="btn btn-primary" style="padding: 1rem 3.5rem; border-radius: var(--radius-full); font-weight: 800; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.2);">
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
