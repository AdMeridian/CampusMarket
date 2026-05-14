<?php
// pages/wishlist.php
require_once '../includes/bootstrap.php';
requireLogin();

// Admins are moderators only — no personal wishlist
if (isAdmin()) {
    setFlash('error', 'Administrators do not have a wishlist. Use the Admin Panel to manage the marketplace.');
    redirect(BASE_URL . 'admin/index.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
    FROM wishlists w
    JOIN products p ON w.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = 1
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([currentUserId()]);
$products = $stmt->fetchAll();

$pageTitle = "My Wishlist";
include '../includes/header.php';
?>

<div class="container min-h-screen pt-12 pb-20 relative">
    <!-- Background Accents -->

    <div class="mb-10 text-center lg:text-left flex flex-col md:flex-row justify-between items-center gap-6">
        <div>
            <h1 class="font-bold text-4xl mb-2 gradient-text" style="background: linear-gradient(135deg, var(--text-main), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">My Wishlist</h1>
            <p class="text-muted text-lg font-medium">Items you've saved to look at later.</p>
        </div>
    </div>

    <div id="wishlist-container">
        <?php if (empty($products)): ?>
            <div class="glass-panel p-20 text-center shadow-sm relative overflow-hidden" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
                <div class="text-8xl mb-6 opacity-20" style="transform: rotate(10deg);">💖</div>
                <h3 class="font-bold text-main text-3xl mb-3">Your wishlist is empty</h3>
                <p class="text-muted text-lg max-w-lg mx-auto mb-8">Start browsing and click the heart icon to save items you love.</p>
                <a href="browse.php" class="btn btn-primary shadow-lg hover-scale" style="border-radius: var(--radius-lg); padding: 0.8rem 2.5rem; font-weight: bold; font-size: 1.1rem;">Discover Items</a>
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
