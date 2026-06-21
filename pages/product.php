<?php
// pages/product.php
require_once __DIR__ . '/../includes/bootstrap.php';

$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    redirect(BASE_URL . 'pages/browse.php');
}

// Fetch Product Details
$viewerId = isLoggedIn() ? (int)currentUserId() : 0;
$viewerIsAdmin = isAdmin() ? 1 : 0;
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.username as seller_name, u.id as seller_id, u.avatar as seller_avatar, u.created_at as seller_since
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id
      AND (
          p.status = 'active'
          OR (p.status <> 'deleted' AND (p.user_id = :viewer_id OR :viewer_is_admin = 1))
      )
");
$stmt->execute([
    ':id' => $productId,
    ':viewer_id' => $viewerId,
    ':viewer_is_admin' => $viewerIsAdmin,
]);
$product = $stmt->fetch();

if (!$product) {
    $pageTitle = __('product.not_found_title');
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container mt-16 mb-20 text-center"><div class="glass-panel p-16" style="border-radius: var(--radius-xl);"><div class="text-6xl mb-4 opacity-50">🔍</div><h2 class="mb-2 font-bold text-main">' . __('product.not_found') . '</h2><p class="text-muted text-lg mb-6">' . __('product.not_found_desc') . '</p><a href="browse.php" class="btn btn-primary hover-scale" style="border-radius: var(--radius-lg);">' . __('product.back_to_browse') . '</a></div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Increment View Count (Unique per student)
$canCountView = false;
$currentUid = isLoggedIn() ? (int)currentUserId() : null;
$hasProductViewsTable = true;

try {
    $pdo->query("SELECT 1 FROM product_views LIMIT 1");
} catch (PDOException $e) {
    $hasProductViewsTable = false;
}

if ($hasProductViewsTable && $currentUid) {
    // Check if this student has already viewed this product
    $viewCheck = $pdo->prepare("SELECT 1 FROM product_views WHERE product_id = ? AND user_id = ?");
    $viewCheck->execute([$productId, $currentUid]);
    if (!$viewCheck->fetch()) {
        // First time viewing! Record it and increment the counter
        $insView = $pdo->prepare("INSERT INTO product_views (product_id, user_id) VALUES (?, ?)");
        $insView->execute([$productId, $currentUid]);
        $canCountView = true;
    }
} elseif ($hasProductViewsTable) {
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

$isOwner = isLoggedIn() && ((int)currentUserId() === (int)$product['seller_id'] || isAdmin());

// Handle Price Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'update_price') {
    verifyCsrfToken();
    $newPrice = (float)($_POST['new_price'] ?? 0);
    if ($newPrice > 0) {
        $stmtUp = $pdo->prepare("UPDATE products SET price = :price, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':price' => $newPrice, ':id' => $productId]);
        setFlash('success', __('product.price_updated'));
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    } else {
        setFlash('error', __('product.price_error'));
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    }
}

// Handle Discount Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'set_discount') {
    verifyCsrfToken();
    $discountPercent = (int)($_POST['discount_percent'] ?? 0);
    if ($discountPercent < 0 || $discountPercent > LISTING_DISCOUNT_MAX_PERCENT) {
        setFlash('error', __('product.discount_range_error', ['max' => LISTING_DISCOUNT_MAX_PERCENT]));
    } else {
        $stmtUp = $pdo->prepare("UPDATE products SET discount_percent = :dp, discount_set_at = NOW() WHERE id = :id");
        $stmtUp->execute([':dp' => $discountPercent, ':id' => $productId]);
        setFlash('success', $discountPercent > 0 ? __('product.discount_applied') : __('product.discount_removed'));
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    }
}

// Handle Description Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'update_description') {
    verifyCsrfToken();
    $newDescription = trim(sanitize($_POST['description'] ?? ''));
    if ($newDescription === '') {
        setFlash('error', __('product.description_required'));
    } else {
        $stmtUp = $pdo->prepare("UPDATE products SET description = :description, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':description' => $newDescription, ':id' => $productId]);
        setFlash('success', __('product.description_updated'));
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    }
}

// Handle Mark as Sold (Now moves to bin too)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'mark_sold') {
    verifyCsrfToken();
    $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$productId])) {
        setFlash('success', __('product.marked_sold_success'));
        redirect(BASE_URL . 'pages/recycle_bin.php');
    }
}

