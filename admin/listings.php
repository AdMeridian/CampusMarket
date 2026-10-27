<?php
// admin/listings.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/admin_audit.php';

$pageTitle = __('admin.manage_listings');
$promoPaymentsTableExists = false;
try {
    $promoPaymentsTableExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'promotion_payments' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    $promoPaymentsTableExists = false;
}

// Handle Actions (Secured via POST & CSRF validation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $action = $_POST['action'];

        if ($action === 'delete') {
            $stmt = $pdo->prepare("SELECT user_id, title FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();

            if ($prod) {
                permanentlyDeleteProduct($pdo, $id);

                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
                $titleText = "Listing Rejected & Deleted";
                if ($reason !== '') {
                    $bodyText = "Your listing for '" . $prod['title'] . "' was not approved and has been deleted. Reason: " . $reason;
                } else {
                    $bodyText = "Your listing for '" . $prod['title'] . "' was not approved and has been deleted.";
                }

                createNotification($pdo, (int)$prod['user_id'], 'system', $titleText, $bodyText, null);
                
                setFlash('success', __('admin.flash_listing_deleted'));
                logAdminAction($pdo, 'delete_listing', 'product', $id, ['title' => $prod['title'], 'reason' => $reason]);
            } else {
                setFlash('error', 'Listing not found.');
            }
        } elseif ($action === 'approve') {
            // Fetch listing details to notify the owner
            $stmt = $pdo->prepare("SELECT user_id, title, listing_type, category_id FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if ($prod) {
                $approvalStatus = 'active';
                $approvalMessage = "Your listing for '" . $prod['title'] . "' has been approved and is now active.";

                if (($prod['listing_type'] ?? 'product') === 'service') {
                    $approvalMessage = "Your service listing has been approved and is now visible in the services directory.";
                }

                $stmtUpdate = $pdo->prepare("UPDATE products SET status = ?, service_expires_at = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$approvalStatus, null, $id]);
                try {
                    $pdo->prepare("UPDATE products SET moderation_note = NULL WHERE id = ?")->execute([$id]);
                } catch (PDOException $e) {
                    // moderation_note column may not exist until migration is applied
                }
                // Notify the seller
                createNotification($pdo, (int)$prod['user_id'], 'system', 'Listing Approved', $approvalMessage, $id);

                if (($prod['listing_type'] ?? 'product') === 'service' && function_exists('queuePostHogEvent')) {
                    queuePostHogEvent('service_listing_approved', [
                        'product_id' => $id,
                        'user_id' => (int)$prod['user_id'],
                        'approval_status' => $approvalStatus,
                        'category_id' => (int)($prod['category_id'] ?? 0)
                    ]);
                }

                setFlash('success', __('admin.flash_listing_approved'));
                logAdminAction($pdo, 'approve_listing', 'product', $id, ['title' => $prod['title']]);
            } else {
                setFlash('error', 'Listing not found.');
            }
        } elseif ($action === 'feature') {
            if (!$promoPaymentsTableExists) {
                setFlash('error', 'Promotion payment table is missing. Apply the schema update first.');
                redirect(BASE_URL . 'admin/listings.php');
            }

            // FEAT requires an approved, unused promotion payment for this listing.
            $payStmt = $pdo->prepare("
                SELECT id
                FROM promotion_payments
                WHERE product_id = :pid
                  AND payment_type = 'promotion'
                  AND status = 'approved'
                  AND consumed_at IS NULL
                ORDER BY approved_at ASC, created_at ASC
                LIMIT 1
            ");
            $payStmt->execute([':pid' => $id]);
            $paymentId = (int)($payStmt->fetchColumn() ?: 0);

            if ($paymentId <= 0) {
                setFlash('error', 'Cannot FEAT this listing yet. Seller needs an approved promotion payment.');
                redirect(BASE_URL . 'admin/listings.php');
            }

            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE products SET is_featured = TRUE WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE promotion_payments SET consumed_at = NOW(), consumed_for = 'feature' WHERE id = :id")
                    ->execute([':id' => $paymentId]);
                $pdo->commit();
                triggerFeaturedListingAlert($pdo, $id);
                setFlash('success', 'Listing featured using approved promotion payment.');
                logAdminAction($pdo, 'feature_listing', 'product', $id, ['payment_id' => $paymentId]);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('error', 'Unable to feature listing right now.');
            }
        } elseif ($action === 'unfeature') {
            $stmt = $pdo->prepare("UPDATE products SET is_featured = FALSE WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Listing unfeatured.');
            logAdminAction($pdo, 'unfeature_listing', 'product', $id);
        } elseif ($action === 'admin_boost') {
            // Admin-initiated feature boost — database driver agnostic
            $rawDays = (int)($_POST['boost_days'] ?? 7);
            $noExpiry = ($rawDays === 0);
            $durationDays = $noExpiry ? 0 : max(1, min(90, $rawDays));
            $reason = trim(strip_tags($_POST['boost_reason'] ?? ''));
            $stmt = $pdo->prepare("SELECT id, title, user_id FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if ($prod) {
                if ($noExpiry) {
                    $pdo->prepare("
                        UPDATE products
                        SET is_featured = TRUE, featured_until = NULL, updated_at = NOW()
                        WHERE id = :id
                    ")->execute([':id' => $id]);
                } else {
                    $featuredUntil = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
                    $pdo->prepare("
                        UPDATE products
                        SET is_featured = TRUE,
                            featured_until = :until,
                            updated_at = NOW()
                        WHERE id = :id
                    ")->execute([':until' => $featuredUntil, ':id' => $id]);
                }
                createNotification(
                    $pdo,
                    (int)$prod['user_id'],
                    'system',
                    'Your listing is now Featured! 🎉',
                    "Your listing '" . $prod['title'] . "' has been selected to appear in the Featured Spotlight for {$durationDays} day(s).",
                    $id
                );
                triggerFeaturedListingAlert($pdo, $id);
                setFlash('success', "Listing boosted and featured for {$durationDays} day(s).");
                logAdminAction($pdo, 'admin_boost_listing', 'product', $id, [
                    'duration_days' => $durationDays,
                    'reason'        => $reason ?: 'No reason provided',
                ]);
            } else {
                setFlash('error', 'Listing not found or is not active.');
            }
        } elseif ($action === 'toggle_recent_fallback') {
            $stmt = $pdo->prepare("SELECT id, title, is_recent_fallback FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if ($prod) {
                $newState = empty($prod['is_recent_fallback']) ? 1 : 0;
                $updateStmt = $pdo->prepare("UPDATE products SET is_recent_fallback = ? WHERE id = ?");
                $updateStmt->execute([$newState, $id]);
                logAdminAction($pdo, 'toggle_recent_fallback', 'product', $id, [
                    'title' => $prod['title'],
                    'is_recent_fallback' => (bool)$newState
                ]);
                if ($newState) {
                    setFlash('success', __('admin.flash_recent_fallback_added'));
                } else {
                    setFlash('success', __('admin.flash_recent_fallback_removed'));
                }
            } else {
                setFlash('error', 'Listing not found.');
            }
        }
    }

    $redirectUrl = BASE_URL . 'admin/listings.php';
    $params = [];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    if (!empty($_GET['type']))   $params['type']   = $_GET['type'];
    if (!empty($_GET['q']))      $params['q']      = $_GET['q'];
    if (!empty($_GET['page']))   $params['page']   = (int)$_GET['page'];
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    redirect($redirectUrl);
}

// Helper for building filter URLs preserving other query parameters
function adminListingsUrl(array $overrides = []): string {
    $current = [
        'status' => $_GET['status'] ?? '',
        'type'   => $_GET['type'] ?? '',
        'q'      => $_GET['q'] ?? '',
        'page'   => 1,
    ];
    $merged = array_merge($current, $overrides);
    $filtered = array_filter($merged, function ($val, $key) {
        if ($key === 'page' && (int)$val <= 1) return false;
        return $val !== '' && $val !== null;
    }, ARRAY_FILTER_USE_BOTH);

    $qs = http_build_query($filtered);
    return 'listings.php' . ($qs !== '' ? '?' . $qs : '');
}

// Filter handling
$statusFilter = sanitize($_GET['status'] ?? '');
$typeFilter   = sanitize($_GET['type'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$allowedStatuses = ['pending_approval', 'active', 'flagged', 'sold', 'deleted', 'recent_fallback'];
$whereConditions = [];
$params = [];

// Status condition
if ($statusFilter === 'recent_fallback') {
    $whereConditions[] = "p.is_recent_fallback = TRUE AND p.status = 'active'";
} elseif ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereConditions[] = "p.status = :status";
    $params[':status'] = $statusFilter;
}

// Type condition (product vs service)
if ($typeFilter === 'product' || $typeFilter === 'service') {
    $whereConditions[] = "p.listing_type = :listing_type";
    $params[':listing_type'] = $typeFilter;
}

// Search condition (title, seller username, or listing ID)
if ($searchQuery !== '') {
    if (ctype_digit($searchQuery)) {
        $whereConditions[] = "(p.title LIKE :search_title OR u.username LIKE :search_user OR p.id = :search_id)";
        $params[':search_title'] = '%' . $searchQuery . '%';
        $params[':search_user']  = '%' . $searchQuery . '%';
        $params[':search_id']    = (int)$searchQuery;
    } else {
        $whereConditions[] = "(p.title LIKE :search_title OR u.username LIKE :search_user)";
        $params[':search_title'] = '%' . $searchQuery . '%';
        $params[':search_user']  = '%' . $searchQuery . '%';
    }
}

$whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total listings matching current filters for pagination
$countSql = "
    SELECT COUNT(*)
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    {$whereSql}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalMatching / $perPage));
if ($page > $totalPages && $totalMatching > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Main Query with primary image thumbnail & promo credits
$promoCreditSelect = $promoPaymentsTableExists ? "
    (
        SELECT COUNT(*)
        FROM promotion_payments pp
        WHERE pp.product_id = p.id
          AND pp.payment_type = 'promotion'
          AND pp.status = 'approved'
          AND pp.consumed_at IS NULL
    ) AS available_promo_credits
" : "0 AS available_promo_credits";

$sql = "
    SELECT p.*, c.name as category_name, u.username as seller_name, pi.image_path as primary_image,
           {$promoCreditSelect}
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    {$whereSql}
    ORDER BY p.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Global Counters for Top Navigation Badges
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending_approval'")->fetchColumn();
$fallbackCount = 0;
try {
    $fallbackCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_recent_fallback = TRUE AND status = 'active'")->fetchColumn();
} catch (PDOException $e) {
    $fallbackCount = 0;
}
$flaggedCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'flagged'")->fetchColumn();

// Type breakdown counts
$productCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE (listing_type = 'product' OR listing_type IS NULL) AND status != 'deleted'")->fetchColumn();
$serviceCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE listing_type = 'service' AND status != 'deleted'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-16 admin-listings-page">
    <!-- Header & Action Bar -->
    <div class="flex justify-between items-end mb-6 admin-page-toolbar flex-wrap gap-4">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> &gt; Listings</div>
            <h1 class="mb-1 text-main font-bold">Listing Management</h1>
            <p class="text-muted small mb-0">Review physical goods and campus student services, approve submissions, and feature listings.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="promotion_payments.php" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg);">
                💳 Review Payments
            </a>
            <div class="badge" style="background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border-light); font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg); font-weight: 600;">
                Showing <?= number_format($totalMatching) ?> matching
            </div>
        </div>
    </div>

    <!-- Quick Stats Metric Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 0.85rem; background: var(--bg-surface);">
            <div style="width: 42px; height: 42px; border-radius: var(--radius-lg); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                📦
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Physical Products</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.2;"><?= number_format($productCount) ?></div>
            </div>
        </div>

        <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid #0284c7; display: flex; align-items: center; gap: 0.85rem; background: var(--bg-surface);">
            <div style="width: 42px; height: 42px; border-radius: var(--radius-lg); background: rgba(14, 165, 233, 0.12); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                🛠️
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Campus Services</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.2;"><?= number_format($serviceCount) ?></div>
            </div>
        </div>

        <div class="card" style="padding: 1rem 1.25rem; border-left: 4px solid #f59e0b; display: flex; align-items: center; gap: 0.85rem; background: var(--bg-surface);">
            <div style="width: 42px; height: 42px; border-radius: var(--radius-lg); background: rgba(245, 158, 11, 0.12); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                ⏳
            </div>
            <div>
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Pending Moderation</div>
                <div style="font-size: 1.35rem; font-weight: 800; color: <?= $pendingCount > 0 ? '#d97706' : 'var(--text-main)' ?>; line-height: 1.2;"><?= number_format($pendingCount) ?></div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="glass-panel p-4 mb-6" style="border-radius: var(--radius-xl); border: 1px solid var(--border-light); background: var(--bg-surface);">
        <div class="flex flex-col md-flex-row justify-between items-center gap-4 flex-wrap">
            <!-- Type Selector Pills -->
            <div class="flex items-center gap-1 p-1 bg-main rounded-lg" style="border: 1px solid var(--border-light); background: var(--bg-main); border-radius: var(--radius-lg);">
                <a href="<?= adminListingsUrl(['type' => '']) ?>"
                   class="btn btn-sm <?= $typeFilter === '' ? 'btn-primary' : 'text-muted' ?>"
                   style="border-radius: var(--radius-md); padding: 0.35rem 0.85rem; font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                    All Types
                </a>
                <a href="<?= adminListingsUrl(['type' => 'product']) ?>"
                   class="btn btn-sm <?= $typeFilter === 'product' ? 'btn-primary' : 'text-muted' ?>"
                   style="border-radius: var(--radius-md); padding: 0.35rem 0.85rem; font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                    📦 Products
                </a>
                <a href="<?= adminListingsUrl(['type' => 'service']) ?>"
                   class="btn btn-sm <?= $typeFilter === 'service' ? 'btn-primary' : 'text-muted' ?>"
                   style="border-radius: var(--radius-md); padding: 0.35rem 0.85rem; font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                    🛠️ Services
                </a>
            </div>

            <!-- Search Bar -->
            <form method="GET" action="listings.php" class="flex items-center gap-2 m-0" style="flex: 1; max-width: 480px; width: 100%;">
                <?php if ($statusFilter !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <?php endif; ?>
                <?php if ($typeFilter !== ''): ?>
                    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                <?php endif; ?>
                <div style="position: relative; flex: 1;">
                    <input type="text"
                           name="q"
                           value="<?= htmlspecialchars($searchQuery) ?>"
                           placeholder="Search title, seller @username, or #ID..."
                           class="premium-input w-full"
                           style="padding: 0.5rem 0.85rem 0.5rem 2.2rem; font-size: 0.88rem; border-radius: var(--radius-lg);">
                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.85rem; color: var(--text-muted); pointer-events: none;">
                        🔍
                    </span>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="<?= adminListingsUrl(['q' => '']) ?>"
                           style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); text-decoration: none; font-size: 0.9rem;"
                           title="Clear search">
                            ✕
                        </a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 0.9rem; font-weight: 600;">
                    Search
                </button>
            </form>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex gap-2 mt-4 pt-3 border-t flex-wrap" style="border-color: var(--border-light);">
            <a href="<?= adminListingsUrl(['status' => '']) ?>"
               class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?>"
               style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                All Statuses
            </a>
            <a href="<?= adminListingsUrl(['status' => 'pending_approval']) ?>"
               class="btn btn-sm <?= $statusFilter === 'pending_approval' ? 'btn-primary' : 'btn-secondary' ?>"
               style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                Pending Approval<?= $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' ?>
            </a>
            <a href="<?= adminListingsUrl(['status' => 'active']) ?>"
               class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-primary' : 'btn-secondary' ?>"
               style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                Active
            </a>
            <a href="<?= adminListingsUrl(['status' => 'recent_fallback']) ?>"
               class="btn btn-sm <?= $statusFilter === 'recent_fallback' ? 'btn-primary' : 'btn-secondary' ?>"
               style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                📌 Recent Fallback<?= $fallbackCount > 0 ? ' (' . $fallbackCount . ')' : '' ?>
            </a>
            <?php if ($flaggedCount > 0): ?>
            <a href="<?= adminListingsUrl(['status' => 'flagged']) ?>"
               class="btn btn-sm <?= $statusFilter === 'flagged' ? 'btn-danger' : 'btn-secondary' ?>"
               style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                ⚠️ Flagged (<?= $flaggedCount ?>)
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Listings Table Container -->
    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left admin-listings-table" style="border-collapse: collapse; margin: 0; min-width: 980px;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.9);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 34%;">Item / Service</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 12%;">Seller</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 13%;">Category</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 11%;">Price</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 14%;">Details / Loc</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light); width: 10%;">Submitted</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light); width: 16%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $item): ?>
                    <?php 
                        $isService = (($item['listing_type'] ?? 'product') === 'service');
                        $isHourly  = $isService && (($item['pricing_model'] ?? 'flat') === 'hourly');
                        $itemCurr  = productCurrencyCode($item);
                        $priceData = $item['price'] === null ? null : formatPriceInTRY((float)$item['price'], $itemCurr, $pdo);
                        $thumbUrl  = $isService
                            ? getServiceImage($item['primary_image'] ?? null)
                            : getProductImage($item['primary_image'] ?? null);
                    ?>
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='transparent'">
                        <!-- Item / Service Column with Thumbnail -->
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex items-start gap-3">
                                <a href="../pages/product.php?id=<?= $item['id'] ?>" target="_blank" style="flex-shrink: 0; text-decoration: none;">
                                    <img src="<?= htmlspecialchars($thumbUrl) ?>"
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         style="width: 48px; height: 48px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid var(--border-light); background: var(--bg-main);">
                                </a>
                                <div class="listing-title-cell" style="flex: 1; min-width: 0;">
                                    <div class="font-bold flex items-center gap-2" style="line-height: 1.35; word-break: break-word;">
                                        <a href="../pages/product.php?id=<?= $item['id'] ?>" target="_blank" style="color: var(--text-main); text-decoration: none;">
                                            <?= sanitize($item['title']) ?>
                                        </a>
                                    </div>
                                    <div class="listing-badge-row mt-1">
                                        <!-- Type Badge -->
                                        <?php if ($isService): ?>
                                            <span class="badge" style="background: rgba(14, 165, 233, 0.12); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.3); font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 700;">
                                                🛠️ Service
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: #475569; border: 1px solid rgba(100, 116, 139, 0.25); font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 600;">
                                                📦 Product
                                            </span>
                                        <?php endif; ?>

                                        <!-- Status Badges -->
                                        <?php if ($item['status'] === 'pending_approval'): ?>
                                            <span class="badge badge-pending" style="font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);">Pending Approval</span>
                                        <?php elseif ($item['status'] === 'pending_payment'): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);">Pending Payment</span>
                                        <?php elseif ($item['status'] === 'flagged'): ?>
                                            <span class="badge badge-poor" style="font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);">Flagged</span>
                                        <?php elseif ($item['status'] === 'sold'): ?>
                                            <span class="badge badge-dismissed" style="font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);">Sold</span>
                                        <?php endif; ?>

                                        <!-- Feature / Fallback Badges -->
                                        <?php if ($item['is_featured']): ?>
                                            <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 700;">⚡ Featured</span>
                                        <?php endif; ?>
                                        <?php if ((int)$item['available_promo_credits'] > 0): ?>
                                            <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);"><?= (int)$item['available_promo_credits'] ?> Promo Credit</span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['is_recent_fallback'])): ?>
                                            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);" title="Included in Recent Listings Fallback">📌 Recent Fallback</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 0.2rem;">ID #<?= $item['id'] ?></div>
                                    <?php if ($item['status'] === 'pending_approval' && !empty($item['moderation_note'])): ?>
                                        <div style="font-size: 0.76rem; color: #1d4ed8; margin-top: 0.35rem; line-height: 1.4; background: rgba(29, 78, 216, 0.06); padding: 0.35rem 0.6rem; border-radius: var(--radius-sm); border-left: 2px solid #1d4ed8;">
                                            <?= sanitize($item['moderation_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Seller Column -->
                        <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light);">
                            <a href="../pages/profile.php?id=<?= (int)$item['user_id'] ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                @<?= sanitize($item['seller_name']) ?>
                            </a>
                        </td>

                        <!-- Category Column -->
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600;">
                                <?= sanitize(translateCategory($item['category_name'])) ?>
                            </span>
                        </td>

                        <!-- Price Column -->
                        <td class="p-4 font-bold text-main" style="border-bottom: 1px solid var(--border-light); font-size: 1.05rem;">
                            <?php if ($priceData === null): ?>
                                <span class="text-muted" style="font-size:0.82rem;font-weight:500;">No asking rate</span>
                            <?php else: ?>
                            <?= $priceData['display'] ?>
                            <?php if ($isHourly): ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">/hr</span>
                            <?php endif; ?>
                            <?php if ($priceData['original']): ?>
                                <small style="display:block; font-size:0.72rem; color:var(--text-muted); font-weight:500;">
                                    <?= $priceData['original'] ?>
                                </small>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <!-- Type & Details / Location Column -->
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if ($isService): ?>
                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                    <div>
                                        <?php if ($isHourly): ?>
                                            <span class="badge" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 600;">
                                                Hourly Rate
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 600;">
                                                💼 Fixed Price
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['location_town'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem;">
                                            <span>📍</span>
                                            <span><?= sanitize(formatLocationTown($item['location_town'], $item['custom_location'] ?? null)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!$isHourly && !empty($item['delivery_days'])): ?>
                                        <div style="font-size: 0.74rem; color: var(--text-muted);">
                                            ⚡ <?= (int)$item['delivery_days'] ?>d turnaround
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php $badge = conditionBadge($item['condition'] ?? ''); ?>
                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                    <div>
                                        <span class="badge <?= $badge['class'] ?>" style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 600;">
                                            <?= $badge['label'] ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['location_town'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem;">
                                            <span>📍</span>
                                            <span><?= sanitize(formatLocationTown($item['location_town'], $item['custom_location'] ?? null)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Submitted Timestamp Column -->
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-muted);" title="<?= date('M j, Y g:i A', strtotime($item['created_at'])) ?>">
                            <?= timeAgo($item['created_at']) ?>
                        </td>

                        <!-- Actions Column -->
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                            <div class="admin-action-row" style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                <?php if ($item['status'] === 'pending_approval'): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.65rem; font-size: 0.78rem;" title="Approve Listing">
                                            ✓ Approve
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($item['status'] === 'active'): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="toggle_recent_fallback">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <?php if (!empty($item['is_recent_fallback'])): ?>
                                            <button type="submit" class="btn btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.6rem; font-size: 0.78rem; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;" title="Remove from Recent Listings Fallback">
                                                📌 ON
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.6rem; font-size: 0.78rem;" title="Add to Recent Listings Fallback">
                                                📌 +Fallback
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <?php if ($item['is_featured']): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="unfeature">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.6rem; font-size: 0.78rem;" title="Unfeature">
                                            UNFEAT
                                        </button>
                                    </form>
                                <?php elseif ((int)$item['available_promo_credits'] > 0): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="feature">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.6rem; font-size: 0.78rem;" title="Feature using approved credit">
                                            FEAT
                                        </button>
                                    </form>
                                <?php elseif ($item['status'] === 'active'): ?>
                                    <button type="button"
                                        class="btn btn-sm hover-scale shadow-sm"
                                        style="border-radius: var(--radius-md); padding: 0.3rem 0.6rem; font-size: 0.78rem; background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd;"
                                        onclick="openBoostModal(<?= $item['id'] ?>, '<?= addslashes(sanitize($item['title'])) ?>')"
                                        title="Feature this listing for free (Admin Boost)">
                                        🚀 Boost
                                    </button>
                                <?php endif; ?>

                                <a href="../pages/product.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-primary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.65rem; font-size: 0.78rem;">
                                    View
                                </a>

                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return handleListingDelete(this);">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="reason" value="">
                                    <button type="submit" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-md); padding: 0.3rem 0.65rem; font-size: 0.78rem;" title="Delete permanently">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($listings)): ?>
            <div class="text-center p-12 text-muted">
                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🔍</div>
                <div style="font-weight: 600; font-size: 1.05rem; margin-bottom: 0.25rem;">No listings found matching criteria</div>
                <p class="small text-muted mb-4">Try clearing filters or searching with different keywords.</p>
                <a href="listings.php" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg);">Reset All Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-between items-center mt-6 flex-wrap gap-4">
            <div class="text-muted small">
                Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalMatching)) ?> of <?= number_format($totalMatching) ?> listings
            </div>
            <div class="pagination flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= adminListingsUrl(['page' => $page - 1]) ?>" class="btn-page" style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.85rem; font-weight: 600; background: var(--bg-surface); color: var(--text-main);">
                        &larr; Prev
                    </a>
                <?php endif; ?>

                <?php 
                    $rangeStart = max(1, $page - 2);
                    $rangeEnd   = min($totalPages, $page + 2);
                    if ($rangeStart > 1) {
                        echo '<a href="' . adminListingsUrl(['page' => 1]) . '" class="btn-page" style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.85rem; background: var(--bg-surface); color: var(--text-main);">1</a>';
                        if ($rangeStart > 2) echo '<span class="px-2 text-muted">…</span>';
                    }
                    for ($p = $rangeStart; $p <= $rangeEnd; $p++):
                        $isActive = ($p === $page);
                ?>
                    <a href="<?= adminListingsUrl(['page' => $p]) ?>"
                       class="btn-page <?= $isActive ? 'active' : '' ?>"
                       style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; <?= $isActive ? 'background: var(--primary); color: #fff; border: 1px solid var(--primary);' : 'background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border-light);' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($rangeEnd < $totalPages): ?>
                    <?php if ($rangeEnd < $totalPages - 1) echo '<span class="px-2 text-muted">…</span>'; ?>
                    <a href="<?= adminListingsUrl(['page' => $totalPages]) ?>" class="btn-page" style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.85rem; background: var(--bg-surface); color: var(--text-main);"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= adminListingsUrl(['page' => $page + 1]) ?>" class="btn-page" style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.85rem; font-weight: 600; background: var(--bg-surface); color: var(--text-main);">
                        Next &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.admin-listings-table th,
