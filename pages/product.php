<?php
// pages/product.php
require_once __DIR__ . '/../includes/bootstrap.php';

$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    redirect(BASE_URL . 'pages/browse.php');
}

// Fetch Product Details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.username as seller_name, u.id as seller_id, u.avatar as seller_avatar, u.created_at as seller_since
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id AND p.status = 'active'
");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    $pageTitle = "Product Not Found";
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container mt-16 mb-20 text-center"><div class="glass-panel p-16" style="border-radius: var(--radius-xl);"><div class="text-6xl mb-4 opacity-50">🔍</div><h2 class="mb-2 font-bold text-main">Product not found</h2><p class="text-muted text-lg mb-6">This item may have been sold or removed.</p><a href="browse.php" class="btn btn-primary hover-scale" style="border-radius: var(--radius-lg);">Back to Browse</a></div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Increment View Count (Unique per student)
$canCountView = false;
$currentUid = isLoggedIn() ? (int)currentUserId() : null;

if ($currentUid) {
    // Check if this student has already viewed this product
    $viewCheck = $pdo->prepare("SELECT 1 FROM product_views WHERE product_id = ? AND user_id = ?");
    $viewCheck->execute([$productId, $currentUid]);
    if (!$viewCheck->fetch()) {
        // First time viewing! Record it and increment the counter
        $insView = $pdo->prepare("INSERT INTO product_views (product_id, user_id) VALUES (?, ?)");
        $insView->execute([$productId, $currentUid]);
        $canCountView = true;
    }
} else {
    // For guests, use a persistent cookie to track views (survives logout/login)
    $guestViews = [];
    if (isset($_COOKIE['cm_pv'])) {
        $guestViews = json_decode($_COOKIE['cm_pv'], true) ?: [];
    }
    
    if (!in_array($productId, $guestViews)) {
        $guestViews[] = $productId;
        setcookie('cm_pv', json_encode($guestViews), time() + (86400 * 30), '/');
        $canCountView = true;
    }
}

if ($canCountView) {
    $updateViews = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $updateViews->execute([$productId]);
    $product['views']++; 
}

$isOwner = isLoggedIn() && (int)currentUserId() === (int)$product['seller_id'];

// Handle Price Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'update_price') {
    $newPrice = (float)($_POST['new_price'] ?? 0);
    if ($newPrice > 0) {
        $stmtUp = $pdo->prepare("UPDATE products SET price = :price, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':price' => $newPrice, ':id' => $productId]);
        setFlash('success', 'Price updated successfully!');
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    } else {
        setFlash('error', 'Price must be greater than zero.');
    }
}

// Handle Mark as Sold (Now moves to bin too)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'mark_sold') {
    $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$productId])) {
        setFlash('success', 'Product marked as sold and moved to Recycle Bin!');
        redirect(BASE_URL . 'pages/recycle_bin.php');
    }
}

// Handle Delete Listing (Move to Recycle Bin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$productId])) {
        setFlash('success', 'Listing moved to Recycle Bin. You can restore it within 30 days.');
        redirect(BASE_URL . 'pages/recycle_bin.php');
    }
}

// Fetch Images
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

// Fetch Seller Stats (for SCC)
$rating = getSellerRating($pdo, (int)$product['seller_id']);
$trust  = getSellerTrustScore($pdo, (int)$product['seller_id']);

// Fetch REAL unique view count from product_views table
$stmtViews = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ?");
$stmtViews->execute([$productId]);
$uniqueViewCount = (int)$stmtViews->fetchColumn();

// Fetch Wishlist count
$stmtWish = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE product_id = ?");
$stmtWish->execute([$productId]);
$wishlistCount = (int)$stmtWish->fetchColumn();

// CUMULATIVE view counts for graph — each point is total views up to that day
// Points: [5 days ago, 4 days ago, 3 days ago, 2 days ago, yesterday, today]
$viewCumPoints = [];
$wishCumPoints = [];
for ($d = 5; $d >= 0; $d--) {
    $sv = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ? AND viewed_at <= NOW() - (? * INTERVAL '1 day')");
    $sv->execute([$productId, $d]);
    $viewCumPoints[] = (int)$sv->fetchColumn();

    $sw = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE product_id = ? AND created_at <= NOW() - (? * INTERVAL '1 day')");
    $sw->execute([$productId, $d]);
    $wishCumPoints[] = (int)$sw->fetchColumn();
}

