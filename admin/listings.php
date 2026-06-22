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
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', __('admin.flash_listing_deleted'));
            logAdminAction($pdo, 'delete_listing', 'product', $id);
        } elseif ($action === 'approve') {
            // Fetch listing details to notify the owner
            $stmt = $pdo->prepare("SELECT user_id, title FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if ($prod) {
                $stmtUpdate = $pdo->prepare("UPDATE products SET status = 'active' WHERE id = ?");
                $stmtUpdate->execute([$id]);
                // Notify the seller
                createNotification($pdo, (int)$prod['user_id'], 'system', 'Listing Approved', "Your listing for '" . $prod['title'] . "' has been approved and is now active.", $id);
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
        }
    }

    redirect(BASE_URL . 'admin/listings.php');
}

// Fetch Listings
$statusFilter = sanitize($_GET['status'] ?? '');
$allowedStatuses = ['pending_approval', 'active', 'flagged', 'sold', 'deleted'];
$whereSql = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereSql = 'WHERE p.status = :status';
    $params[':status'] = $statusFilter;
}

if ($promoPaymentsTableExists) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, u.username as seller_name,
            (
                SELECT COUNT(*)
                FROM promotion_payments pp
                WHERE pp.product_id = p.id
                  AND pp.payment_type = 'promotion'
                  AND pp.status = 'approved'
                  AND pp.consumed_at IS NULL
            ) AS available_promo_credits
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        {$whereSql}
        ORDER BY p.created_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, u.username as seller_name, 0 as available_promo_credits
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        {$whereSql}
        ORDER BY p.created_at DESC
    ");
}
$stmt->execute($params);
$listings = $stmt->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending_approval'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-16 admin-listings-page">
    <div class="flex justify-between items-end mb-4 admin-page-toolbar">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> > Listings</div>
            <h1 class="mb-0">Listing Management</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="promotion_payments.php" class="btn btn-secondary btn-sm">Review Payments</a>
            <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($listings); ?> listings</div>
        </div>
    </div>

    <nav class="flex gap-2 mb-6 flex-wrap">
        <a href="listings.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <a href="listings.php?status=pending_approval" class="btn btn-sm <?= $statusFilter === 'pending_approval' ? 'btn-primary' : 'btn-secondary' ?>">
            Pending approval<?= $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' ?>
        </a>
        <a href="listings.php?status=active" class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-primary' : 'btn-secondary' ?>">Active</a>
    </nav>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left admin-listings-table" style="border-collapse: collapse; margin: 0; min-width: 920px;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Item Name</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Seller</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Category</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Price</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Condition</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $item): ?>
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="listing-title-cell">
                                <div class="font-bold flex items-center gap-2" style="line-height: 1.35;">
                                    <?php echo sanitize($item['title']); ?>
                                </div>
                                <div class="listing-badge-row">
                                <?php if ($item['is_featured']): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);"><span>Featured</span></span>
                                <?php endif; ?>
                                <?php if ((int)$item['available_promo_credits'] > 0): ?>
                                    <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);"><?php echo (int)$item['available_promo_credits']; ?> Promo Credit</span>
                                <?php endif; ?>
                                <?php if ($item['status'] === 'pending_approval'): ?>
                                    <span class="badge badge-pending" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);">Pending Approval</span>
                                <?php elseif ($item['status'] === 'flagged'): ?>
                                    <span class="badge badge-poor" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);">Flagged</span>
                                <?php elseif ($item['status'] === 'sold'): ?>
                                    <span class="badge badge-dismissed" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);">Sold</span>
                                <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">ID #<?php echo $item['id']; ?></div>
                        </td>
                        <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light); color: var(--primary);">@<?php echo sanitize($item['seller_name']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);"><span class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); border-radius: var(--radius-lg);"><?php echo sanitize($item['category_name']); ?></span></td>
                        <td class="p-4 font-bold text-main" style="border-bottom: 1px solid var(--border-light); font-size: 1.1rem;"><?php echo formatPrice($item['price']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php $badge = conditionBadge($item['condition']); ?>
                            <span class="badge <?php echo $badge['class']; ?> shadow-sm"><?php echo $badge['label']; ?></span>
                        </td>
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                            <div class="admin-action-row">
                                <?php if ($item['status'] === 'pending_approval'): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Approve Listing">Approve</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($item['is_featured']): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="action" value="unfeature">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Unfeature">UNFEAT</button>
                                    </form>
                                <?php elseif ((int)$item['available_promo_credits'] > 0): ?>
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="action" value="feature">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Feature">FEAT</button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm opacity-50" style="border-radius: var(--radius-lg);">No credits</span>
                                <?php endif; ?>
                                <a href="../pages/product.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn btn-primary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">View</a>
                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Delete this listing permanently?')">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Delete permanently">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($listings)): ?>
            <div class="text-center p-8 text-muted">
                No listings available on the platform.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-listings-table th,
.admin-listings-table td {
    vertical-align: middle;
}
.listing-title-cell {
    display: grid;
    gap: 0.45rem;
    max-width: 320px;
}
.listing-badge-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
