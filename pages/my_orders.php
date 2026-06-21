<?php
// pages/my_orders.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if (isAdmin()) {
    setFlash('error', __('orders.admin_redirect'));
    redirect(BASE_URL . 'admin/orders.php');
}

$currentUserId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $action = sanitize($_POST['action']);
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($orderId > 0) {
        $stmt = $pdo->prepare("
            SELECT o.*, p.user_id AS seller_id, p.title AS product_title
            FROM orders o
            JOIN products p ON o.product_id = p.id
            WHERE o.id = :id
        ");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $isSeller = ((int)$order['seller_id'] === (int)$currentUserId);
            $isBuyer = ((int)$order['buyer_id'] === (int)$currentUserId);

            try {
                $pdo->beginTransaction();

                if ($action === 'submit_review' && $isBuyer && $order['status'] === 'completed') {
                    $ratingValue = (int)($_POST['rating'] ?? 0);
                    $comment = trim((string)($_POST['comment'] ?? ''));

                    if ($ratingValue < 1 || $ratingValue > 5) {
                        throw new RuntimeException('Rating must be between 1 and 5.');
                    }
                    if (mb_strlen($comment) > 1000) {
                        $comment = mb_substr($comment, 0, 1000);
                    }

                    $checkRating = $pdo->prepare("SELECT id FROM ratings WHERE reviewer_id = :rid AND product_id = :pid LIMIT 1");
                    $checkRating->execute([
                        ':rid' => $currentUserId,
                        ':pid' => $order['product_id'],
                    ]);
                    if ($checkRating->fetchColumn()) {
                        throw new RuntimeException('You already reviewed this transaction.');
                    }

                    $insertRating = $pdo->prepare("
                        INSERT INTO ratings (reviewer_id, seller_id, product_id, rating, comment)
                        VALUES (:rid, :sid, :pid, :rating, :comment)
                    ");
                    $insertRating->execute([
                        ':rid' => $currentUserId,
                        ':sid' => $order['seller_id'],
                        ':pid' => $order['product_id'],
                        ':rating' => $ratingValue,
                        ':comment' => $comment !== '' ? $comment : null,
                    ]);

                    createNotification(
                        $pdo,
                        (int)$order['seller_id'],
                        'system',
                        'New Seller Review',
                        "You received a new review for '{$order['product_title']}'.",
                        (int)$order['product_id']
                    );

                    setFlash('success', __('orders.flash_review_thanks'));
                } elseif ($action === 'confirm' && $isSeller && $order['status'] === 'pending') {
                    $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);
                    $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$order['product_id']]);
                    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE product_id = ? AND id != ? AND status = 'pending'")->execute([$order['product_id'], $orderId]);
                    createNotification($pdo, $order['buyer_id'], 'order', 'Order Confirmed!', "Your order for '{$order['product_title']}' was confirmed.", $orderId);
                    setFlash('success', __('orders.flash_confirmed'));
                } elseif ($action === 'cancel' && ($isSeller || $isBuyer) && $order['status'] === 'pending') {
                    $newStatus = $isBuyer ? 'not taken' : 'cancelled';
                    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);

                    if ($isBuyer) {
                        $pdo->prepare("DELETE FROM deal_confirmations WHERE product_id = ? AND buyer_id = ?")->execute([$order['product_id'], $order['buyer_id']]);
                    }

                    $notifyId = $isSeller ? $order['buyer_id'] : $order['seller_id'];
                    $cancelerRole = $isSeller ? 'Seller' : 'Buyer';
                    createNotification($pdo, $notifyId, 'system', 'Order Cancelled', "The $cancelerRole cancelled the order for '{$order['product_title']}'.", $orderId);
                    setFlash('info', __('orders.flash_cancelled'));
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('error', $e instanceof RuntimeException ? $e->getMessage() : __('orders.flash_update_failed'));
            }
        }
    }

    redirect(BASE_URL . '/pages/my_orders.php');
}