// Handle Delete Listing (Move to Recycle Bin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    verifyCsrfToken();
    $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$productId])) {
        setFlash('success', __('product.deleted_success'));
        redirect(BASE_URL . 'pages/recycle_bin.php');
    }
}

// Handle Add Images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'add_images') {
    verifyCsrfToken();
    
    // Count current images
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $stmtCount->execute([$productId]);
    $currentCount = (int)$stmtCount->fetchColumn();
    
    if ($currentCount >= 5) {
        setFlash('error', __('product.max_images_error'));
        redirect(BASE_URL . 'pages/product.php?id=' . $productId);
    }
    
    if (!empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        $uploaded = 0;
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($currentCount + $uploaded >= 5) break;
            
            $fileData = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i]
            ];
            
            $upload = handleUpload($fileData, 'products/');
            if ($upload['success']) {
                $isPrimary = ($currentCount === 0 && $uploaded === 0);
                $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (:pid, :path, :primary)");
                $stmtImg->bindValue(':pid', $productId, PDO::PARAM_INT);
                $stmtImg->bindValue(':path', $upload['path'], PDO::PARAM_STR);
                $stmtImg->bindValue(':primary', $isPrimary, PDO::PARAM_BOOL);
                $stmtImg->execute();
                $uploaded++;
            } else {
                setFlash('error', __('product.upload_failed', ['error' => $upload['error']]));
                redirect(BASE_URL . 'pages/product.php?id=' . $productId);
            }
        }
        
        if ($uploaded > 0) {
            setFlash('success', __('product.photos_uploaded', ['count' => $uploaded]));
        }
    } else {
        setFlash('error', __('product.no_images_selected'));
    }
    redirect(BASE_URL . 'pages/product.php?id=' . $productId);
}

// Handle Set Primary Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'set_primary') {
    verifyCsrfToken();
    $imageId = (int)($_POST['image_id'] ?? 0);
    
    // Verify image belongs to this product
    $check = $pdo->prepare("SELECT 1 FROM product_images WHERE id = ? AND product_id = ?");
    $check->execute([$imageId, $productId]);
    if ($check->fetch()) {
        $pdo->beginTransaction();
        try {
            // Set all to false
            $stmt1 = $pdo->prepare("UPDATE product_images SET is_primary = FALSE WHERE product_id = ?");
            $stmt1->execute([$productId]);
            // Set target to true
            $stmt2 = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE id = ?");
            $stmt2->execute([$imageId]);
            $pdo->commit();
            setFlash('success', __('product.primary_image_updated'));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', __('product.db_error'));
        }
    } else {
        setFlash('error', __('product.invalid_image_selection'));
    }
    redirect(BASE_URL . 'pages/product.php?id=' . $productId);
}

// Handle Delete Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    verifyCsrfToken();
    $imageId = (int)($_POST['image_id'] ?? 0);
    
    // Get image details
    $stmtGet = $pdo->prepare("SELECT image_path, is_primary FROM product_images WHERE id = ? AND product_id = ?");
    $stmtGet->execute([$imageId, $productId]);
    $img = $stmtGet->fetch();
    
    if ($img) {
        $pdo->beginTransaction();
        try {
            // Delete DB row
            $stmtDel = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $stmtDel->execute([$imageId]);
            
            // Remove file from storage (Supabase or local uploads)
            $path = $img['image_path'];
            deleteStoredImageFile($path);
            
            // If it was primary, assign a new primary
            if ($img['is_primary']) {
                $stmtNext = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? LIMIT 1");
                $stmtNext->execute([$productId]);
                $nextId = $stmtNext->fetchColumn();
                if ($nextId) {
                    $stmtSetNext = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE id = ?");
                    $stmtSetNext->execute([$nextId]);
                }
            }
            
            $pdo->commit();
            setFlash('success', __('product.image_deleted'));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', __('product.delete_image_failed'));
        }
    } else {
        setFlash('error', __('product.image_not_found'));
    }
    redirect(BASE_URL . 'pages/product.php?id=' . $productId);
}

// Fetch Images
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

// Fetch Seller Stats (for SCC)
$rating = getSellerRating($pdo, (int)$product['seller_id']);
$trust  = getSellerTrustScore($pdo, (int)$product['seller_id']);