// Map cumulative counts to SVG Y coordinates
// SVG viewBox "0 0 100 40": Y=39 = bottom, Y=5 = near top
// We cap the maximum visual rise at 28 units so even 1 event shows clearly
function cumulToSvgY(array $points, float $bottom = 39.0, float $maxRise = 28.0): array {
    $max = max($points) ?: 1; // avoid div by zero; if all zero, normalise to 1
    $result = [];
    foreach ($points as $v) {
        $result[] = round($bottom - ($v / $max) * $maxRise, 2);
    }
    return $result;
}

$viewY = cumulToSvgY($viewCumPoints);
$wishY = cumulToSvgY($wishCumPoints);

// X positions for 6 evenly-spaced points
$xPos = [0, 20, 40, 60, 80, 100];

// Check if current user has this in wishlist
$isSaved = false;
if (isLoggedIn()) {
    $stmtSaved = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmtSaved->execute([currentUserId(), $productId]);
    $isSaved = (bool)$stmtSaved->fetch();
}

$pageTitle = $product['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Graph line draw animation */
@keyframes drawLine {
    from { stroke-dashoffset: 200; }
    to   { stroke-dashoffset: 0; }
}
.graph-line {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: drawLine 1.4s ease-out forwards;
}
.graph-line-wish {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: drawLine 1.4s ease-out 0.2s forwards;
}
@media (min-width: 1024px) {
    .scc-wrapper {
        width: min(1060px, 100%);
        margin-left: auto;
    }
}

@media (max-width: 1023.98px) {
    .scc-wrapper {
        width: 100%;
        margin-left: 0;
    }
}

.scc-seller-card {
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}

.scc-main-card {
    border-radius: 20px;
    border: 1px solid #e9eef7;
    background: #fff;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}

.scc-colorful-shell {
    background: var(--bg-surface);
    position: relative;
    overflow: hidden;
}

.scc-colorful-shell::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(168, 85, 247, 0.03) 100%);
    pointer-events: none;
}

