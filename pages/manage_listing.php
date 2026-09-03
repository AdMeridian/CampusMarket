<?php
// pages/manage_listing.php — Dedicated Seller Management Hub
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/listing_moderation.php';

requireLogin();

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    redirect(BASE_URL . 'pages/profile.php');
}

$viewerId = (int)currentUserId();
$viewerIsAdmin = isAdmin();

// Fetch product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.username as seller_name, u.id as seller_id, u.avatar as seller_avatar
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id AND (p.user_id = :uid OR :is_admin = 1)
");
$stmt->execute([
    ':id' => $productId,
    ':uid' => $viewerId,
    ':is_admin' => $viewerIsAdmin ? 1 : 0
]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', __('product.not_found'));
    redirect(BASE_URL . 'pages/profile.php');
}

$isOwner = true;
$isService = (($product['listing_type'] ?? 'product') === 'service');
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$productCategories = getProductCategoryRows($pdo, $productId);

// ==========================================
// POST ACTION HANDLERS
// ==========================================

// 1. Update Title & Description & Location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_basic_details') {
    verifyCsrfToken();
    $newTitle = trim(sanitize($_POST['title'] ?? ''));
    $newDescription = trim(sanitize($_POST['description'] ?? ''));
    $newTown = strtolower(trim((string)($_POST['location_town'] ?? '')));
    $newCustomLoc = trim(sanitize($_POST['custom_location'] ?? ''));

    if ($newTitle === '' || mb_strlen($newTitle) < 3) {
        setFlash('error', __('create_listing.title_required'));
    } elseif (mb_strlen($newTitle) > 100) {
        setFlash('error', __('create_listing.title_too_long'));
    } elseif ($newDescription === '') {
        setFlash('error', __('product.description_required'));
    } elseif (!isValidLocationTown($newTown)) {
        setFlash('error', __('create_listing.town_required'));
    } elseif ($newTown === 'other' && empty($newCustomLoc)) {
        setFlash('error', __('create_listing.custom_location_required'));
    } else {
        $stmtUp = $pdo->prepare("
            UPDATE products 
            SET title = :title, 
                description = :description, 
                location_town = :town, 
                custom_location = :custom_loc, 
                updated_at = NOW() 
            WHERE id = :id
        ");
        $stmtUp->execute([
            ':title' => $newTitle,
            ':description' => $newDescription,
            ':town' => $newTown,
            ':custom_loc' => ($newTown === 'other' ? $newCustomLoc : null),
            ':id' => $productId
        ]);
        setFlash('success', 'Listing details updated successfully.');
    }
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 2. Update Pricing & Discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
    verifyCsrfToken();
    $newPrice = (float)($_POST['new_price'] ?? 0);
    $newCurrency = strtoupper(trim((string)($_POST['price_currency'] ?? DEFAULT_PRODUCT_CURRENCY)));
    $discountPercent = (int)($_POST['discount_percent'] ?? 0);

    if (!array_key_exists($newCurrency, PRODUCT_CURRENCIES)) {
        $newCurrency = DEFAULT_PRODUCT_CURRENCY;
    }

    if ($newPrice <= 0) {
        setFlash('error', __('product.price_error'));
    } elseif ($discountPercent < 0 || $discountPercent > LISTING_DISCOUNT_MAX_PERCENT) {
        setFlash('error', __('product.discount_range_error', ['max' => LISTING_DISCOUNT_MAX_PERCENT]));
    } else {
        $stmtUp = $pdo->prepare("
            UPDATE products 
            SET price = :price, 
                price_currency = :currency, 
                discount_percent = :dp, 
                discount_set_at = NOW(), 
                updated_at = NOW() 
            WHERE id = :id
        ");
        $stmtUp->execute([
            ':price' => $newPrice,
            ':currency' => $newCurrency,
            ':dp' => $discountPercent,
            ':id' => $productId
        ]);
        setFlash('success', 'Pricing updated successfully.');
    }
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 3. Update Service Details (Turnaround, Revisions, Availability, Portfolio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_service_details') {
    verifyCsrfToken();
    $rawDelivery = (int)($_POST['delivery_days'] ?? 0);
    $newDeliveryDays = ($rawDelivery > 0 && $rawDelivery <= 365) ? $rawDelivery : null;
    
    $rawRevisions = $_POST['revision_count'] ?? '';
    if ($rawRevisions === 'unlimited') {
        $newRevisionCount = 99;
    } elseif (is_numeric($rawRevisions) && (int)$rawRevisions >= 0) {
        $newRevisionCount = (int)$rawRevisions;
    } else {
        $newRevisionCount = null;
    }

    $newAvailStatus = in_array($_POST['availability_status'] ?? '', ['available','busy','unavailable'])
        ? $_POST['availability_status']
        : 'available';

    $rawResetDays = (int)($_POST['availability_reset_days'] ?? 0);
    $newResetAt = null;
    if ($rawResetDays > 0) {
        $newResetAt = date('Y-m-d H:i:s', strtotime("+{$rawResetDays} days"));
    }

    $rawPortfolio = trim($_POST['portfolio_link'] ?? '');
    $newPortfolioLink = null;
    if ($rawPortfolio !== '' && filter_var($rawPortfolio, FILTER_VALIDATE_URL)) {
        $newPortfolioLink = mb_substr($rawPortfolio, 0, 500);
    }

    $stmtUp = $pdo->prepare("
        UPDATE products 
        SET delivery_days = :del_days,
            revision_count = :rev_count,
            availability_status = :avail_status,
            availability_reset_at = :avail_reset,
            portfolio_link = :port_link,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':del_days' => $newDeliveryDays,
        ':rev_count' => $newRevisionCount,
        ':avail_status' => $newAvailStatus,
        ':avail_reset' => $newResetAt,
        ':port_link' => $newPortfolioLink,
        ':id' => $productId,
    ]);

    setFlash('success', 'Service settings updated successfully.');
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 4. Update Categories (Product only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_categories') {
    verifyCsrfToken();
    $selectedCategoryIds = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids'] ?? []))));
    
    if (empty($selectedCategoryIds)) {
        setFlash('error', __('create_listing.select_category'));
        redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
    }
    
    $primaryCategoryId = $selectedCategoryIds[0];
    try {
        ensureProductCategoriesTable($pdo);
    } catch (Exception $e) {}

    try {
        $pdo->beginTransaction();
        $stmtUp = $pdo->prepare("UPDATE products SET category_id = :cid, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':cid' => $primaryCategoryId, ':id' => $productId]);

        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertSql = ($driverName === 'pgsql')
            ? 'INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, ?) ON CONFLICT (product_id, category_id) DO UPDATE SET is_primary = EXCLUDED.is_primary'
            : 'INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)';
        $ins = $pdo->prepare($insertSql);

        foreach ($selectedCategoryIds as $cid) {
            $isPrimary = ((int)$cid === (int)$primaryCategoryId) ? 1 : 0;
            $ins->execute([$productId, (int)$cid, $isPrimary]);
        }

        $placeholders = implode(',', array_fill(0, count($selectedCategoryIds), '?'));
        $delSql = "DELETE FROM product_categories WHERE product_id = ? AND category_id NOT IN ($placeholders)";
        $delStmt = $pdo->prepare($delSql);
        $delParams = array_merge([$productId], array_map('intval', $selectedCategoryIds));
        $delStmt->execute($delParams);

        $pdo->commit();
        setFlash('success', __('product.categories_updated'));
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', __('product.update_categories_failed'));
    }

    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 5. Add Images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_images') {
    verifyCsrfToken();
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $stmtCount->execute([$productId]);
    $currentCount = (int)$stmtCount->fetchColumn();
    
    if ($currentCount >= 5) {
        setFlash('error', __('product.max_images_error'));
        redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
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
            }
        }
        if ($uploaded > 0) {
            setFlash('success', __('product.photos_uploaded', ['count' => $uploaded]));
        }
    } else {
        setFlash('error', __('product.no_images_selected'));
    }
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 6. Set Primary Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_primary') {
    verifyCsrfToken();
    $imageId = (int)($_POST['image_id'] ?? 0);
    $check = $pdo->prepare("SELECT 1 FROM product_images WHERE id = ? AND product_id = ?");
    $check->execute([$imageId, $productId]);
    if ($check->fetch()) {
        $pdo->beginTransaction();
        try {
            $stmt1 = $pdo->prepare("UPDATE product_images SET is_primary = FALSE WHERE product_id = ?");
            $stmt1->execute([$productId]);
            $stmt2 = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE id = ?");
            $stmt2->execute([$imageId]);
            $pdo->commit();
            setFlash('success', __('product.primary_image_updated'));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', __('product.db_error'));
        }
    }
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 7. Delete Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    verifyCsrfToken();
    $imageId = (int)($_POST['image_id'] ?? 0);
    $stmtGet = $pdo->prepare("SELECT image_path, is_primary FROM product_images WHERE id = ? AND product_id = ?");
    $stmtGet->execute([$imageId, $productId]);
    $img = $stmtGet->fetch();
    
    if ($img) {
        $pdo->beginTransaction();
        try {
            $stmtDel = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $stmtDel->execute([$imageId]);
            deleteStoredImageFile($img['image_path']);
            
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
    }
    redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
}

// 8. Mark Sold / Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_sold') {
    verifyCsrfToken();
    $result = completeProductSale($pdo, $productId, (int)$product['user_id'], null, 'manual');
    if ($result['success']) {
        setFlash('success', $isService ? 'Service marked as completed.' : __('product.marked_sold_success'));
        redirect(BASE_URL . 'pages/profile.php');
    } else {
        setFlash('error', __('product.mark_sold_failed'));
        redirect(BASE_URL . 'pages/manage_listing.php?id=' . $productId);
    }
}

// 9. Delete Listing (Recycle bin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    verifyCsrfToken();
    $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$productId])) {
        setFlash('success', __('product.deleted_success'));
        redirect(BASE_URL . 'pages/recycle_bin.php');
    }
}

