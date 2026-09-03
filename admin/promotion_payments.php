<?php
// admin/promotion_payments.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Promotion & Donation Payments';
$promoPaymentsTableExists = false;
try {
    $promoPaymentsTableExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'promotion_payments' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    $promoPaymentsTableExists = false;
}

if (!$promoPaymentsTableExists) {
    setFlash('error', 'Promotion payment table is missing. Apply the schema update first.');
    redirect(BASE_URL . 'admin/listings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $action = sanitize($_POST['action']);

    if ($action === 'update_service_pricing') {
        $listingFee = max(0.0, (float)($_POST['service_listing_fee'] ?? 30.0));
        $boostFee   = max(0.0, (float)($_POST['service_boost_fee'] ?? 30.0));
        $listingDays = max(1, (int)($_POST['service_listing_days'] ?? 30));
        $boostDays   = max(1, (int)($_POST['service_boost_days'] ?? 7));
        $freeTrialEnabled = isset($_POST['service_free_trial_enabled']) ? '1' : '0';

        setSystemSetting($pdo, 'service_listing_fee', (string)$listingFee);
        setSystemSetting($pdo, 'service_boost_fee', (string)$boostFee);
        setSystemSetting($pdo, 'service_listing_days', (string)$listingDays);
        setSystemSetting($pdo, 'service_boost_days', (string)$boostDays);
        setSystemSetting($pdo, 'service_free_trial_enabled', $freeTrialEnabled);

        setFlash('success', 'Service pricing and duration settings updated successfully!');
        redirect(BASE_URL . 'admin/promotion_payments.php');
    }

    if ($action === 'clear_donations') {
        try {
            $removed = clearDonationData($pdo);
            setFlash('success', "Cleared {$removed} donation record(s). Hall of Fame and payment history are reset for go-live.");
        } catch (Exception $e) {
            setFlash('error', 'Failed to clear donation data: ' . $e->getMessage());
        }
        redirect(BASE_URL . 'admin/promotion_payments.php');
    }

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $adminNote = sanitize($_POST['admin_note'] ?? '');

    if ($paymentId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('
                UPDATE promotion_payments
                SET status = :status,
                    admin_note = :note,
                    approved_at = CASE WHEN :status2 = \'approved\' THEN NOW() ELSE NULL END,
                    approved_by = :admin
                WHERE id = :id
                  AND status = \'pending\'
            ');
            $stmt->execute([
                ':status' => $newStatus,
                ':status2' => $newStatus,
                ':note' => $adminNote !== '' ? $adminNote : null,
                ':admin' => currentUserId(),
                ':id' => $paymentId,
            ]);

            if ($stmt->rowCount() > 0 && $action === 'approve') {
                // Fetch the product_id, type, and amount for this payment
                $pInfo = $pdo->prepare('SELECT product_id, payment_type, amount FROM promotion_payments WHERE id = ?');
                $pInfo->execute([$paymentId]);
                $paymentRow = $pInfo->fetch(PDO::FETCH_ASSOC);

                if ($paymentRow && $paymentRow['product_id']) {
                    $pid = (int)$paymentRow['product_id'];
                    $amount = (float)$paymentRow['amount'];
                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

                    if ($paymentRow['payment_type'] === 'promotion') {
                        $days = max(1, (int)floor($amount / 15));
                        $updSql = ($driver === 'pgsql')
                            ? "UPDATE products SET is_featured = TRUE, discount_set_at = NOW(), featured_until = NOW() + (CAST(? AS text) || ' days')::interval WHERE id = ?"
                            : "UPDATE products SET is_featured = 1, discount_set_at = NOW(), featured_until = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?";
                        $upd = $pdo->prepare($updSql);
                        $upd->execute([$days, $pid]);
                    } elseif ($paymentRow['payment_type'] === 'service_listing') {
                        $updSql = ($driver === 'pgsql')
                            ? "UPDATE products SET status = 'active', service_expires_at = NOW() + INTERVAL '30 days', updated_at = NOW() WHERE id = ?"
                            : "UPDATE products SET status = 'active', service_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY), updated_at = NOW() WHERE id = ?";
                        $upd = $pdo->prepare($updSql);
                        $upd->execute([$pid]);
                    }
                }
            }

            $pdo->commit();
            setFlash('success', "Payment #{$paymentId} marked as {$newStatus}.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    redirect(BASE_URL . 'admin/promotion_payments.php');
}

$rows = $pdo->query('
    SELECT pp.*, u.username, p.title AS product_title
    FROM promotion_payments pp
    JOIN users u ON u.id = pp.user_id
    LEFT JOIN products p ON p.id = pp.product_id
    ORDER BY
        CASE pp.status WHEN \'pending\' THEN 0 WHEN \'approved\' THEN 1 ELSE 2 END,
        pp.created_at DESC
')->fetchAll();

$donationCount = countDonationRecords($pdo);
$svcPricing = getServicePricingSettings($pdo);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-16 admin-payments-page">
    <div class="flex justify-between items-end mb-6 admin-page-toolbar" style="gap: 1rem; flex-wrap: wrap;">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Payment & Pricing Management</div>
            <h1 class="mb-0">Monetization & Payment Reviews</h1>
            <p class="text-muted mb-2">Configure platform service listing fees and review promotion / service transactions.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($rows); ?> Requests</div>
            <?php if ($donationCount > 0): ?>
            <form method="post" style="margin: 0;">
                <?php echo csrfTokenField(); ?>
                <button type="submit" name="action" value="clear_donations" class="btn btn-danger btn-sm" onclick="return confirm('This will permanently delete all <?php echo (int)$donationCount; ?> donation record(s) — including test checkout data and the Hall of Fame. Promotion payments will not be affected. Continue?');">Clear Donation Data (<?php echo (int)$donationCount; ?>)</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Service Pricing Settings Panel -->
    <div class="glass-panel p-6 mb-8" style="border-radius: var(--radius-xl); border: 1px solid var(--border-light); background: var(--bg-surface);">
        <div class="flex items-center gap-3 mb-4">
            <span style="font-size: 1.5rem;">⚙️</span>
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text-main);">Service Listing Pricing &amp; Duration Settings</h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Adjust listing fees, duration, and promotional boost add-on pricing across the marketplace.</p>
            </div>
        </div>

        <form method="POST" action="promotion_payments.php" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="update_service_pricing">

            <div class="form-group">
                <label class="form-label font-bold mb-1 block" style="font-size: 0.88rem; color: var(--text-main);">Standard Listing Fee (₺ TRY)</label>
                <input type="number" step="1" min="0" max="10000" name="service_listing_fee" value="<?= htmlspecialchars((string)$svcPricing['listing_fee']) ?>" class="premium-input w-full" style="padding: 0.65rem 0.85rem;" required>
                <small class="text-muted" style="font-size: 0.76rem;">Fee charged to list or renew a standard service.</small>
            </div>

            <div class="form-group">
                <label class="form-label font-bold mb-1 block" style="font-size: 0.88rem; color: var(--text-main);">Homepage Boost Add-On Fee (₺ TRY)</label>
                <input type="number" step="1" min="0" max="10000" name="service_boost_fee" value="<?= htmlspecialchars((string)$svcPricing['boost_fee']) ?>" class="premium-input w-full" style="padding: 0.65rem 0.85rem;" required>
                <small class="text-muted" style="font-size: 0.76rem;">Extra fee added for featured placement (Total: ₺<?= number_format($svcPricing['total_boosted_fee'], 0) ?>).</small>
            </div>

            <div class="form-group">
                <label class="form-label font-bold mb-1 block" style="font-size: 0.88rem; color: var(--text-main);">Listing Active Duration (Days)</label>
                <input type="number" step="1" min="1" max="365" name="service_listing_days" value="<?= (int)$svcPricing['listing_days'] ?>" class="premium-input w-full" style="padding: 0.65rem 0.85rem;" required>
                <small class="text-muted" style="font-size: 0.76rem;">Number of days before service expires.</small>
            </div>

            <div class="form-group">
                <label class="form-label font-bold mb-1 block" style="font-size: 0.88rem; color: var(--text-main);">Boost Duration (Days)</label>
                <input type="number" step="1" min="1" max="365" name="service_boost_days" value="<?= (int)$svcPricing['boost_days'] ?>" class="premium-input w-full" style="padding: 0.65rem 0.85rem;" required>
                <small class="text-muted" style="font-size: 0.76rem;">Days the service remains featured on the homepage.</small>
            </div>

            <div class="form-group flex items-center gap-3 pt-6">
                <label class="flex items-center gap-2 cursor-pointer" style="margin: 0;">
                    <input type="checkbox" name="service_free_trial_enabled" value="1" <?= $svcPricing['free_trial_enabled'] ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <span class="font-bold" style="font-size: 0.88rem; color: var(--text-main);">1st Service Listing Free Trial</span>
                </label>
            </div>

            <div class="form-group flex items-end">
                <button type="submit" class="btn btn-primary w-full" style="padding: 0.7rem 1.2rem; font-weight: 700;">
                    Save Pricing Settings
                </button>
            </div>
        </form>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left admin-payments-table" style="border-collapse: collapse; margin: 0; min-width: 980px;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Type</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">User</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Listing / Donation</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Amount</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Method/Ref</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Status</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="badge" style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-light);"><?php echo ucfirst(sanitize($row['payment_type'])); ?></span>
                        </td>
                        <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light); color: var(--primary);">@<?php echo sanitize($row['username']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if ($row['payment_type'] === 'promotion' && $row['product_title']): ?>
                                <div class="font-bold" style="line-height: 1.35;"><?php echo sanitize($row['product_title']); ?></div>
                                <?php if (!empty($row['product_id'])): ?>
                                    <a href="../pages/product.php?id=<?php echo (int)$row['product_id']; ?>" target="_blank" class="text-muted" style="font-size: 0.78rem;">View listing #<?php echo (int)$row['product_id']; ?></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">General donation</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);"><?php echo formatPrice((float)$row['amount']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light); font-size: 0.84rem;">
                            <?php echo strtoupper(sanitize($row['payment_method'])); ?>
                            <?php if (!empty($row['transaction_ref'])): ?><br><span class="text-muted"><?php echo sanitize($row['transaction_ref']); ?></span><?php endif; ?>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="badge badge-<?php echo sanitize($row['status']); ?>"><?php echo ucfirst(sanitize($row['status'])); ?></span>
                        </td>
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light); min-width: 260px;">
                            <?php if ($row['status'] === 'pending'): ?>
                                <form method="post" class="m-0 admin-payment-action-form">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="payment_id" value="<?php echo (int)$row['id']; ?>">
                                    <input type="text" name="admin_note" class="premium-input" placeholder="Admin note" style="max-width: 130px; padding: 0.35rem 0.5rem; font-size: 0.8rem;">
                                    <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:0.8rem;">Processed <?php echo date('M d, Y H:i', strtotime($row['approved_at'] ?? $row['created_at'])); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($rows)): ?>
            <div class="text-center p-8 text-muted">No payment requests yet.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-payments-table th,
.admin-payments-table td {
    vertical-align: middle;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
