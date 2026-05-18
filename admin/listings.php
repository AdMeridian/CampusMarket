<?php
// admin/listings.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

// Auth Check (Admin Only)
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = "Manage Listings";
$promoPaymentsTableExists = false;
try {
    $promoPaymentsTableExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'promotion_payments' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    $promoPaymentsTableExists = false;
}

// Handle Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($_GET['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Listing deleted.');
    } elseif ($_GET['action'] === 'feature') {
        if (!$promoPaymentsTableExists) {
            setFlash('error', 'Promotion payment table is missing. Apply the schema update first.');
            redirect('listings.php');
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
            redirect('listings.php');
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE products SET is_featured = TRUE WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE promotion_payments SET consumed_at = NOW(), consumed_for = 'feature' WHERE id = :id")
                ->execute([':id' => $paymentId]);
            $pdo->commit();
            setFlash('success', 'Listing featured using approved promotion payment.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Unable to feature listing right now.');
        }
    } elseif ($_GET['action'] === 'unfeature') {
        $stmt = $pdo->prepare("UPDATE products SET is_featured = FALSE WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Listing unfeatured.');
    }

    redirect('listings.php');
}

// Fetch Listings
if ($promoPaymentsTableExists) {
    $stmt = $pdo->query("
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
        ORDER BY p.created_at DESC
    ");
} else {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, u.username as seller_name, 0 as available_promo_credits
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
}
$listings = $stmt->fetchAll();
?>

<div class="container mt-8 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> > Listings</div>
            <h1 class="mb-0">Listing Management</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="promotion_payments.php" class="btn btn-secondary btn-sm">Review Payments</a>
            <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($listings); ?> Total Listings</div>
        </div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
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
                            <div class="flex items-center gap-3">
                                <div class="font-bold flex items-center gap-2">
                                    <?php echo sanitize($item['title']); ?>
                                </div>
                                <?php if ($item['is_featured']): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);"><span>Featured</span></span>
                                <?php endif; ?>
                                <?php if ((int)$item['available_promo_credits'] > 0): ?>
                                    <span class="badge" style="background: #dcfce7; color: #166534; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: var(--radius-lg);"><?php echo (int)$item['available_promo_credits']; ?> Promo Credit</span>
                                <?php endif; ?>
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
                            <div class="flex justify-end gap-2">
                                <?php if ($item['is_featured']): ?>
                                    <a href="?action=unfeature&id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Unfeature">UNFEAT</a>
                                <?php elseif ((int)$item['available_promo_credits'] > 0): ?>
                                    <a href="?action=feature&id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" title="Feature">FEAT</a>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm opacity-50" style="border-radius: var(--radius-lg);">No credits</span>
                                <?php endif; ?>
                                <a href="../pages/product.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn btn-primary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">View</a>
                                <a href="?action=delete&id=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" onclick="return confirm('Delete this listing permanently?')">Delete</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
