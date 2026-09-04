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
            $stmt = $pdo->prepare("SELECT user_id, title, listing_type FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if ($prod) {
                $approvalStatus = 'active';
                $serviceExpiresAt = null;
                $approvalMessage = "Your listing for '" . $prod['title'] . "' has been approved and is now active.";

                if (($prod['listing_type'] ?? 'product') === 'service') {
                    $svcPricing = getServicePricingSettings($pdo);
                    $otherServicesStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND listing_type = 'service' AND status != 'deleted' AND id <> ?");
                    $otherServicesStmt->execute([(int)$prod['user_id'], $id]);
                    $isFreeService = $svcPricing['free_trial_enabled'] && (int)$otherServicesStmt->fetchColumn() === 0;

                    if ($isFreeService) {
                        $listingDays = (int)$svcPricing['listing_days'];
                        $approvalMessage = "Your first service listing has been approved and is active for {$listingDays} days at no charge.";
                        $serviceExpiresAt = date('Y-m-d H:i:s', strtotime("+{$listingDays} days"));
                    } else {
                        $approvalStatus = 'pending_payment';
                        $approvalMessage = "Your service listing has been approved. Choose a payment plan to activate it.";
                    }
                }

                $stmtUpdate = $pdo->prepare("UPDATE products SET status = ?, service_expires_at = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$approvalStatus, $serviceExpiresAt, $id]);
                try {
                    $pdo->prepare("UPDATE products SET moderation_note = NULL WHERE id = ?")->execute([$id]);
                } catch (PDOException $e) {
                    // moderation_note column may not exist until migration is applied
                }
                // Notify the seller
                createNotification($pdo, (int)$prod['user_id'], 'system', 'Listing Approved', $approvalMessage, $id);
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
        } elseif ($action === 'admin_boost') {
            // Admin-initiated feature boost — no payment required
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
                    $pdo->prepare("
                        UPDATE products
                        SET is_featured = TRUE,
                            featured_until = DATE_ADD(NOW(), INTERVAL :days DAY),
                            updated_at = NOW()
                        WHERE id = :id
                    ")->execute([':days' => $durationDays, ':id' => $id]);
                }
                createNotification(
                    $pdo,
                    (int)$prod['user_id'],
                    'system',
                    'Your listing is now Featured! 🎉',
                    "Your listing '" . $prod['title'] . "' has been selected to appear in the Featured Spotlight for {$durationDays} day(s).",
                    $id
                );
                setFlash('success', "Listing boosted and featured for {$durationDays} day(s).");
                logAdminAction($pdo, 'admin_boost_listing', 'product', $id, [
                    'duration_days' => $durationDays,
                    'reason'        => $reason ?: 'No reason provided',
                ]);
            } else {
                setFlash('error', 'Listing not found or is not active.');
            }
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
                            <?php if ($item['status'] === 'pending_approval' && !empty($item['moderation_note'])): ?>
                                <div style="font-size: 0.78rem; color: #1d4ed8; margin-top: 0.35rem; line-height: 1.45; max-width: 28rem;">
                                    <?php echo sanitize($item['moderation_note']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light); color: var(--primary);">@<?php echo sanitize($item['seller_name']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);"><span class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); border-radius: var(--radius-lg);"><?php echo sanitize($item['category_name']); ?></span></td>
                        <td class="p-4 font-bold text-main" style="border-bottom: 1px solid var(--border-light); font-size: 1.1rem;"><?php echo formatPrice($item['price'], productCurrencyCode($item)); ?></td>
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
                                    <button type="button"
                                        class="btn btn-sm hover-scale shadow-sm"
                                        style="border-radius: var(--radius-lg); background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd;"
                                        onclick="openBoostModal(<?php echo $item['id']; ?>, '<?php echo addslashes(sanitize($item['title'])); ?>')"
                                        title="Feature this listing for free (Admin Boost)">
                                        🚀 Boost
                                    </button>
                                <?php endif; ?>
                                <a href="../pages/product.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn btn-primary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">View</a>
                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return handleListingDelete(this);">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="reason" value="">
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
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="admin_boost">
            <input type="hidden" name="id" id="boostProductId" value="">

            <div class="mb-4">
                <label class="font-bold mb-2 block" style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Feature Duration</label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days', 0 => 'No expiry'] as $days => $label): ?>
                        <button type="button"
                            class="boost-duration-btn <?php echo $days === 7 ? 'active' : ''; ?>"
                            data-days="<?php echo $days; ?>"
                            onclick="selectBoostDuration(this)">
                            <?php echo $label; ?>
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
