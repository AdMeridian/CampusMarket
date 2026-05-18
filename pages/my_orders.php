<?php
// pages/my_orders.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

// Admins are moderators only — use admin/orders.php to manage all orders
if (isAdmin()) {
    setFlash('error', 'Administrators do not have personal orders. Use the Admin Panel to view all orders.');
    redirect(BASE_URL . 'admin/orders.php');
}

$currentUserId = currentUserId();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $action = sanitize($_POST['action']);
    $orderId = (int) $_POST['order_id'];
    
    $stmt = $pdo->prepare("SELECT o.*, p.user_id as seller_id, p.title as product_title FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = :id");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    
    if ($order) {
        $isSeller = ($order['seller_id'] == $currentUserId);
        $isBuyer = ($order['buyer_id'] == $currentUserId);
        
        try {
            $pdo->beginTransaction();
            if ($action === 'confirm' && $isSeller && $order['status'] === 'pending') {
                $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);
                $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$order['product_id']]);
                $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE product_id = ? AND id != ? AND status = 'pending'")->execute([$order['product_id'], $orderId]);
                createNotification($pdo, $order['buyer_id'], 'order', "Order Confirmed!", "Your order for '{$order['product_title']}' was confirmed.", $orderId);
                setFlash('success', 'Order confirmed and product marked as sold.');
            } elseif ($action === 'cancel' && ($isSeller || $isBuyer) && $order['status'] === 'pending') {
                $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
                $notifyId = $isSeller ? $order['buyer_id'] : $order['seller_id'];
                $cancelerRole = $isSeller ? "Seller" : "Buyer";
                createNotification($pdo, $notifyId, 'system', "Order Cancelled", "The $cancelerRole cancelled the order for '{$order['product_title']}'.", $orderId);
                setFlash('info', 'Order cancelled.');
            }
            $pdo->commit();
        } catch (PDOException $e) { $pdo->rollBack(); setFlash('error', 'Update failed.'); }
    }
    redirect(BASE_URL . '/pages/my_orders.php');
}

// Fetch buying orders
$stmtBuying = $pdo->prepare("SELECT o.*, p.title as product_title, p.price, u.username as seller_name, i.image_path FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON p.user_id = u.id LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE WHERE o.buyer_id = :uid ORDER BY o.created_at DESC");
$stmtBuying->execute([':uid' => $currentUserId]);
$buyingOrders = $stmtBuying->fetchAll();

// Fetch selling orders
$stmtSelling = $pdo->prepare("SELECT o.*, p.title as product_title, p.price, u.username as buyer_name, i.image_path FROM orders o JOIN products p ON o.product_id = p.id JOIN users u ON o.buyer_id = u.id LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE WHERE p.user_id = :uid ORDER BY o.created_at DESC");
$stmtSelling->execute([':uid' => $currentUserId]);
$sellingOrders = $stmtSelling->fetchAll();

