<?php
// admin/orders.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

// Auth Check (Admin Only)
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = "Order Oversight";

// Fetch All Orders
$stmt = $pdo->query("
    SELECT o.*,
           b.username as buyer_name,
           p.title as product_title,
           s.username as seller_name,
           p.id as product_id
    FROM orders o
    JOIN users b ON o.buyer_id = b.id
    JOIN products p ON o.product_id = p.id
    JOIN users s ON p.user_id = s.id
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll();
?>

<div class="container mt-8 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Orders</div>
            <h1 class="mb-0 gradient-text">Marketplace Transactions</h1>
        </div>
        <div class="badge" style="background: rgba(245,158,11,0.1); color: #d97706; font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($orders); ?> Total Orders</div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Order ID</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Item Details</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Buyer</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Seller</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Transaction Value</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Status</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                     <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(245,158,11,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4 font-bold" style="border-bottom: 1px solid var(--border-light); color: var(--text-muted);">#<?php echo $order['id']; ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="font-bold text-main"><?php echo sanitize($order['product_title']); ?></div>
                            <a href="../pages/product.php?id=<?php echo $order['product_id']; ?>" class="small text-primary hover-scale inline-block mt-1" target="_blank" style="text-decoration: none; font-weight: 600;">View Listing →</a>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.85rem;">@<?php echo sanitize($order['buyer_name']); ?></span>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.85rem;">@<?php echo sanitize($order['seller_name']); ?></span>
                        </td>
                        <td class="p-4 font-bold" style="border-bottom: 1px solid var(--border-light); font-size: 1.1rem; color: #d97706;"><?php echo formatPrice($order['amount']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="badge badge-<?php echo $order['status']; ?> shadow-sm"><?php echo ucfirst($order['status']); ?></span>
                        </td>
                        <td class="p-4 text-right text-muted small" style="border-bottom: 1px solid var(--border-light); font-family: monospace;">
                            <?php echo date('M d, Y • H:i', strtotime($order['created_at'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($orders)): ?>
            <div class="text-center p-8 text-muted">
                <span class="text-4xl mb-4 block">🧾</span>
                No transactions have occurred yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