$stmtBuying = $pdo->prepare("
    SELECT
        o.*,
        p.title AS product_title,
        p.price,
        p.user_id AS seller_id,
        u.username AS seller_name,
        i.image_path
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
    WHERE o.buyer_id = :uid
    ORDER BY o.created_at DESC
");
$stmtBuying->execute([':uid' => $currentUserId]);
$buyingOrders = $stmtBuying->fetchAll();

$stmtSelling = $pdo->prepare("
    SELECT
        o.*,
        p.title AS product_title,
        p.price,
        u.username AS buyer_name,
        i.image_path
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
    WHERE p.user_id = :uid
    ORDER BY o.created_at DESC
");
$stmtSelling->execute([':uid' => $currentUserId]);
$sellingOrders = $stmtSelling->fetchAll();

$pendingReviewStmt = $pdo->prepare("
    SELECT
        o.id AS order_id,
        o.product_id,
        p.title AS product_title,
        u.username AS seller_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = p.user_id
    LEFT JOIN ratings r ON r.product_id = o.product_id AND r.reviewer_id = o.buyer_id
    WHERE o.buyer_id = :uid
      AND o.status = 'completed'
      AND r.id IS NULL
    ORDER BY o.updated_at DESC, o.created_at DESC
");
$pendingReviewStmt->execute([':uid' => $currentUserId]);
$pendingReviews = $pendingReviewStmt->fetchAll();
$pendingReviewByOrder = [];
foreach ($pendingReviews as $row) {
    $pendingReviewByOrder[(int)$row['order_id']] = $row;
}
$promptReview = $pendingReviews[0] ?? null;

$pageTitle = __('orders.hub_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-20 relative">
    <?php if ($promptReview): ?>
    <div id="review-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 1200;">
        <div style="max-width: 520px; margin: 8vh auto 0; background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); padding: 1.2rem;">
            <h3 style="margin: 0 0 0.45rem 0; font-size: 1.2rem; color: var(--text-main);">How was your seller?</h3>
            <p id="review-modal-message" style="margin: 0 0 1rem 0; color: var(--text-muted); font-size: 0.92rem;">
                You completed <strong><?php echo sanitize($promptReview['product_title']); ?></strong> with
                <strong>@<?php echo sanitize($promptReview['seller_name']); ?></strong>. Leave a quick review.
            </p>
            <form method="post" id="review-form">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="order_id" value="<?php echo (int)$promptReview['order_id']; ?>">
                <div style="margin-bottom: 0.8rem;">
                    <label for="rating" style="display: block; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">Rating</label>
                    <select name="rating" id="rating" required class="premium-input" style="width: 100%; padding: 0.65rem;">
                        <option value="">Select rating</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - Okay</option>
                        <option value="2">2 - Poor</option>
                        <option value="1">1 - Very Poor</option>
                    </select>
                </div>
                <div style="margin-bottom: 0.9rem;">
                    <label for="review_comment" style="display: block; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">Comment (optional)</label>
                    <textarea name="comment" id="review_comment" rows="4" class="premium-input" maxlength="1000" style="width: 100%; padding: 0.65rem;" placeholder="Share your experience with this seller..."></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.55rem;">
                    <button type="button" id="review-not-now" class="btn btn-secondary">Not now</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="glass-panel p-5 mb-8" style="border-radius: var(--radius-lg); border-left: 4px solid var(--primary); background: var(--bg-surface);">
        <h2 class="mb-2" style="font-size: 1.05rem; color: var(--text-main);"><?= __('orders.how_it_works_title') ?></h2>
        <p class="text-muted mb-0" style="line-height: 1.6;"><?= __('orders.how_it_works_body') ?></p>
    </div>

    <div class="text-center mb-12">
        <h1 class="mb-2 page-hero-title"><?= __('orders.hub_title') ?></h1>
        <p class="text-muted text-lg"><?= __('orders.hub_subtitle') ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 relative z-10">
        <div>
            <div class="flex items-center gap-4 mb-6 pb-2 border-b">
                <div class="p-3 shadow-md" style="background: var(--primary); border-radius: 12px; color: white;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h2 class="mb-0" style="font-size: 1.5rem;"><?= __('orders.my_purchases') ?></h2>
                <div class="ml-auto badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);"><?php echo count($buyingOrders); ?> Orders</div>
            </div>

            <div class="flex flex-col gap-5">
                <?php if (empty($buyingOrders)): ?>
                    <div class="glass-panel p-12 text-center" style="border: 2px dashed rgba(0,0,0,0.05); border-radius: var(--radius-lg);">
                        <div class="mb-4 opacity-50" style="display: flex; justify-content: center; align-items: center;"><svg style="width: 48px; height: 48px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
                        <p class="text-muted font-medium mb-4">You haven't bought anything yet.</p>
                        <a href="browse.php" class="btn btn-secondary shadow-sm hover-scale" style="border-radius: var(--radius-lg);">Browse Market</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($buyingOrders as $order): ?>
                        <div class="glass-panel p-5 order-hub-card hover-scale" style="border-radius: var(--radius-lg); border-left: 4px solid var(--primary); transition: all 0.3s;">
                            <div style="width: 80px; height: 80px; background: var(--bg-main); border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                <img src="<?php echo getProductImage($order['image_path'] ?? null); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo sanitize($order['product_title']); ?>">
                            </div>
                            <div class="flex-grow order-hub-main">
                                <div class="order-hub-title-row mb-1">
                                    <h4 class="mb-0 text-main font-bold" style="line-height: 1.2;"><?php echo sanitize($order['product_title']); ?></h4>
                                    <span class="badge badge-<?php echo str_replace(' ', '-', $order['status']); ?> shadow-sm" style="font-size: 0.70rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($order['status']); ?></span>
                                </div>
                                <p class="text-primary font-bold mb-2" style="font-size: 1.1rem;"><?php echo formatPrice($order['price']); ?></p>
                                <p class="text-muted small mb-0 flex items-center gap-2">
                                    <span style="background: rgba(0,0,0,0.03); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid rgba(0,0,0,0.05);">@<?php echo sanitize($order['seller_name']); ?></span>
                                    <span>&bull;</span>
                                    <span><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </p>
                            </div>
                            <?php if ($order['status'] === 'pending'): ?>
                                <form method="post" class="ml-2 m-0 order-hub-actions">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm shadow-sm hover-scale order-hub-cancel-btn" style="border-radius: var(--radius-lg); padding: 0; display: flex; align-items: center; justify-content: center;" title="Cancel Order" onclick="return confirm('Cancel this purchase request?')">X</button>
                                </form>
                            <?php elseif ($order['status'] === 'completed' && isset($pendingReviewByOrder[(int)$order['id']])): ?>
                                <div class="order-hub-actions">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm shadow-sm hover-scale open-review-btn"
                                    data-order-id="<?php echo (int)$order['id']; ?>"
                                    data-product-title="<?php echo htmlspecialchars($order['product_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-seller-name="<?php echo htmlspecialchars($order['seller_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    style="border-radius: var(--radius-lg);">
                                    Review Seller
                                </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-4 mb-6 pb-2 border-b">
                <div class="p-3 shadow-md" style="background: #f59e0b; border-radius: 12px; color: white;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h2 class="mb-0" style="font-size: 1.5rem;">My Sales</h2>
                <div class="ml-auto badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);"><?php echo count($sellingOrders); ?> Requests</div>
            </div>

            <div class="flex flex-col gap-5">
                <?php if (empty($sellingOrders)): ?>
                    <div class="glass-panel p-12 text-center" style="border: 2px dashed rgba(0,0,0,0.05); border-radius: var(--radius-lg);">
                        <div class="mb-4 opacity-50" style="display: flex; justify-content: center; align-items: center;"><svg style="width: 48px; height: 48px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                        <p class="text-muted font-medium mb-4">No incoming sales orders yet.</p>
                        <a href="create_listing.php" class="btn btn-primary shadow-sm hover-scale" style="border-radius: var(--radius-lg);">Create Listing</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($sellingOrders as $order): ?>
                        <div class="glass-panel p-5 hover-scale" style="border-radius: var(--radius-lg); border-left: 4px solid #f59e0b; transition: all 0.3s; <?php echo $order['status'] === 'pending' ? 'background: rgba(245, 158, 11, 0.05);' : ''; ?>">
                            <div class="flex gap-5 items-start mb-4">
                                <div style="width: 80px; height: 80px; background: var(--bg-main); border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                    <img src="<?php echo getProductImage($order['image_path'] ?? null); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo sanitize($order['product_title']); ?>">
                                </div>
                                <div class="flex-grow">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="mb-0 text-main font-bold" style="line-height: 1.2;"><?php echo sanitize($order['product_title']); ?></h4>
                                        <span class="badge badge-<?php echo str_replace(' ', '-', $order['status']); ?> shadow-sm" style="font-size: 0.70rem; padding: 0.2rem 0.5rem;"><?php echo ucfirst($order['status']); ?></span>
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
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <button type="submit" name="action" value="confirm" class="btn btn-primary w-full py-2 hover-scale shadow-sm font-bold" style="border-radius: var(--radius-md);">Accept & Confirm Sold</button>
                                    </form>
                                    <form method="post" class="m-0">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
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

<?php if ($promptReview): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('review-modal-overlay');
    const form = document.getElementById('review-form');
    const notNow = document.getElementById('review-not-now');
    const openButtons = document.querySelectorAll('.open-review-btn');
    const snoozePrefix = 'cm_review_snooze_';

    function setModalContent(orderId, productTitle, sellerName) {
        if (!overlay || !form) return;
        const orderInput = form.querySelector('input[name="order_id"]');
        if (orderInput) orderInput.value = String(orderId);
        const msg = document.getElementById('review-modal-message');
        if (msg) {
            msg.innerHTML = 'You completed <strong>' + productTitle + '</strong> with <strong>@' + sellerName + '</strong>. Leave a quick review.';
        }
    }

    function openModal(orderId, productTitle, sellerName) {
        setModalContent(orderId, productTitle, sellerName);
        overlay.style.display = 'block';
    }

    function closeModal() {
        overlay.style.display = 'none';
    }

    const firstOrderId = <?php echo (int)$promptReview['order_id']; ?>;
    const snoozeUntil = Number(localStorage.getItem(snoozePrefix + firstOrderId) || 0);
    if (Date.now() > snoozeUntil) {
        openModal(
            firstOrderId,
            <?php echo json_encode($promptReview['product_title']); ?>,
            <?php echo json_encode($promptReview['seller_name']); ?>
        );
    }

    if (notNow) {
        notNow.addEventListener('click', function() {
            const orderInput = form ? form.querySelector('input[name="order_id"]') : null;
            if (orderInput && orderInput.value) {
                localStorage.setItem(snoozePrefix + orderInput.value, String(Date.now() + (24 * 60 * 60 * 1000)));
            }
            closeModal();
        });
    }

    openButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            openModal(
                btn.getAttribute('data-order-id') || '',
                btn.getAttribute('data-product-title') || '',
                btn.getAttribute('data-seller-name') || ''
            );
        });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
