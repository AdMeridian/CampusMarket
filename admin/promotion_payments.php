<?php
// admin/promotion_payments.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = 'Promotion & Donation Payments';
$promoPaymentsTableExists = false;
try {
    $promoPaymentsTableExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'promotion_payments' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    $promoPaymentsTableExists = false;
}

if (!$promoPaymentsTableExists) {
    setFlash('error', 'Promotion payment table is missing. Apply the schema update first.');
    redirect('listings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['action'])) {
    $paymentId = (int)$_POST['payment_id'];
    $action = sanitize($_POST['action']);
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
                $payData = $pInfo->fetch();

                if ($payData && $payData['payment_type'] === 'promotion' && !empty($payData['product_id'])) {
                    // Business Logic (Option B - Linear + Special Tiers)
                    $amount = (float)$payData['amount'];
                    
                    if ($amount == 50) $days = 3;
                    elseif ($amount == 100) $days = 7;
                    elseif ($amount >= 200) $days = 30; // 200+ is always 1 month bulk deal
                    else $days = max(1, floor($amount / 15)); // Linear ₺15 per day

                    // Automatically feature the product with expiration
                    $updProd = $pdo->prepare("UPDATE products SET is_featured = 1, discount_set_at = NOW(), featured_until = NOW() + INTERVAL '1 day' * ? WHERE id = ?");
                    $updProd->execute([$days, $payData['product_id']]);
                }
            }

            $pdo->commit();
            setFlash('success', 'Payment request ' . $newStatus . '.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error processing request: ' . $e->getMessage());
        }
    }

    redirect('promotion_payments.php');
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
?>

<div class="container mt-8 mb-16">
    <div class="flex justify-between items-end mb-6" style="gap: 1rem; flex-wrap: wrap;">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Payment Reviews</div>
            <h1 class="gradient-text mb-0">Promotion & Donation Payments</h1>
            <p class="text-muted mb-2">Donations support CampusMarket generally and do not become promotion credits. Promotion requests can later be consumed to feature an approved listing.</p>
        </div>
        <div class="badge" style="background: var(--primary-light); color: var(--primary-hover); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($rows); ?> Requests</div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
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
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);"><?php echo ucfirst(sanitize($row['payment_type'])); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">@<?php echo sanitize($row['username']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if ($row['payment_type'] === 'promotion' && $row['product_title']): ?>
                                <?php echo sanitize($row['product_title']); ?>
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
                                <form method="post" class="m-0" style="display:flex; gap:0.4rem; justify-content:flex-end; align-items:center;">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