// Fetch REAL unique view count from product_views table (fallback when table is missing locally)
if ($hasProductViewsTable) {
    $stmtViews = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ?");
    $stmtViews->execute([$productId]);
    $uniqueViewCount = (int)$stmtViews->fetchColumn();
} else {
    $uniqueViewCount = (int)($product['views'] ?? 0);
}

// Fetch Wishlist count
$stmtWish = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE product_id = ?");
$stmtWish->execute([$productId]);
$wishlistCount = (int)$stmtWish->fetchColumn();

// CUMULATIVE view counts for graph — each point is total views up to that day
// Points: [5 days ago, 4 days ago, 3 days ago, 2 days ago, yesterday, today]
$viewCumPoints = [];
$wishCumPoints = [];
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$viewSql = $driver === 'pgsql' 
    ? "SELECT COUNT(*) FROM product_views WHERE product_id = ? AND viewed_at <= NOW() - (CAST(? AS text) || ' days')::interval"
    : "SELECT COUNT(*) FROM product_views WHERE product_id = ? AND viewed_at <= DATE_SUB(NOW(), INTERVAL ? DAY)";

$wishSql = $driver === 'pgsql'
    ? "SELECT COUNT(*) FROM wishlists WHERE product_id = ? AND created_at <= NOW() - (CAST(? AS text) || ' days')::interval"
    : "SELECT COUNT(*) FROM wishlists WHERE product_id = ? AND created_at <= DATE_SUB(NOW(), INTERVAL ? DAY)";

for ($d = 5; $d >= 0; $d--) {
    if ($hasProductViewsTable) {
        $sv = $pdo->prepare($viewSql);
        $sv->execute([$productId, $d]);
        $viewCumPoints[] = (int)$sv->fetchColumn();
    } else {
        $viewCumPoints[] = 0;
    }

    $sw = $pdo->prepare($wishSql);
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
.scc-wrapper {
    width: 100%;
}

.scc-seller-card {
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    border: 1px solid var(--border-light);
    border-left: 4px solid var(--primary);
    border-radius: var(--radius-lg);
    padding: 1.2rem 1.4rem;
    background: var(--bg-card);
    margin-bottom: 1rem;
    transition: var(--transition);
}
@media (max-width: 768px) {
    .scc-seller-card {
        flex-direction: column;
        align-items: stretch;
        gap: 1.2rem;
        padding: 1.2rem 1rem;
    }
}

.scc-seller-link {
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    min-width: 0;
}
@media (max-width: 480px) {
    .scc-seller-link {
        gap: 10px;
    }
}

.scc-avatar {
    width: 74px;
    height: 74px;
    border-radius: var(--radius-md);
    object-fit: cover;
    border: 1px solid var(--border-light);
    flex-shrink: 0;
}
@media (max-width: 480px) {
    .scc-avatar {
        width: 56px;
        height: 56px;
    }
}

.scc-seller-info {
    margin-left: 4px;
    min-width: 0;
    flex-grow: 1;
}

.scc-username {
    font-size: 1.5rem;
    line-height: 1.2;
    letter-spacing: -0.01em;
    font-weight: 700;
    color: var(--text-main);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 480px) {
    .scc-username {
        font-size: 1.2rem;
    }
}

.scc-stats-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
    font-size: 0.88rem;
    font-weight: 600;
    margin-top: 0.25rem;
}
@media (max-width: 480px) {
    .scc-stats-row {
        font-size: 0.8rem;
    }
}

.scc-badge {
    background: var(--primary-light);
    border: 1px solid rgba(26, 127, 100, 0.15);
    color: var(--primary);
    padding: 0.2rem 0.5rem;
    border-radius: var(--radius-sm);
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
body.dark-mode .scc-badge {
    background: rgba(52, 211, 153, 0.15);
    border-color: rgba(52, 211, 153, 0.25);
    color: #34d399;
}

.scc-view-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.65rem 1.25rem;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    font-weight: 700;
    color: var(--text-main);
    background: var(--bg-surface);
    font-size: 0.85rem;
    transition: var(--transition);
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
}
.scc-view-profile-btn:hover {
    background: var(--primary-light);
    color: var(--primary);
    border-color: var(--primary);
}
@media (max-width: 768px) {
    .scc-view-profile-btn {
        width: 100%;
        padding: 0.75rem;
    }
}