.admin-listings-table td {
    vertical-align: middle;
}
.listing-title-cell {
    display: grid;
    gap: 0.25rem;
}
.listing-badge-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.admin-boost-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center;
}
.admin-boost-overlay.open { display: flex; }
.admin-boost-modal {
    background: var(--bg-surface);
    border-radius: var(--radius-xl);
    padding: 2rem;
    width: 100%; max-width: 440px;
    box-shadow: var(--shadow-xl);
    border: 1px solid var(--border-light);
    animation: slideUp 0.2s ease;
}
@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
.boost-duration-btn {
    cursor: pointer; padding: 0.45rem 0.9rem;
    border-radius: var(--radius-lg);
    border: 1.5px solid var(--border-light);
    background: var(--bg-main);
    color: var(--text-muted);
    font-size: 0.85rem; font-weight: 600;
    transition: all 0.15s ease;
}
.boost-duration-btn.active,
.boost-duration-btn:hover {
    border-color: #6d28d9;
    background: #ede9fe;
    color: #6d28d9;
}
</style>

<!-- Admin Boost Modal -->
<div class="admin-boost-overlay" id="adminBoostOverlay" onclick="closeBoostModal(event)">
    <div class="admin-boost-modal" role="dialog" aria-modal="true" aria-labelledby="boostModalTitle">
        <div class="flex items-center gap-3 mb-4">
            <span style="font-size: 1.6rem;">🚀</span>
            <div>
                <h3 id="boostModalTitle" class="m-0 font-bold text-main" style="font-size: 1.1rem;">Admin Boost</h3>
                <p class="m-0 text-muted small" id="boostModalSubtitle">Feature this listing for free</p>
            </div>
        </div>

        <form method="POST" id="adminBoostForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="admin_boost">
            <input type="hidden" name="id" id="boostProductId" value="">

            <div class="mb-4">
                <label class="font-bold mb-2 block" style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Feature Duration</label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days', 0 => 'No expiry'] as $days => $label): ?>
                        <button type="button"
                            class="boost-duration-btn <?= $days === 7 ? 'active' : '' ?>"
                            data-days="<?= $days ?>"
                            onclick="selectBoostDuration(this)">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="boost_days" id="boostDaysInput" value="7">
            </div>

            <div class="mb-4">
                <label for="boostReasonInput" class="font-bold mb-2 block" style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Reason / Campaign Note <span style="font-weight:400; text-transform: none;">(optional)</span></label>
                <input type="text" id="boostReasonInput" name="boost_reason" class="w-full premium-input" placeholder="e.g. Campus Week campaign, Partner deal with BookShop Co." style="padding: 0.7rem 1rem;">
            </div>

            <div class="flex gap-3 justify-end mt-5">
                <button type="button" onclick="closeBoostModal()" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg);">Cancel</button>
                <button type="submit" class="btn btn-sm" style="border-radius: var(--radius-lg); background: #6d28d9; color: #fff;">🚀 Boost Listing</button>
            </div>
        </form>
    </div>
</div>

<script>
function handleListingDelete(form) {
    if (!confirm('Are you sure you want to delete this listing permanently?')) {
        return false;
    }
    const reason = prompt('Please enter the reason for rejection/deletion (this will be sent to the seller):');
    if (reason === null) {
        return false;
    }
    form.querySelector('input[name="reason"]').value = reason.trim();
    return true;
}

function openBoostModal(productId, title) {
    document.getElementById('boostProductId').value = productId;
    document.getElementById('boostModalSubtitle').textContent = '"' + title + '"';
    document.getElementById('boostReasonInput').value = '';
    document.getElementById('adminBoostOverlay').classList.add('open');
}

function closeBoostModal(e) {
    if (e && e.target !== document.getElementById('adminBoostOverlay')) return;
    document.getElementById('adminBoostOverlay').classList.remove('open');
}

function selectBoostDuration(btn) {
    document.querySelectorAll('.boost-duration-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('boostDaysInput').value = btn.dataset.days;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('adminBoostOverlay').classList.remove('open');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