// ==========================================
// DATA COMPUTATION FOR ANALYTICS GRAPHS
// ==========================================
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY is_primary DESC");
$stmt->execute([':id' => $productId]);
$images = $stmt->fetchAll();

$hasProductViewsTable = true;
try {
    $pdo->query("SELECT 1 FROM product_views LIMIT 1");
} catch (PDOException $e) {
    $hasProductViewsTable = false;
}

if ($hasProductViewsTable) {
    $stmtViews = $pdo->prepare("SELECT COUNT(*) FROM product_views WHERE product_id = ?");
    $stmtViews->execute([$productId]);
    $uniqueViewCount = (int)$stmtViews->fetchColumn();
} else {
    $uniqueViewCount = (int)($product['views'] ?? 0);
}

$stmtWish = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE product_id = ?");
$stmtWish->execute([$productId]);
$wishlistCount = (int)$stmtWish->fetchColumn();

$hasProductSharesTable = true;
try {
    $pdo->query('SELECT 1 FROM product_shares LIMIT 1');
    $shareStmt = $pdo->prepare('SELECT COUNT(*) FROM product_shares WHERE product_id = ?');
    $shareStmt->execute([$productId]);
    $shareCount = (int)$shareStmt->fetchColumn();
} catch (PDOException $e) {
    $hasProductSharesTable = false;
    $shareCount = 0;
}