.product-desc-card {
    margin-top: 2rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    background: var(--bg-card);
    border: 1px solid var(--border-light);
}
@media (min-width: 768px) {
    .product-desc-card {
        padding: 2.5rem;
    }
}

/* Gallery responsive height overrides */
@media (max-width: 768px) {
    .product-gallery-main {
        height: 300px;
    }
}
@media (max-width: 480px) {
    .product-gallery-main {
        height: 240px;
    }
}

.product-meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
@media (max-width: 480px) {
    .product-meta-row {
        gap: 0.75rem;
    }
}

.scc-main-card {
    border-radius: 20px;
    border: 1px solid var(--border-light);
    background: var(--bg-card);
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
    background: var(--bg-surface);
    pointer-events: none;
}

.scc-metric-blue {
    background: var(--bg-surface);
    border-color: var(--border-light) !important;
}

.scc-metric-violet {
    background: var(--bg-surface);
    border-color: var(--border-light) !important;
}

.text-main {
    color: var(--text-main) !important;
}
.text-muted {
    color: var(--text-muted) !important;
}
.text-light {
    color: var(--text-light) !important;
}
</style>

<div class="container pt-24 mb-20 relative">
    <?php if ($isOwner): ?>
        <div class="seller-management-banner" style="background: var(--primary); color: white; padding: 1.25rem 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(255,255,255,0.1);">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 flex items-center justify-center" style="border-radius: var(--radius-md); background: rgba(255,255,255,0.2);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </div>
                <div>
                    <h4 class="mb-0 font-bold" style="line-height: 1.2; font-size: 1.25rem; color: white;"><?= __('product.mgmt_mode') ?></h4>
                    <p class="mb-0 opacity-90 small" style="color: white; font-weight: 500;"><?= __('product.mgmt_mode_desc') ?></p>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>pages/profile.php" class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);"><?= __('product.go_to_dashboard') ?></a>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-muted small mb-6 font-medium inline-flex px-4 py-2 rounded-xl backdrop-blur-md" style="background: color-mix(in srgb, var(--bg-surface) 70%, transparent); border: 1px solid var(--border-light);">
        <a href="<?php echo BASE_URL; ?>/" class="hover:text-primary transition-colors"><?= __('product.home') ?></a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php" class="hover:text-primary transition-colors"><?= __('product.browse') ?></a>
        <span class="opacity-50">/</span>
        <a href="<?php echo BASE_URL; ?>/pages/browse.php?category=<?php echo $product['category_id']; ?>" class="hover:text-primary transition-colors"><?php echo sanitize(translateCategory($product['category_name'])); ?></a>
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
                        <span class="text-xl font-medium opacity-50"><?= __('product.no_image') ?></span>
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
                <p class="text-primary font-bold tracking-widest uppercase small mb-2" style="font-size: 0.8rem;"><?php echo sanitize(translateCategory($product['category_name'])); ?></p>
                <h1 class="product-title mb-4 text-main font-bold" style="line-height: 1.2; letter-spacing: -0.5px;"><?php echo sanitize($product['title']); ?></h1>
                <div class="product-meta-row">
                    <span class="product-price" style="font-weight: 700; color: var(--text-main); font-family: 'Inter', sans-serif; letter-spacing: -1px;"><?php echo renderProductPrice($product); ?></span>
                    
                    <form action="../actions/toggle_wishlist.php" method="POST" style="display: inline-block;">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                        <button type="submit" class="hover-scale" style="background: <?php echo $isSaved ? '#fff1f2' : 'var(--bg-main)'; ?>; border: 1px solid <?php echo $isSaved ? '#fecdd3' : 'var(--border-light)'; ?>; color: <?php echo $isSaved ? '#e11d48' : 'var(--text-muted)'; ?>; padding: 0.6rem 1rem; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; gap: 0.5rem;">
                            <svg class="w-6 h-6" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24" style="width: 22px; height: 22px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <span style="font-weight: 700; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em;"><?= $isSaved ? __('product.saved') : __('product.save') ?></span>
                        </button>
                    </form>

                    <span class="text-muted small px-3 py-1 rounded-lg font-medium" style="background: var(--bg-main); border: 1px solid var(--border-light);"><?= __('product.listed_time', ['time' => timeAgo($product['created_at'])]) ?></span>
                </div>
            </div>

            <!-- SELLER PROFILE CARD (Visible to Everyone) -->
            <div class="scc-wrapper mt-8">
                <div class="scc-seller-card">
                    <a href="<?php echo BASE_URL; ?>pages/profile.php?id=<?php echo $product['seller_id']; ?>" class="scc-seller-link">
                        <img src="<?php echo avatarUrl($product['seller_avatar']); ?>" 
                             alt="<?php echo sanitize($product['seller_name']); ?>"
                             class="scc-avatar">
                        <div class="scc-seller-info">
                            <h4 class="scc-username">@<?php echo sanitize($product['seller_name']); ?></h4>
                            <div class="scc-stats-row">
                                <div class="flex items-center gap-1" style="color: var(--text-main);">
                                    <span style="color: #f59e0b;">&#9733;</span>
                                    <span style="font-weight: 700; color: var(--text-main);"><?php echo number_format($rating['avg'], 1); ?></span>
                                    <span style="color: var(--text-light); font-weight: 500;">(<?php echo $rating['count']; ?> <?= $rating['count'] === 1 ? __('product.review') : __('product.reviews') ?>)</span>
                                </div>
                                <span class="scc-badge">
                                    <?= $rating['count'] > 5 ? __('product.trusted_seller') : __('product.new_seller') ?>
                                </span>
                                <div style="color: var(--text-muted);"><?= __('product.trust_score') ?> <span style="font-weight: 700; color: var(--text-main);"><?php echo (int)$trust['score']; ?>/100</span> <span style="opacity: 0.4; cursor: help;" title="<?php echo sanitize($trust['tier']); ?>">&#9432;</span></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/profile.php?id=<?php echo $product['seller_id']; ?>" class="scc-view-profile-btn">
                        <?= __('product.view_profile') ?> <span style="opacity: 0.45; font-size: 0.8rem; margin-left: 4px;">&#10095;</span>
                    </a>
                </div>
            </div>

            <!-- LISTING INSIGHTS CENTER -->
            <?php if ($isOwner): ?>
            <div class="scc-wrapper">
                <!-- MAIN INSIGHTS BOX -->
                <div class="scc-main-card" style="padding: 2rem; margin-bottom: 2rem; margin-left: auto; border-radius: var(--radius-lg); background: var(--bg-card); border: 1px solid var(--border-light);">
                    <!-- Insights Header -->
                    <div class="flex items-start justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 flex items-center justify-center text-indigo-500" style="border-radius: var(--radius-xl); background: var(--bg-main);">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <div>
                                <h3 class="m-0 font-black text-main" style="font-size: 1.4rem; color: var(--text-main);"><?= __('product.listing_insights') ?></h3>
                                <p class="m-0 text-muted font-bold" style="font-size: 0.9rem;"><?= __('product.live_performance') ?></p>
                            </div>
                        </div>
                        <span style="font-size: 0.65rem; font-weight: 900; color: #94a3b8; background: #f8fafc; padding: 0.25rem 0.6rem; border-radius: 6px; letter-spacing: 0.05em; border: 1px solid #f1f5f9;"><?php echo $isOwner ? __('product.seller_badge') : __('product.livestats_badge'); ?></span>
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
                                    <span class="text-[0.95rem] font-bold text-muted"><?= __('product.total_reach') ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-4xl font-black text-main m-0 count-up" data-value="<?php echo $uniqueViewCount; ?>">0</h2>
                                </div>
                                <p class="text-[0.75rem] font-bold text-light m-0 mt-1"><?= __('product.unique_views') ?></p>
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
                                    <span class="text-[0.95rem] font-bold text-muted"><?= __('product.student_interest') ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-4xl font-black text-main m-0 count-up" data-value="<?php echo $wishlistCount; ?>">0</h2>
                                    <span class="text-emerald-500 font-black text-[0.8rem] flex items-center gap-1">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                        <?= __('product.live_label') ?>
                                    </span>
                                </div>
                                <p class="text-[0.75rem] font-bold text-light m-0 mt-1"><?= __('product.saved_by') ?></p>
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
                        <h4 class="font-bold text-main mb-4" style="font-size: 1.15rem; color: var(--text-main);"><?= __('product.pricing_strategy') ?></h4>
                        <form method="post" class="flex flex-wrap items-center gap-4">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="action" value="update_price">
                            <div class="flex items-center bg-white border border-slate-200 px-4" style="border-radius: 10px; height: 38px; min-width: 120px;">
                                <span class="text-slate-400 font-bold mr-1" style="font-size: 0.8rem;">&#8377;</span>
                                <input type="number" name="new_price" step="0.01" value="<?php echo (float)$product['price']; ?>" 
                                       style="width: 100%; background: transparent; border: none; font-size: 0.95rem; font-weight: 800; color: #1e293b; outline: none;" required>
                            </div>
                            <button type="submit" class="flex items-center gap-2 font-black text-[0.72rem] uppercase tracking-[0.14em] transition-all hover:brightness-95 shadow-sm" style="height: 38px; color: var(--primary); background: var(--bg-surface); border: 1px solid var(--border-light); padding: 0 1rem; border-radius: 10px; cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= __('product.update_price') ?>
                            </button>
                        </form>
                        
                        <!-- Set Discount Form -->
                        <form method="post" class="flex flex-wrap items-center gap-4 mt-4">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="action" value="set_discount">
                            <div class="flex items-center bg-white border border-slate-200 px-4" style="border-radius: 10px; height: 38px; min-width: 120px;">
                                <select name="discount_percent" style="width: 100%; background: transparent; border: none; font-size: 0.95rem; font-weight: 800; color: #1e293b; outline: none; cursor: pointer;">
                                    <?php foreach ([0, 5, 10, 15, 20, 25, 30, 40, 50] as $d): ?>
                                        <option value="<?php echo $d; ?>" <?php echo ((int)($product['discount_percent'] ?? 0) === $d) ? 'selected' : ''; ?>>
                                            <?php echo $d === 0 ? __('product.no_discount') : ('-' . $d . '%'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="flex items-center gap-2 font-black text-[0.72rem] uppercase tracking-[0.14em] transition-all hover:brightness-95 shadow-sm" style="height: 38px; color: white; background: #ef4444; border: 1px solid #dc2626; padding: 0 1rem; border-radius: 10px; cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                <?= __('product.apply_discount') ?>
                            </button>
                        </form>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <form method="post" onsubmit="return confirm('<?= addslashes(__('product.confirm_mark_sold')) ?>')">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="action" value="mark_sold">
                            <button type="submit" class="flex items-center gap-2 font-bold text-[0.75rem] uppercase tracking-wider transition-all hover-scale" style="height: 38px; color: var(--primary); background: var(--bg-surface); border: 1px solid var(--border-light); padding: 0 1rem; border-radius: var(--radius-sm); cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <?= __('product.mark_sold_btn') ?>
                            </button>
                        </form>
                        
                        <form method="post" onsubmit="return confirm('<?= addslashes(__('product.confirm_delete')) ?>')">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="action" value="delete_listing">
                            <button type="submit" class="flex items-center gap-2 font-bold text-[0.75rem] uppercase tracking-wider transition-all hover-scale" style="height: 38px; color: #ef4444; background: var(--bg-surface); border: 1px solid var(--border-light); padding: 0 0.75rem; border-radius: var(--radius-sm); cursor: pointer;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                <?= __('product.delete_listing_btn') ?>
                            </button>
                        </form>
                    </div>

                    <!-- GALLERY MANAGEMENT -->
                    <div class="mb-8 border-t border-slate-100 pt-6 mt-6">
                        <h4 class="font-bold text-main mb-4" style="font-size: 1.15rem; color: var(--text-main);"><?= __('product.manage_gallery') ?></h4>
                        
                        <!-- Thumbnail Grid -->
                        <div class="grid grid-cols-5 gap-3 mb-6">
                            <?php foreach ($images as $img): ?>
                                <div class="relative group rounded-lg overflow-hidden border border-slate-200 aspect-square bg-slate-50" style="width: 100%;">
                                    <img src="<?php echo getProductImage($img['image_path']); ?>" alt="Gallery Image" class="w-full h-full object-contain" style="object-fit: contain; background: #f8fafc;">
                                    
                                    <!-- Badges & Controls Overlay -->
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-1.5">
                                        <div class="flex justify-between items-start">
                                            <?php if ($img['is_primary']): ?>
                                                <span class="bg-indigo-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow"><?= __('product.primary') ?></span>
                                            <?php else: ?>
                                                <form method="post" style="display:inline;">
                                                    <?php echo csrfTokenField(); ?>
                                                    <input type="hidden" name="action" value="set_primary">
                                                    <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                    <button type="submit" class="bg-white/90 hover:bg-white text-indigo-600 p-1 rounded shadow transition-colors" title="<?= addslashes(__('product.set_primary')) ?>" style="border:none; cursor:pointer;">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button -->
                                            <?php if (count($images) > 1): ?>
                                                <form method="post" onsubmit="return confirm('<?= addslashes(__('product.confirm_delete_image')) ?>')" style="display:inline;">
                                                    <?php echo csrfTokenField(); ?>
                                                    <input type="hidden" name="action" value="delete_image">
                                                    <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                                    <button type="submit" class="bg-red-600/90 hover:bg-red-600 text-white p-1 rounded shadow transition-colors ml-auto" title="<?= addslashes(__('product.delete_image_btn')) ?>" style="border:none; cursor:pointer;">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Fallback Indicator for Primary on non-hover -->
                                    <?php if ($img['is_primary']): ?>
                                        <div class="absolute bottom-1 right-1 bg-indigo-600 text-white p-0.5 rounded-full shadow" style="pointer-events: none;">
                                            <svg class="w-2.5 h-2.5" style="width: 10px; height: 10px; display: block;" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Empty Slots if < 5 -->
                            <?php for ($i = count($images); $i < 5; $i++): ?>
                                <div class="border border-dashed border-slate-200 rounded-lg flex items-center justify-center aspect-square text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Add Images Form -->
                        <?php if (count($images) < 5): ?>
                            <form method="post" enctype="multipart/form-data" class="mt-4">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="action" value="add_images">
                                
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-2 px-4 py-2 border border-slate-200 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer" style="margin-bottom:0;">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?= __('product.select_files') ?>
                                        <input type="file" id="mgmtImgInput" name="images[]" multiple accept="image/*" class="hidden">
                                    </label>
                                    <span id="mgmtUploadHelp" class="text-xs text-slate-400"><?= __('product.more_photos_help', ['count' => (5 - count($images))]) ?></span>
                                    
                                    <button type="submit" id="mgmtSubmitBtn" class="btn btn-primary btn-sm px-4 py-2 ml-auto" style="height:38px; display:none; font-weight:bold;">
                                        <?= __('product.upload_btn') ?>
                                    </button>
                                </div>
                                <div id="mgmtPreview" class="flex flex-wrap gap-2 mt-3"></div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- FOOTER NAVIGATION -->
                    <a href="<?php echo BASE_URL; ?>pages/profile.php" class="inline-flex items-center gap-2 font-bold text-[1rem] mt-12 hover-scale" style="color: var(--primary);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke-width: 2;"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <?= __('product.return_dashboard') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

                <!-- DESCRIPTION CARD (BOTTOM) -->
                <div class="product-desc-card">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-10 h-10 flex items-center justify-center" style="border-radius: var(--radius-md); background: var(--bg-main); color: var(--primary);">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <h3 class="m-0 font-bold text-main" style="font-size: 1.4rem; color: var(--text-main);"><?= __('product.description_title') ?></h3>
                    </div>
                    <div style="line-height: 2; color: var(--text-muted); font-size: 1.15rem;">
                        <?php if ($isOwner): ?>
                            <form method="post">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="action" value="update_description">
                                <textarea name="description" rows="6" class="w-full premium-input" style="padding: 1rem; border-radius: var(--radius-lg); line-height: 1.6; font-size: 1rem; margin-bottom: 0.75rem;" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                <button type="submit" class="btn btn-primary btn-sm"><?= __('product.update_description') ?></button>
                            </form>
                        <?php else: ?>
                            <?php echo nl2br(sanitize($product['description'])); ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php if (!$isOwner && isLoggedIn()): ?>
            <!-- Action Buttons for Buyer -->
            <div class="flex flex-col gap-4 sticky bottom-4 z-10 p-4 mt-8" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light); background: color-mix(in srgb, var(--bg-card) 95%, transparent); backdrop-filter: blur(10px);">
                <a href="messages.php?other_user_id=<?php echo $product['seller_id']; ?>&product_id=<?php echo $product['id']; ?>" class="btn btn-primary flex-grow justify-center py-4 text-lg hover-scale">
                    <?= __('product.message_seller') ?>
                </a>
                <a href="<?php echo BASE_URL; ?>pages/report.php?product_id=<?php echo (int)$product['id']; ?>" class="btn btn-secondary flex-grow justify-center py-3 text-sm hover-scale" style="border-radius: var(--radius-lg);">
                    <?= __('report.report_listing') ?>
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

