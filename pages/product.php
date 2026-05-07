<?php
// pages/product.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

$productId = $_GET['id'] ?? 0;

// Fetch Product Details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.username as seller_name, u.id as seller_id, u.created_at as seller_since
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id AND p.status = 'active'
");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    echo '<div class="container mt-16 mb-20 text-center"><div class="glass-panel p-16" style="border-radius: var(--radius-xl);"><div class="text-6xl mb-4 opacity-50">🔍</div><h2 class="mb-2 font-bold text-main">Product not found</h2><p class="text-muted text-lg mb-6">This item may have been sold or removed.</p><a href="browse.php" class="btn btn-primary hover-scale" style="border-radius: var(--radius-full);">Back to Browse</a></div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch Images
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

// Seller Rating + Trust
$rating = getSellerRating($pdo, $product['seller_id']);
$trust = getSellerTrustScore($pdo, (int)$product['seller_id']);
?>

<div class="container mt-8 mb-20 relative">
    
    <!-- Background Accents -->
    <div style="position: absolute; top: -100px; right: -50px; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, rgba(255,255,255,0) 70%); z-index: -1;"></div>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-muted small mb-6 font-medium inline-flex px-4 py-2 rounded-full backdrop-blur-md" style="background: color-mix(in srgb, var(--bg-surface) 70%, transparent); border: 1px solid var(--border-light);">
        <a href="<?php echo BASE_URL; ?>/" class="hover:text-primary transition-colors">Home</a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php" class="hover:text-primary transition-colors">Browse</a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php?category=<?php echo $product['category_id']; ?>" class="hover:text-primary transition-colors"><?php echo sanitize($product['category_name']); ?></a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
        
        <!-- Gallery -->
        <div class="gallery-container sticky top-24" style="align-self: start;">
            <div class="product-gallery-main relative group">
                <?php if (!empty($images)): ?>
                    <img src="<?php echo BASE_URL; ?>/public/<?php echo $images[0]['image_path']; ?>" id="main-image" alt="<?php echo sanitize($product['title']); ?>">
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center text-muted">
                        <svg class="w-24 h-24 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-xl font-medium opacity-50">No Image Available</span>
                    </div>
                <?php endif; ?>
                
                <div style="position: absolute; top: 1.5rem; right: 1.5rem;">
                    <?php $badge = conditionBadge($product['condition']); ?>
                    <span class="badge <?php echo $badge['class']; ?> shadow-md px-4 py-2 font-bold" style="font-size: 0.95rem; backdrop-filter: blur(8px);"><?php echo $badge['label']; ?></span>
                </div>
            </div>
            
            <?php if (count($images) > 1): ?>
                <div class="flex gap-4 mt-6 overflow-x-auto pb-2 custom-scrollbar">
                    <?php foreach ($images as $index => $img): ?>
                        <div class="card p-1 cursor-pointer hover-scale flex-shrink-0 thumbnail-btn <?php echo $index === 0 ? 'ring-2 ring-primary' : ''; ?>" 
                             onclick="updateMainImage('<?php echo BASE_URL; ?>/public/<?php echo $img['image_path']; ?>', this)"
                             style="width: 80px; height: 80px; overflow: hidden; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: all 0.2s;">
                            <img src="<?php echo BASE_URL; ?>/public/<?php echo $img['image_path']; ?>" alt="Thumb" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="flex flex-col">
            <div class="mb-6 border-b border-gray-100 pb-6">
                <p class="text-primary font-bold tracking-widest uppercase small mb-2" style="font-size: 0.8rem;"><?php echo sanitize($product['category_name']); ?></p>
                <h1 class="mb-4 text-main font-bold" style="font-size: 2.75rem; line-height: 1.2; letter-spacing: -0.5px;"><?php echo sanitize($product['title']); ?></h1>
                <div class="flex items-center gap-4">
                    <span style="font-size: 2.1rem; font-weight: 800; color: var(--primary); font-family: 'Inter', sans-serif; letter-spacing: -1px;"><?php echo renderProductPrice($product); ?></span>
                    <span class="text-muted small px-3 py-1 rounded-full font-medium" style="background: var(--bg-main); border: 1px solid var(--border-light);">Listed <?php echo timeAgo($product['created_at']); ?></span>
                </div>
            </div>

            <!-- Seller Card -->
            <div class="glass-panel p-6 mb-8 flex items-center justify-between" style="border-radius: var(--radius-lg); border-left: 4px solid var(--primary); background: linear-gradient(to right, rgba(99,102,241,0.08), var(--bg-surface));">
                <div class="flex items-center gap-4">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.5rem; box-shadow: var(--shadow-md);">
                        <?php echo strtoupper(substr($product['seller_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <p class="text-muted small mb-0 uppercase tracking-wider font-bold" style="font-size: 0.7rem;">Seller</p>
                        <h4 class="mb-0 font-bold text-main" style="font-size: 1.2rem;">@<?php echo sanitize($product['seller_name']); ?></h4>
                        <div class="flex items-center gap-2 text-sm mt-1">
                            <span style="color: #f59e0b; font-weight: bold;">★ <?php echo $rating['avg']; ?></span>
                            <span class="text-muted">(<?php echo $rating['count']; ?> reviews)</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs mt-1">
                            <span class="badge" style="background: rgba(16,185,129,0.14); color: #065f46; font-weight: 700; font-size: 0.68rem; padding: 0.15rem 0.5rem;">
                                <?php echo sanitize($trust['tier']); ?>
                            </span>
                            <span class="text-muted">Trust Score: <?php echo (int)$trust['score']; ?>/100</span>
                        </div>
                    </div>
                </div>
                <a href="profile.php?id=<?php echo $product['seller_id']; ?>" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-full);">View Profile</a>
            </div>

            <div class="glass-panel p-8 mb-8 flex-grow" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); background: var(--bg-surface); border: 1px solid var(--border-light);">
                <h3 class="mb-6 font-bold flex items-center gap-3 border-b border-gray-100 pb-4 text-xl text-main">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                    Description
                </h3>
                <div style="line-height: 1.8; color: var(--text-main); font-size: 1.1rem; text-wrap: pretty; min-height: 150px;">
                    <?php echo nl2br(sanitize($product['description'])); ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 sticky bottom-4 z-10 glass-panel p-4" style="border-radius: var(--radius-xl); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); background: color-mix(in srgb, var(--bg-surface) 95%, transparent); backdrop-filter: blur(10px);">
                <a href="messages.php?other_user_id=<?php echo $product['seller_id']; ?>&product_id=<?php echo $product['id']; ?>" class="btn btn-primary flex-grow justify-center py-4 text-lg shadow-lg hover-scale" style="border-radius: var(--radius-full); font-weight: bold;">
                    Message Seller
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function updateMainImage(src, element) {
    document.getElementById('main-image').src = src;
    
    // Update thumbnail rings
    document.querySelectorAll('.thumbnail-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-primary');
    });
    element.classList.add('ring-2', 'ring-primary');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