$viewCumPoints = [];
$wishCumPoints = [];
$shareCumPoints = [];
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$viewSql = $driver === 'pgsql' 
    ? "SELECT COUNT(*) FROM product_views WHERE product_id = ? AND viewed_at <= NOW() - (CAST(? AS text) || ' days')::interval"
    : "SELECT COUNT(*) FROM product_views WHERE product_id = ? AND viewed_at <= DATE_SUB(NOW(), INTERVAL ? DAY)";

$wishSql = $driver === 'pgsql'
    ? "SELECT COUNT(*) FROM wishlists WHERE product_id = ? AND created_at <= NOW() - (CAST(? AS text) || ' days')::interval"
    : "SELECT COUNT(*) FROM wishlists WHERE product_id = ? AND created_at <= DATE_SUB(NOW(), INTERVAL ? DAY)";

$shareSql = $driver === 'pgsql'
    ? "SELECT COUNT(*) FROM product_shares WHERE product_id = ? AND shared_at <= NOW() - (CAST(? AS text) || ' days')::interval"
    : "SELECT COUNT(*) FROM product_shares WHERE product_id = ? AND shared_at <= DATE_SUB(NOW(), INTERVAL ? DAY)";

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

    if ($hasProductSharesTable) {
        $ss = $pdo->prepare($shareSql);
        $ss->execute([$productId, $d]);
        $shareCumPoints[] = (int)$ss->fetchColumn();
    } else {
        $shareCumPoints[] = 0;
    }
}