// Gallery Management client-side logic
const mgmtImgInput = document.getElementById('mgmtImgInput');
if (mgmtImgInput) {
    let mgmtUploadedFiles = [];
    const mgmtMaxFiles = <?php echo isset($images) ? (5 - count($images)) : 5; ?>;

    function compressImageAsync(file, maxWidth = 1200, maxHeight = 1200, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            reject(new Error('Canvas to Blob failed'));
                            return;
                        }
                        const compressedFile = new File([blob], file.name.substring(0, file.name.lastIndexOf('.')) + '.jpg', {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    function updateMgmtFileInput() {
        const dt = new DataTransfer();
        mgmtUploadedFiles.forEach(file => dt.items.add(file));
        mgmtImgInput.files = dt.files;
    }

    function renderMgmtPreviews() {
        const preview = document.getElementById('mgmtPreview');
        const submitBtn = document.getElementById('mgmtSubmitBtn');
        preview.innerHTML = '';
        
        if (mgmtUploadedFiles.length > 0) {
            submitBtn.style.display = 'inline-block';
        } else {
            submitBtn.style.display = 'none';
        }
        
        mgmtUploadedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (re) => {
                const div = document.createElement('div');
                div.style = "position: relative; width:70px; height:70px; border-radius: var(--radius-md); overflow:hidden; border: 1px solid var(--border-light); flex-shrink: 0;";
                
                const img = document.createElement('img');
                img.src = re.target.result;
                img.style = "width:100%; height:100%; object-fit:cover;";
                
                const removeBtn = document.createElement('button');
                removeBtn.type = "button";
                removeBtn.innerHTML = "&times;";
                removeBtn.style = "position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;";
                removeBtn.onclick = function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    mgmtUploadedFiles.splice(index, 1);
                    updateMgmtFileInput();
                    renderMgmtPreviews();
                };
                
                div.appendChild(img);
                div.appendChild(removeBtn);
                preview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }

    mgmtImgInput.addEventListener('change', async function(e) {
        const newFiles = [...e.target.files];
        const submitBtn = document.getElementById('mgmtSubmitBtn');
        const uploadHelp = document.getElementById('mgmtUploadHelp');
        if (newFiles.length === 0) {
            updateMgmtFileInput();
            return;
        }
        // Check if new selection was just the internal update
        if (newFiles.length === mgmtUploadedFiles.length) {
            let same = true;
            for (let i = 0; i < newFiles.length; i++) {
                if (newFiles[i] !== mgmtUploadedFiles[i]) {
                    same = false; break;
                }
            }
            if (same) return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerText = "Processing...";
        uploadHelp.innerText = "Compressing images...";
        uploadHelp.style.color = "var(--primary)";
        
        for (let i = 0; i < newFiles.length; i++) {
            if (mgmtUploadedFiles.some(f => f.name === newFiles[i].name && f.size === newFiles[i].size)) continue;
            
            if (mgmtUploadedFiles.length < mgmtMaxFiles) {
                try {
                    const compressed = await compressImageAsync(newFiles[i]);
                    mgmtUploadedFiles.push(compressed);
                } catch (err) {
                    console.error('Compression failed', err);
                    mgmtUploadedFiles.push(newFiles[i]);
                }
            } else {
                alert('You can only upload up to ' + mgmtMaxFiles + ' additional images.');
                break;
            }
        }
        
        updateMgmtFileInput();
        renderMgmtPreviews();
        
        submitBtn.disabled = false;
        submitBtn.innerText = "Upload";
        uploadHelp.innerText = "Ready to upload";
        uploadHelp.style.color = "";
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