$pageTitle = "My Transactions";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-20 relative">
    <div class="text-center mb-12">
        <h1 class="gradient-text mb-2" style="font-size: 2.75rem;">Transaction Hub</h1>
        <p class="text-muted text-lg">Track your purchases and manage your sales</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 relative z-10">
        
        <!-- My Purchases -->
        <div>
            <div class="flex items-center gap-4 mb-6 pb-2 border-b">
                <div class="p-3 shadow-md" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 12px; color: white;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h2 class="mb-0" style="font-size: 1.5rem;">My Purchases</h2>
                <div class="ml-auto badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);"><?php echo count($buyingOrders); ?> Orders</div>
            </div>
            
            <div class="flex flex-col gap-5">
                <?php if (empty($buyingOrders)): ?>
                    <div class="glass-panel p-12 text-center" style="border: 2px dashed rgba(0,0,0,0.05); border-radius: var(--radius-lg);">
                        <div class="text-4xl mb-4 opacity-50">🛍️</div>
                        <p class="text-muted font-medium mb-4">You haven't bought anything yet.</p>
                        <a href="browse.php" class="btn btn-secondary shadow-sm hover-scale" style="border-radius: var(--radius-lg);">Browse Market</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($buyingOrders as $order): ?>
                        <div class="glass-panel p-5 flex gap-5 items-center hover-scale" style="border-radius: var(--radius-lg); border-left: 4px solid var(--primary); transition: all 0.3s;">
                            <div style="width: 80px; height: 80px; background: var(--bg-main); border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                <img src="<?php echo getProductImage($order['image_path'] ?? null); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="mb-0 text-main font-bold" style="line-height: 1.2;"><?php echo sanitize($order['product_title']); ?></h4>
                                    <span class="badge badge-<?php echo $order['status']; ?> shadow-sm" style="font-size: 0.70rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($order['status']); ?></span>
                                </div>
                                <p class="text-primary font-bold mb-2" style="font-size: 1.1rem;"><?php echo formatPrice($order['price']); ?></p>
                                <p class="text-muted small mb-0 flex items-center gap-2">
                                    <span style="background: rgba(0,0,0,0.03); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid rgba(0,0,0,0.05);">@<?php echo sanitize($order['seller_name']); ?></span>
                                    <span>•</span>
                                    <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </p>
                            </div>
                            <?php if ($order['status'] === 'pending'): ?>
                                <form method="post" class="ml-2 m-0">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm shadow-sm hover-scale" style="border-radius: var(--radius-lg); width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Cancel Order" onclick="return confirm('Cancel this purchase request?')">✕</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Sales -->
        <div>
            <div class="flex items-center gap-4 mb-6 pb-2 border-b">
                <div class="p-3 shadow-md" style="background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 12px; color: white;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h2 class="mb-0" style="font-size: 1.5rem;">My Sales</h2>
                <div class="ml-auto badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);"><?php echo count($sellingOrders); ?> Requests</div>
            </div>

            <div class="flex flex-col gap-5">
                <?php if (empty($sellingOrders)): ?>
                    <div class="glass-panel p-12 text-center" style="border: 2px dashed rgba(0,0,0,0.05); border-radius: var(--radius-lg);">
                        <div class="text-4xl mb-4 opacity-50">💵</div>
                        <p class="text-muted font-medium mb-4">No incoming sales orders yet.</p>
                        <a href="create_listing.php" class="btn btn-primary shadow-sm hover-scale" style="border-radius: var(--radius-lg);">Create Listing</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($sellingOrders as $order): ?>
                        <div class="glass-panel p-5 hover-scale" style="border-radius: var(--radius-lg); border-left: 4px solid #f59e0b; transition: all 0.3s; <?php echo $order['status'] === 'pending' ? 'background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(252,211,77,0.05));' : ''; ?>">
                            <div class="flex gap-5 items-start mb-4">
                                <div style="width: 80px; height: 80px; background: var(--bg-main); border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                    <img src="<?php echo getProductImage($order['image_path'] ?? null); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div class="flex-grow">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="mb-0 text-main font-bold" style="line-height: 1.2;"><?php echo sanitize($order['product_title']); ?></h4>
                                        <span class="badge badge-<?php echo $order['status']; ?> shadow-sm" style="font-size: 0.70rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($order['status']); ?></span>
                                    </div>
                                    <p class="text-primary font-bold mb-2" style="font-size: 1.1rem;"><?php echo formatPrice($order['price']); ?> <span class="text-muted font-normal" style="font-size: 0.85rem;">payment</span></p>
                                    
                                    <div style="background: rgba(255,255,255,0.6); padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); font-size: 0.85rem;">
                                        <div class="mb-1 text-muted">Buyer: <span class="font-medium text-main">@<?php echo sanitize($order['buyer_name']); ?></span></div>
                                        <div class="text-muted">Meet at: <strong class="text-main"><?php echo sanitize($order['meeting_point']); ?></strong></div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($order['status'] === 'pending'): ?>
                                <hr style="border: none; border-top: 1px solid rgba(0,0,0,0.05); margin: 1rem 0;">
                                <div class="flex gap-3 mt-4">
                                    <form method="post" class="flex-grow m-0">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="action" value="confirm" class="btn btn-primary w-full py-2 hover-scale shadow-sm font-bold" style="border-radius: var(--radius-md);">Accept & Confirm Sold</button>
                                    </form>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="action" value="cancel" class="btn btn-secondary py-2 hover-scale shadow-sm" style="border-radius: var(--radius-md);">Reject</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