function cumulToSvgY(array $points, float $bottom = 39.0, float $maxRise = 28.0): array {
    $max = max($points) ?: 1;
    $result = [];
    foreach ($points as $v) {
        $result[] = round($bottom - ($v / $max) * $maxRise, 2);
    }
    return $result;
}

$viewY = cumulToSvgY($viewCumPoints);
$wishY = cumulToSvgY($wishCumPoints);
$shareY = cumulToSvgY($shareCumPoints);
$xPos = [0, 20, 40, 60, 80, 100];

function buildSvgPath(array $xPos, array $yPos): string {
    $d = "M {$xPos[0]},{$yPos[0]}";
    for ($i = 1; $i < count($xPos); $i++) {
        $d .= " L {$xPos[$i]},{$yPos[$i]}";
    }
    return $d;
}

$vPath = buildSvgPath($xPos, $viewY);
$wPath = buildSvgPath($xPos, $wishY);
$sPath = buildSvgPath($xPos, $shareY);
$vFill = $vPath . " L 100,39 L 0,39 Z";
$wFill = $wPath . " L 100,39 L 0,39 Z";
$sFill = $sPath . " L 100,39 L 0,39 Z";

$pageTitle = 'Manage: ' . sanitize($product['title']);

$svcPricing = getServicePricingSettings($pdo);
$serviceIsExpired = false;
$serviceDaysRemaining = null;
if ($isService && !empty($product['service_expires_at'])) {
    $expiresTimestamp = strtotime($product['service_expires_at']);
    $serviceIsExpired = ($expiresTimestamp <= time());
    $serviceDaysRemaining = max(0, (int)ceil(($expiresTimestamp - time()) / 86400));
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.mgmt-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 992px) {
    .mgmt-grid {
        grid-template-columns: 2fr 1fr;
    }
}
.mgmt-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-xl);
    padding: 1.75rem;
    box-shadow: var(--shadow-sm);
}
.mgmt-header-badge {
    padding: 0.35rem 0.85rem;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.graph-line {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: drawLine 1.4s ease-out forwards;
}
@keyframes drawLine {
    from { stroke-dashoffset: 200; }
    to   { stroke-dashoffset: 0; }
}
</style>

<div class="container pt-24 mb-20">
    <!-- Top Action Bar -->
    <div class="glass-panel p-4 mb-6 flex flex-wrap items-center justify-between gap-4" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>pages/profile.php" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-md);">
                ← Back to Dashboard
            </a>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">
                Editing: <span style="color: var(--primary);"><?= sanitize($product['title']) ?></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($isService && $product['status'] === 'pending_payment'): ?>
                <span class="mgmt-header-badge" style="background: #fef3c7; color: #92400e;">
                    Pending Payment
                </span>
            <?php elseif ($isService && $serviceIsExpired): ?>
                <span class="mgmt-header-badge" style="background: #fee2e2; color: #991b1b;">
                    Expired
                </span>
            <?php else: ?>
                <span class="mgmt-header-badge" style="background: <?= ($product['status'] === 'active') ? '#dcfce7; color: #166534;' : '#eff6ff; color: #1e40af;' ?>">
                    <?= ucfirst(str_replace('_', ' ', $product['status'])) ?>
                </span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>pages/product.php?id=<?= $productId ?>" class="btn btn-primary btn-sm flex items-center gap-2" style="border-radius: var(--radius-md); font-weight: 700;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview as Buyer
            </a>
        </div>
    </div>

    <?php if ($isService && ($product['status'] === 'pending_payment' || $serviceIsExpired || ($serviceDaysRemaining !== null && $serviceDaysRemaining <= 5))): ?>
    <!-- Service Status & Renewal Banner -->
    <div class="glass-panel p-6 mb-8" style="border-radius: var(--radius-xl); border: 2px solid <?= ($product['status'] === 'pending_payment' || $serviceIsExpired) ? 'var(--primary)' : '#f59e0b' ?>; background: <?= ($product['status'] === 'pending_payment' || $serviceIsExpired) ? 'rgba(var(--primary-rgb), 0.04)' : 'rgba(245, 158, 11, 0.05)' ?>;">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div style="flex: 1 1 300px;">
                <div class="flex items-center gap-2 mb-1">
                    <span style="font-size: 1.3rem;">
                        <?= ($product['status'] === 'pending_payment') ? '💳' : ($serviceIsExpired ? '⌛' : '⚡') ?>
                    </span>
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0; color: var(--text-main);">
                        <?php if ($product['status'] === 'pending_payment'): ?>
                            Activate Your Service Listing
                        <?php elseif ($serviceIsExpired): ?>
                            Service Listing Expired
                        <?php else: ?>
                            Expiring Soon (<?= $serviceDaysRemaining ?> days left)
                        <?php endif; ?>
                    </h3>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.9rem; line-height: 1.5;">
                    <?php if ($product['status'] === 'pending_payment'): ?>
                        This service listing is currently private. Activate for ₺<?= number_format($svcPricing['listing_fee'], 0) ?> to display it to campus buyers for <?= (int)$svcPricing['listing_days'] ?> days.
                    <?php elseif ($serviceIsExpired): ?>
                        This listing expired on <?= date('M j, Y', strtotime($product['service_expires_at'])) ?> and is hidden from search. Renew for <?= (int)$svcPricing['listing_days'] ?> days for ₺<?= number_format($svcPricing['listing_fee'], 0) ?>.
                    <?php else: ?>
                        Your service will expire on <?= date('M j, Y', strtotime($product['service_expires_at'])) ?>. You can renew early to keep bookings uninterrupted.
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <form method="POST" action="<?= BASE_URL ?>pages/create_stripe_session.php" class="mb-0">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="payment_type" value="service_listing">
                    <input type="hidden" name="tier" value="standard">
                    <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.6rem 1.1rem; font-weight: 700;">
                        <?= $serviceIsExpired ? 'Renew ' . (int)$svcPricing['listing_days'] . ' Days (₺' . number_format($svcPricing['listing_fee'], 0) . ')' : 'Pay Standard (₺' . number_format($svcPricing['listing_fee'], 0) . ')' ?>
                    </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>pages/create_stripe_session.php" class="mb-0">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="payment_type" value="service_listing">
                    <input type="hidden" name="tier" value="boosted">
                    <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.6rem 1.1rem; font-weight: 700;">
                        ⚡ <?= $serviceIsExpired ? 'Renew + Boost (₺' . number_format($svcPricing['total_boosted_fee'], 0) . ')' : 'Activate + Boost (₺' . number_format($svcPricing['total_boosted_fee'], 0) . ')' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Insights Metric Bar -->
    <div class="grid grid-cols-1 md-grid-cols-3 gap-4 mb-8">
        <div class="mgmt-card p-4 relative overflow-hidden" style="border-left: 4px solid var(--primary);">
            <div class="flex justify-between items-center mb-2">
                <span class="text-muted font-bold text-sm">TOTAL VIEWS</span>
                <span class="font-black text-2xl text-main"><?= $uniqueViewCount ?></span>
            </div>
            <svg viewBox="0 0 100 40" class="w-full h-10" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="vFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="var(--primary)" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <path d="<?= $vFill ?>" fill="url(#vFill)"/>
                <path d="<?= $vPath ?>" class="graph-line" stroke="var(--primary)" stroke-width="1.5" fill="none"/>
            </svg>
        </div>

        <div class="mgmt-card p-4 relative overflow-hidden" style="border-left: 4px solid #ec4899;">
            <div class="flex justify-between items-center mb-2">
                <span class="text-muted font-bold text-sm">WISHLIST SAVES</span>
                <span class="font-black text-2xl text-main"><?= $wishlistCount ?></span>
            </div>
            <svg viewBox="0 0 100 40" class="w-full h-10" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="wFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#ec4899" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#ec4899" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <path d="<?= $wFill ?>" fill="url(#wFill)"/>
                <path d="<?= $wPath ?>" class="graph-line" stroke="#ec4899" stroke-width="1.5" fill="none"/>
            </svg>
        </div>

        <div class="mgmt-card p-4 relative overflow-hidden" style="border-left: 4px solid #10b981;">
            <div class="flex justify-between items-center mb-2">
                <span class="text-muted font-bold text-sm">SHARES</span>
                <span class="font-black text-2xl text-main"><?= $shareCount ?></span>
            </div>
            <svg viewBox="0 0 100 40" class="w-full h-10" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="sFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10b981" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <path d="<?= $sFill ?>" fill="url(#sFill)"/>
                <path d="<?= $sPath ?>" class="graph-line" stroke="#10b981" stroke-width="1.5" fill="none"/>
            </svg>
        </div>
    </div>

    <!-- Main Editor Grid -->
    <div class="mgmt-grid">
        <div class="flex flex-col gap-6">
            <!-- 1. Details Form -->
            <div class="mgmt-card">
                <h3 class="font-bold text-lg text-main mb-4">Basic Information</h3>
                <form method="post">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="update_basic_details">
                    
                    <div class="form-group mb-4">
                        <label class="font-bold mb-1 block small text-muted">Listing Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($product['title']) ?>" class="premium-input w-full" style="padding: 0.75rem 1rem;" required maxlength="100">
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-bold mb-1 block small text-muted">Description &amp; Story</label>
                        <textarea name="description" rows="5" class="premium-input w-full" style="padding: 0.85rem 1rem;" required><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="font-bold mb-1 block small text-muted">Location / Campus Town</label>
                            <select name="location_town" id="mgmt_town_select" class="premium-input w-full" style="padding: 0.75rem 1rem;">
                                <?php foreach (locationTownSlugs() as $townSlug): ?>
                                <option value="<?= $townSlug ?>" <?= ($product['location_town'] === $townSlug) ? 'selected' : '' ?>>
                                    <?= formatLocationTown($townSlug) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="mgmt_custom_loc_box" style="<?= ($product['location_town'] === 'other') ? '' : 'display:none;' ?>">
                            <label class="font-bold mb-1 block small text-muted">Custom Location Name</label>
                            <input type="text" name="custom_location" value="<?= htmlspecialchars($product['custom_location'] ?? '') ?>" class="premium-input w-full" style="padding: 0.75rem 1rem;" maxlength="100">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.4rem; border-radius: var(--radius-md);">
                        Save Details
                    </button>
                </form>
            </div>

            <!-- 2. Service Specific Settings -->
            <?php if ($isService): ?>
            <div class="mgmt-card">
                <h3 class="font-bold text-lg text-main mb-4">Service &amp; Availability Settings</h3>
                <form method="post">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="update_service_details">

                    <div class="form-group mb-4">
                        <label class="font-bold mb-2 block small text-muted">Current Availability Status</label>
                        <select name="availability_status" class="premium-input w-full" style="padding: 0.75rem 1rem;">
                            <option value="available" <?= ($product['availability_status'] ?? 'available') === 'available' ? 'selected' : '' ?>>🟢 Available — taking new clients</option>
                            <option value="busy" <?= ($product['availability_status'] ?? '') === 'busy' ? 'selected' : '' ?>>🟡 Busy — open but might be slow</option>
                            <option value="unavailable" <?= ($product['availability_status'] ?? '') === 'unavailable' ? 'selected' : '' ?>>🔴 Unavailable — on a break</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="font-bold mb-1 block small text-muted">Turnaround Time</label>
                            <select name="delivery_days" class="premium-input w-full" style="padding: 0.75rem 1rem;">
                                <option value="0" <?= empty($product['delivery_days']) ? 'selected' : '' ?>>Varies / Let's discuss</option>
                                <option value="1" <?= (int)($product['delivery_days'] ?? 0) === 1 ? 'selected' : '' ?>>Same day</option>
                                <option value="2" <?= (int)($product['delivery_days'] ?? 0) === 2 ? 'selected' : '' ?>>2 days</option>
                                <option value="3" <?= (int)($product['delivery_days'] ?? 0) === 3 ? 'selected' : '' ?>>3 days</option>
                                <option value="5" <?= (int)($product['delivery_days'] ?? 0) === 5 ? 'selected' : '' ?>>5 days</option>
                                <option value="7" <?= (int)($product['delivery_days'] ?? 0) === 7 ? 'selected' : '' ?>>1 week</option>
                                <option value="14" <?= (int)($product['delivery_days'] ?? 0) === 14 ? 'selected' : '' ?>>2 weeks</option>
                                <option value="30" <?= (int)($product['delivery_days'] ?? 0) === 30 ? 'selected' : '' ?>>1 month</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold mb-1 block small text-muted">Revisions Included</label>
                            <select name="revision_count" class="premium-input w-full" style="padding: 0.75rem 1rem;">
                                <option value="" <?= !isset($product['revision_count']) ? 'selected' : '' ?>>Not specified</option>
                                <option value="0" <?= isset($product['revision_count']) && (int)$product['revision_count'] === 0 ? 'selected' : '' ?>>None</option>
                                <option value="1" <?= (int)($product['revision_count'] ?? -1) === 1 ? 'selected' : '' ?>>1 revision</option>
                                <option value="2" <?= (int)($product['revision_count'] ?? -1) === 2 ? 'selected' : '' ?>>2 revisions</option>
                                <option value="3" <?= (int)($product['revision_count'] ?? -1) === 3 ? 'selected' : '' ?>>3 revisions</option>
                                <option value="5" <?= (int)($product['revision_count'] ?? -1) === 5 ? 'selected' : '' ?>>5 revisions</option>
                                <option value="unlimited" <?= (int)($product['revision_count'] ?? -1) === 99 ? 'selected' : '' ?>>Unlimited</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-bold mb-1 block small text-muted">Portfolio or Social URL</label>
                        <input type="url" name="portfolio_link" value="<?= htmlspecialchars($product['portfolio_link'] ?? '') ?>" placeholder="https://instagram.com/yourhandle" class="premium-input w-full" style="padding: 0.75rem 1rem;" maxlength="500">
                    </div>

                    <button type="submit" class="btn btn--service" style="padding: 0.65rem 1.4rem; border-radius: var(--radius-md);">
                        Save Service Settings
                    </button>
                </form>
            </div>
            <?php else: ?>
            <!-- Category Management for Products -->
            <div class="mgmt-card">
                <h3 class="font-bold text-lg text-main mb-4">Categories</h3>
                <form method="post">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="update_categories">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                        <?php
                            $curCatIds = array_map(function($r){ return (int)$r['id']; }, $productCategories);
                            foreach ($categories as $cat):
                                if (($cat['type'] ?? 'product') === 'service') continue;
                                $cid = (int)$cat['id'];
                        ?>
                        <label class="flex items-center gap-2 rounded-lg border px-3 py-2" style="border-color: var(--border-light); background: var(--bg-surface);">
                            <input type="checkbox" name="category_ids[]" value="<?= $cid ?>" <?= in_array($cid, $curCatIds, true) ? 'checked' : '' ?>>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?= sanitize(translateCategory($cat['name'])) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="padding: 0.65rem 1.4rem; border-radius: var(--radius-md);">
                        Update Categories
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Column (Pricing, Gallery & Danger Zone) -->
        <div class="flex flex-col gap-6">
            <!-- 1. Pricing Strategy -->
            <div class="mgmt-card">
                <h3 class="font-bold text-lg text-main mb-4">Pricing Strategy</h3>
                <form method="post" class="flex flex-col gap-3">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="update_pricing">

                    <div>
                        <label class="font-bold mb-1 block small text-muted">Price</label>
                        <div class="flex gap-2">
                            <input type="number" name="new_price" step="0.01" min="0.01" value="<?= (float)$product['price'] ?>" class="premium-input flex-1" style="padding: 0.65rem 0.85rem;" required>
                            <select name="price_currency" class="premium-input" style="padding: 0.65rem 0.85rem;">
                                <?php
                                    $selectedCurrency = strtoupper(trim((string)($product['price_currency'] ?? DEFAULT_PRODUCT_CURRENCY)));
                                    foreach (PRODUCT_CURRENCIES as $code => $meta):
                                ?>
                                    <option value="<?= $code ?>" <?= ($selectedCurrency === $code) ? 'selected' : '' ?>><?= $code ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold mb-1 block small text-muted">Promotional Discount</label>
                        <select name="discount_percent" class="premium-input w-full" style="padding: 0.65rem 0.85rem;">
                            <?php foreach ([0, 5, 10, 15, 20, 25, 30, 40, 50] as $d): ?>
                                <option value="<?= $d ?>" <?= ((int)($product['discount_percent'] ?? 0) === $d) ? 'selected' : '' ?>>
                                    <?= $d === 0 ? 'No discount' : ('-' . $d . '% OFF') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary mt-2" style="padding: 0.65rem 1rem; border-radius: var(--radius-md);">
                        Update Price
                    </button>
                </form>
            </div>

            <!-- 2. Photo Gallery Manager -->
            <div class="mgmt-card">
                <h3 class="font-bold text-lg text-main mb-3">Photos (<?= count($images) ?>/5)</h3>
                
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <?php foreach ($images as $img): ?>
                    <div class="relative group rounded-lg overflow-hidden border border-slate-200 aspect-square" style="background: var(--bg-surface);">
                        <img src="<?= getProductImage($img['image_path']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1 p-1">
                            <?php if (!$img['is_primary']): ?>
                            <form method="post">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="action" value="set_primary">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button type="submit" title="Make Primary" class="btn btn-sm" style="background: white; color: var(--primary); padding: 0.3rem 0.5rem; font-size: 0.75rem;">★</button>
                            </form>
                            <?php endif; ?>

                            <?php if (count($images) > 1): ?>
                            <form method="post" onsubmit="return confirm('Delete this image?')">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button type="submit" title="Delete" class="btn btn-sm btn--danger" style="padding: 0.3rem 0.5rem; font-size: 0.75rem;">✕</button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($img['is_primary']): ?>
                        <span style="position: absolute; bottom: 4px; left: 4px; background: var(--primary); color: white; font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 4px;">MAIN</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($images) < 5): ?>
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="add_images">
                    <label class="btn btn-secondary btn-sm w-full cursor-pointer text-center" style="display: block; border-radius: var(--radius-md);">
                        + Add Photos
                        <input type="file" name="images[]" multiple accept="image/*" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
                <?php endif; ?>
            </div>

            <!-- 3. Actions / Danger Zone -->
            <div class="mgmt-card" style="border-color: #fecaca; background: #fff5f5;">
                <h3 class="font-bold text-lg mb-3" style="color: #b91c1c;">Listing Actions</h3>
                <div class="flex flex-col gap-2">
                    <form method="post" onsubmit="return confirm('<?= $isService ? "Mark this service completed?" : "Mark this item as sold?" ?>')">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="action" value="mark_sold">
                        <button type="submit" class="btn btn-secondary w-full" style="border-radius: var(--radius-md); font-weight: 700;">
                            ✓ <?= $isService ? 'Mark as Completed' : 'Mark as Sold' ?>
                        </button>
                    </form>

                    <form method="post" onsubmit="return confirm('Move this listing to the recycle bin?')">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="action" value="delete_listing">
                        <button type="submit" class="btn w-full" style="background: #ef4444; color: white; border: none; border-radius: var(--radius-md); font-weight: 700;">
                            🗑 Delete Listing
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const townSelect = document.getElementById('mgmt_town_select');
    const customBox = document.getElementById('mgmt_custom_loc_box');
    if (townSelect && customBox) {
        townSelect.addEventListener('change', function() {
            customBox.style.display = (this.value === 'other') ? 'block' : 'none';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
