<?php
// pages/recycle_bin.php
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please log in to access your Recycle Bin.');
    redirect(BASE_URL . 'pages/login.php');
}

$userId = (int)currentUserId();
$errors = [];
$success = '';

// Auto-cleanup: Delete items older than 30 days (PostgreSQL syntax)
$cleanup = $pdo->prepare("DELETE FROM products WHERE status = 'deleted' AND deleted_at < NOW() - INTERVAL '30 days'");
$cleanup->execute();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $productId = (int)($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND user_id = ? AND status = 'deleted'");
    $stmt->execute([$productId, $userId]);
    $product = $stmt->fetch();

    if ($product) {
        if ($action === 'restore') {
            $upd = $pdo->prepare("UPDATE products SET status = 'active', deleted_at = NULL, updated_at = NOW() WHERE id = ?");
            if ($upd->execute([$productId])) {
                setFlash('success', 'Listing restored successfully!');
                redirect(BASE_URL . 'pages/recycle_bin.php');
            }
        } elseif ($action === 'delete_permanent') {
            $del = $pdo->prepare("DELETE FROM products WHERE id = ?");
            if ($del->execute([$productId])) {
                setFlash('success', 'Listing deleted permanently.');
                redirect(BASE_URL . 'pages/recycle_bin.php');
            }
        }
    }
}

// Fetch deleted products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.user_id = ? AND p.status = 'deleted'
    ORDER BY p.deleted_at DESC
");
$stmt->execute([$userId]);
$deletedProducts = $stmt->fetchAll();

$pageTitle = 'Recycle Bin';
require_once '../includes/header.php';
?>

<div class="page-content-offset">
    <div class="container py-12">
        <div class="flex flex-col md-flex-row justify-between items-start md-items-center mb-10 gap-4">
            <div>
                <h1 class="mb-2" style="font-weight: 800;">Recycle Bin</h1>
                <p class="text-muted" style="font-weight: 500; font-size: 1.1rem;">Items here will be permanently deleted after 30 days.</p>
            </div>
            <a href="profile.php" class="btn btn-outline flex items-center gap-2" style="border-radius: 12px; font-weight: 700;">
                <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                Back to Profile
            </a>
        </div>

        <?php if (empty($deletedProducts)): ?>
            <div class="glass-panel p-16 text-center" style="border-radius: 24px; border: 2px dashed var(--border-light); background: transparent;">
                <h2 class="mb-4" style="font-weight: 800;">Your bin is empty</h2>
                <p class="text-muted max-w-md mx-auto mb-8" style="font-weight: 500;">When you delete a listing, it will stay here for 30 days before being permanently removed.</p>
                <a href="browse.php" class="btn btn-primary" style="padding: 0.75rem 2rem; border-radius: 12px; font-weight: 700;">Browse Marketplace</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md-grid-cols-2 lg-grid-cols-3 gap-8">
                <?php foreach ($deletedProducts as $prod): 
                    $deletedAt = $prod['deleted_at'] ? new DateTime($prod['deleted_at']) : new DateTime();
                    $expiryDate = (clone $deletedAt)->modify('+30 days');
                    $now = new DateTime();
                    $diff = $now->diff($expiryDate);
                    $daysLeft = $diff->invert ? 0 : $diff->days;
                ?>
                    <div class="card overflow-hidden flex flex-col" style="border-radius: 20px; border: 1px solid var(--border-light); transition: all 0.3s ease;">
                        <div class="relative h-48 bg-slate-100" style="background-color: #f1f5f9;">
                            <?php 
                            $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = TRUE LIMIT 1");
                            $imgStmt->execute([$prod['id']]);
                            $mainImg = $imgStmt->fetchColumn();
                            $imageSrc = getProductImage($mainImg);
                            ?>
                            <img src="<?php echo $imageSrc; ?>" alt="<?php echo sanitize($prod['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <div class="absolute top-4 right-4 bg-white/95 backdrop-blur px-3 py-1.5 rounded-full text-[0.65rem] font-black shadow-lg <?php echo $daysLeft < 5 ? 'text-error' : 'text-slate-600'; ?>" style="letter-spacing: 0.08em; border: 1px solid rgba(0,0,0,0.05); color: <?php echo $daysLeft < 5 ? 'var(--error)' : '#475569'; ?>; background: white;">
                                <?php echo $daysLeft; ?> DAYS REMAINING
                            </div>
                        </div>
                        
                        <div class="p-6 flex-grow">
                            <span class="text-[0.65rem] font-black uppercase text-indigo-500 mb-2 block" style="letter-spacing: 0.15em; opacity: 0.8; color: var(--primary);"><?php echo sanitize($prod['category_name'] ?? 'Uncategorized'); ?></span>
                            <h3 class="mb-2" style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);"><?php echo sanitize($prod['title']); ?></h3>
                            <p class="text-muted text-sm mb-6 line-clamp-2" style="font-weight: 500; line-height: 1.6;"><?php echo sanitize($prod['description']); ?></p>
                            
                            <div class="flex items-center gap-3">
                                <form method="post" style="flex: 1;">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button type="submit" class="btn btn-outline w-full py-3 hover-scale" style="border-radius: 14px; font-weight: 800; border-color: var(--primary); color: var(--primary); font-size: 0.9rem; background: transparent;">
                                        Restore Item
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this listing forever? This action cannot be undone.')">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                    <input type="hidden" name="action" value="delete_permanent">
                                    <button type="submit" class="btn btn-danger py-3 px-5 hover-scale shadow-sm" style="border-radius: 14px; background: var(--error); color: #fff; border: none; display: flex; align-items: center; justify-content: center;">
                                        <svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