.scc-metric-blue {
    background: linear-gradient(145deg, #ffffff 0%, #f4f8ff 100%);
    border-color: #e3ecff !important;
}

.scc-metric-violet {
    background: linear-gradient(145deg, #ffffff 0%, #f7f3ff 100%);
    border-color: #ece5ff !important;
}
</style>

<div class="container pt-24 mb-20 relative">
    <?php if ($isOwner): ?>
        <div class="seller-management-banner" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; padding: 1.25rem 2rem; border-radius: 20px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.25); border: 1px solid rgba(255,255,255,0.1);">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 flex items-center justify-center text-2xl" style="border-radius: var(--radius-xl); background: rgba(255,255,255,0.2);">⚙️</div>
                <div>
                    <h4 class="mb-0 font-bold" style="line-height: 1.2; font-size: 1.25rem; color: white;">Management Mode</h4>
                    <p class="mb-0 opacity-90 small" style="color: white; font-weight: 500;">You are viewing your own listing. Only you can see these controls.</p>
                </div>
            </div>
            <a href="profile.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">Go to Dashboard</a>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-muted small mb-6 font-medium inline-flex px-4 py-2 rounded-xl backdrop-blur-md" style="background: color-mix(in srgb, var(--bg-surface) 70%, transparent); border: 1px solid var(--border-light);">
        <a href="<?php echo BASE_URL; ?>/" class="hover:text-primary transition-colors">Home</a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php" class="hover:text-primary transition-colors">Browse</a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php?category=<?php echo $product['category_id']; ?>" class="hover:text-primary transition-colors"><?php echo sanitize($product['category_name']); ?></a>
    </div>

    <div class="grid grid-cols-1 lg-grid-cols-2 gap-12 lg-gap-16">
        
        <!-- Gallery -->
        <div class="gallery-container sticky top-24" style="align-self: start;">
            <div class="product-gallery-main relative group">
                <?php if (!empty($images)): ?>
                    <img src="<?php echo getProductImage($images[0]['image_path']); ?>" id="main-image" alt="<?php echo sanitize($product['title']); ?>">
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
                             onclick="updateMainImage('<?php echo getProductImage($img['image_path']); ?>', this)"
                             style="width: 80px; height: 80px; overflow: hidden; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: all 0.2s;">
                            <img src="<?php echo getProductImage($img['image_path']); ?>" alt="Thumb" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
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
                    
                    <form action="../actions/toggle_wishlist.php" method="POST" style="display: inline-block;">
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                        <button type="submit" class="hover-scale" style="background: <?php echo $isSaved ? '#fff1f2' : 'var(--bg-main)'; ?>; border: 1px solid <?php echo $isSaved ? '#fecdd3' : 'var(--border-light)'; ?>; color: <?php echo $isSaved ? '#e11d48' : 'var(--text-muted)'; ?>; padding: 0.6rem 1rem; border-radius: var(--radius-lg); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; gap: 0.5rem; box-shadow: var(--shadow-sm);">
                            <svg class="w-6 h-6" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24" style="width: 22px; height: 22px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <span style="font-weight: 800; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo $isSaved ? 'Saved' : 'Save'; ?></span>
                        </button>
                    </form>

                    <span class="text-muted small px-3 py-1 rounded-lg font-medium" style="background: var(--bg-main); border: 1px solid var(--border-light);">Listed <?php echo timeAgo($product['created_at']); ?></span>
                </div>
            </div>

            <!-- SELLER COMMAND CENTER -->
            <?php if ($isOwner): ?>
            <div class="scc-wrapper">
                
                <!-- TOP SELLER CARD -->
                <div class="scc-seller-card" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid #dbe6f6; border-left: 4px solid #3b82f6; border-radius: 16px; padding: 1.2rem 1.4rem; background: #fff; margin-bottom: 1rem;">
                    <div class="flex items-center" style="gap: 14px;">
                        <img src="<?php echo avatarUrl($product['seller_avatar']); ?>" 
                             alt="<?php echo sanitize($product['seller_name']); ?>"
                             style="width: 74px; height: 74px; border-radius: 16px; object-fit: cover; box-shadow: 0 8px 18px rgba(0,0,0,0.08); border: 2px solid white;">
                        <div style="margin-left: 4px;">
                            <h4 class="m-0 font-bold text-slate-900" style="font-size: 2.25rem; line-height: 1.05; letter-spacing: -0.01em;">@<?php echo sanitize($product['seller_name']); ?></h4>
                            <div class="flex items-center gap-3 text-[0.95rem] font-bold mt-1">
                                <div class="flex items-center gap-1">
                                    <span style="color: #f59e0b;">&#9733;</span>
                                    <span class="text-slate-800"><?php echo number_format($rating['avg'], 1); ?></span>
                                    <span class="text-slate-400 font-medium">(<?php echo $rating['count']; ?> review<?php echo $rating['count'] !== 1 ? 's' : ''; ?>)</span>
                                </div>
                                <span style="background: #ecfdf5; color: #10b981; padding: 0.2rem 0.75rem; border-radius: 10px; font-size: 0.78rem;">
                                    <?php echo $rating['count'] > 5 ? 'Trusted Seller' : 'New Seller'; ?>
                                </span>
                                <div class="text-slate-700">Trust Score: <span class="font-bold"><?php echo (int)$trust['score']; ?>/100</span> <span style="opacity: 0.35; cursor: help;" title="<?php echo sanitize($trust['tier']); ?>">&#9432;</span></div>
                            </div>
                        </div>
                    </div>
                    <a href="profile.php" class="flex items-center gap-2 px-6 py-2.5 border border-slate-200 rounded-xl font-bold text-slate-600 text-sm hover:bg-slate-50 transition-all" style="min-width: 168px; justify-content: center;">
                        View Profile <span style="opacity: 0.45; font-size: 0.8rem; margin-left: 4px;">&#10095;</span>
                    </a>
                </div>
                <!-- MAIN INSIGHTS BOX -->
                <div class="bg-white scc-main-card scc-colorful-shell" style="padding: 2rem; margin-bottom: 2rem; margin-left: auto;">
                    <!-- Insights Header -->
                    <div class="flex items-start justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 flex items-center justify-center text-indigo-500" style="border-radius: var(--radius-xl); background: var(--bg-main);">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <div>
                                <h3 class="m-0 font-black text-slate-800" style="font-size: 1.4rem;">Listing Insights</h3>
                                <p class="m-0 text-slate-400 font-bold" style="font-size: 0.9rem;">Live Performance Center</p>
                            </div>
                        </div>
                        <span style="font-size: 0.65rem; font-weight: 900; color: #94a3b8; background: #f8fafc; padding: 0.25rem 0.6rem; border-radius: 6px; letter-spacing: 0.05em; border: 1px solid #f1f5f9;">SELLER</span>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-1 md-grid-cols-2 gap-5 mb-7">
                        <!-- Card 1: Total Reach -->
                        <div class="p-5 rounded-[1rem] relative border border-[#edf2fb] bg-white shadow-sm overflow-hidden scc-metric-blue">
                            <div class="relative z-10">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="flex items-center justify-center text-indigo-600 shadow-sm" style="width: 48px; height: 48px; border-radius: var(--radius-xl); background: var(--bg-main);">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </div>
                                    <span class="text-[0.95rem] font-bold text-slate-600">Total Reach</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-4xl font-black text-slate-800 m-0 count-up" data-value="<?php echo $uniqueViewCount; ?>">0</h2>
                                </div>
                                <p class="text-[0.75rem] font-bold text-slate-400 m-0 mt-1">Unique student views</p>
                            </div>
                            <?php
                                // Build SVG path from real daily data
                                $vPath = '';
                                $vFill = '';
                                for ($i = 0; $i < 6; $i++) {
                                    $cmd = $i === 0 ? 'M' : 'L';
                                    $vPath .= "$cmd {$xPos[$i]} {$viewY[$i]} ";
                                }
                                $vFill = $vPath . "L 100 40 L 0 40 Z";
                            ?>
                            <svg class="absolute bottom-0 right-0 w-full h-14 opacity-70" viewBox="0 0 100 40" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="reachFill" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#2563eb" stop-opacity="0.25"/>
                                        <stop offset="100%" stop-color="#2563eb" stop-opacity="0.02"/>
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $vFill; ?>" fill="url(#reachFill)"/>
                                <path d="<?php echo $vPath; ?>" class="graph-line" stroke="#1d4ed8" stroke-width="1.5" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
                            </svg>
                        </div>

                        <!-- Card 2: Student Interest -->
                        <div class="p-5 rounded-[1rem] relative border border-[#edf2fb] bg-white shadow-sm overflow-hidden scc-metric-violet">
                            <div class="relative z-10">
                                <div class="flex items-center gap-4 mb-4">
                                    <!-- Owner sees a read-only heart — not clickable, so they can't self-save -->
                                    <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 shadow-sm">
                                        <svg class="w-6 h-6" fill="<?php echo $wishlistCount > 0 ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    </div>
                                    <span class="text-[0.95rem] font-bold text-slate-600">Student Interest</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-4xl font-black text-slate-800 m-0 count-up" data-value="<?php echo $wishlistCount; ?>">0</h2>
                                    <span class="text-emerald-500 font-black text-[0.8rem] flex items-center gap-1">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                        LIVE
                                    </span>
                                </div>
                                <p class="text-[0.75rem] font-bold text-slate-400 m-0 mt-1">Students who saved this</p>
                            </div>
                            <?php
                                // Build SVG path from real daily wishlist data
                                $wPath = '';
                                for ($i = 0; $i < 6; $i++) {
                                    $cmd = $i === 0 ? 'M' : 'L';
                                    $wPath .= "$cmd {$xPos[$i]} {$wishY[$i]} ";
                                }
                                $wFill = $wPath . "L 100 40 L 0 40 Z";
                            ?>
                            <svg class="absolute bottom-0 right-0 w-full h-14 opacity-70" viewBox="0 0 100 40" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="interestFill" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.25"/>
                                        <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.02"/>
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wFill; ?>" fill="url(#interestFill)"/>
                                <path d="<?php echo $wPath; ?>" class="graph-line-wish" stroke="#9333ea" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                    <!-- PRICING STRATEGY -->
                    <div class="mb-8">
                        <h4 class="font-bold text-slate-800 mb-4" style="font-size: 1.15rem;">Current Pricing Strategy</h4>
                        <form method="post" class="flex flex-wrap items-center gap-4">
                            <input type="hidden" name="action" value="update_price">
                            <div class="flex items-center bg-white border border-slate-200 px-4" style="border-radius: 10px; height: 38px; min-width: 120px;">
                                <span class="text-slate-400 font-bold mr-1" style="font-size: 0.8rem;">&#8377;</span>
                                <input type="number" name="new_price" step="0.01" value="<?php echo (float)$product['price']; ?>" 
                                       style="width: 100%; background: transparent; border: none; font-size: 0.95rem; font-weight: 800; color: #1e293b; outline: none;" required>
                            </div>
                            <button type="submit" class="flex items-center gap-2 font-black text-[0.72rem] uppercase tracking-[0.14em] transition-all hover:brightness-95 shadow-sm" style="height: 38px; color: #4f46e5; background: linear-gradient(180deg, #eef2ff 0%, #e0e7ff 100%); border: 1px solid #c7d2fe; padding: 0 1rem; border-radius: 10px; cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                UPDATE PRICE
                            </button>
                        </form>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <form method="post" onsubmit="return confirm('Mark as sold?')">
                            <input type="hidden" name="action" value="mark_sold">
                            <button type="submit" class="flex items-center gap-2 font-black text-[0.72rem] uppercase tracking-[0.14em] transition-all hover:brightness-95 shadow-sm" style="height: 38px; color: #059669; background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0; padding: 0 1rem; border-radius: 10px; cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                MARK AS SOLD
                            </button>
                        </form>
                        
                        <form method="post" onsubmit="return confirm('Delete listing?')">
                            <input type="hidden" name="action" value="delete_listing">
                            <button type="submit" class="flex items-center gap-2 font-black text-[0.72rem] uppercase tracking-[0.14em] transition-all hover:brightness-95" style="height: 38px; color: #e11d48; background: linear-gradient(180deg, #fff1f2 0%, #ffe4e6 100%); border: 1px solid #fecdd3; padding: 0 0.75rem; border-radius: 10px; cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                DELETE LISTING
                            </button>
                        </form>
                    </div>

                    <!-- FOOTER NAVIGATION -->
                    <a href="profile.php" class="inline-flex items-center gap-2 text-indigo-500 font-bold text-[1rem] mt-12 hover:translate-x-[-4px] transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke-width: 3;"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Return to Dashboard
                    </a>
                </div>
            </div>
            <?php endif; ?>

                <!-- DESCRIPTION CARD (BOTTOM) -->
                <div class="bg-white p-10 mt-8" style="border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.01); border: 1px solid #f1f5f9;">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-10 h-10 flex items-center justify-center text-blue-500" style="border-radius: var(--radius-xl); background: var(--bg-main);">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <h3 class="m-0 font-bold text-slate-800" style="font-size: 1.4rem;">Product Description</h3>
                    </div>
                    <div style="line-height: 2; color: #64748b; font-size: 1.15rem;">
                        <?php echo nl2br(sanitize($product['description'])); ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons for Buyer -->
            <?php if (!$isOwner): ?>
                <div class="flex flex-col gap-4 sticky bottom-4 z-10 glass-panel p-4" style="border-radius: var(--radius-xl); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); background: color-mix(in srgb, var(--bg-surface) 95%, transparent); backdrop-filter: blur(10px);">
                    <a href="messages.php?other_user_id=<?php echo $product['seller_id']; ?>&product_id=<?php echo $product['id']; ?>" class="btn btn-primary flex-grow justify-center py-4 text-lg shadow-lg hover-scale" style="border-radius: var(--radius-lg); font-weight: bold;">
                        Message Seller
                    </a>
                </div>
            <?php endif; ?>
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

// Count-up animation
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.count-up').forEach(counter => {
        const target = parseInt(counter.getAttribute('data-value')) || 0;

        if (target === 0) {
            counter.innerText = '0';
            return;
        }

        let current = 0;
        const steps = 40;
        const increment = Math.max(1, Math.ceil(target / steps));

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.innerText = target;
                clearInterval(timer);
            } else {
                counter.innerText = current;
            }
        }, 30);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
