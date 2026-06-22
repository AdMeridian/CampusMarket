<?php
// pages/wishlist.php
require_once '../includes/bootstrap.php';
requireLogin();

if (isAdmin()) {
    setFlash('error', __('wishlist.admin_redirect'));
    redirect(BASE_URL . 'admin/index.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
    FROM wishlists w
    JOIN products p ON w.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([currentUserId()]);
$products = $stmt->fetchAll();

$pageTitle = __('wishlist.page_title');
include '../includes/header.php';
?>

<div class="container min-h-screen pt-24 pb-20 relative">
    <div class="mb-10 text-center lg:text-left flex flex-col md:flex-row justify-between items-center gap-6">
        <div>
            <h1 class="font-bold text-4xl mb-2"><?= __('wishlist.title') ?></h1>
            <p class="text-muted text-lg font-medium"><?= __('wishlist.subtitle') ?></p>
        </div>
    </div>

    <div id="wishlist-container">
        <?php if (empty($products)): ?>
            <div class="glass-panel p-20 text-center shadow-sm relative overflow-hidden" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
                <div class="mb-6 opacity-20" style="display: flex; justify-content: center; align-items: center; transform: rotate(10deg);"><svg style="width: 80px; height: 80px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                <h3 class="font-bold text-main text-3xl mb-3"><?= __('wishlist.empty_title') ?></h3>
                <p class="text-muted text-lg max-w-lg mx-auto mb-8"><?= __('wishlist.empty_desc') ?></p>
                <a href="browse.php" class="btn btn-primary shadow-lg hover-scale" style="border-radius: var(--radius-lg); padding: 0.8rem 2.5rem; font-weight: bold; font-size: 1.1rem;"><?= __('wishlist.discover') ?></a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($products as $prod): ?>
                    <?php include '../includes/product_card_template.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
