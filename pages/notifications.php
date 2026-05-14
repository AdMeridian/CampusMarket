<?php
$pageTitle = "Notifications";
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$currentUserId = currentUserId();

// Handle specific actions like marking single/all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :uid");
        $stmt->execute([':uid' => $currentUserId]);
        setFlash('success', 'All notifications marked as read.');
    }
    // Additional logic to handle single read could go here
    redirect(BASE_URL . '/pages/notifications.php');
}

// Automatically mark viewed notifications as read after fetching them
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
$stmt->execute([':uid' => $currentUserId]);
$notifications = $stmt->fetchAll();

// Now implicitly mark them as read in DB if they aren't
$unreadIds = array_column(array_filter($notifications, fn($n) => $n['is_read'] == 0), 'id');
if (!empty($unreadIds)) {
    $placeholders = str_repeat('?,', count($unreadIds) - 1) . '?';
    $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)");
    $updateStmt->execute($unreadIds);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container main-content mb-20" style="max-width: 800px; margin-top: 3rem;">
    <div class="glass-panel" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-lg);">
        <div style="background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(244,63,94,0.05)); padding: 2rem; border-bottom: 1px solid var(--border-light);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 class="mb-0 text-main font-bold" style="letter-spacing: -0.5px; font-size: 2rem;">Activity <span class="gradient-text" style="background: linear-gradient(135deg, #ef4444, #f43f5e); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Updates</span></h1>
                <?php if (!empty($notifications)): ?>
                    <form method="post" class="m-0">
                        <button type="submit" name="action" value="mark_all" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 1rem; border: 1px solid var(--border-focus);"><svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mark All Read</button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="text-muted mt-2 mb-0">Stay up to date with your orders, messages, and account security.</p>
        </div>
        
        <div style="padding: 1rem 2rem 2rem 2rem;">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-16">
                    <div class="text-5xl mb-4 opacity-50">📭</div>
                    <h3 class="mb-2 font-bold text-muted">All Caught Up</h3>
                    <p class="text-muted mb-6">You have no new notifications right now.</p>
                    <a href="browse.php" class="btn btn-primary hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Explore CampusMarket</a>
                </div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($notifications as $n): ?>
                        <div class="p-5 flex gap-5 items-start hover-scale" style="background: <?php echo $n['is_read'] ? 'var(--bg-main)' : 'rgba(99,102,241,0.03)'; ?>; border: 1px solid var(--border-light); border-radius: var(--radius-md); transition: all 0.2s; border-left: 4px solid <?php echo $n['type'] === 'order' ? 'var(--primary)' : 'var(--secondary)'; ?>;">
                            <div style="width: 44px; height: 44px; border-radius: var(--radius-lg); background: <?php echo $n['is_read'] ? 'var(--bg-card)' : 'white'; ?>; border: 1px solid var(--border-light); display: flex; justify-content: center; align-items: center; box-shadow: var(--shadow-sm); flex-shrink: 0; font-size: 1.25rem;">
                                <?php echo $n['type'] === 'order' ? '📦' : '✨'; ?>
                            </div>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="mb-0 font-bold <?php echo !$n['is_read'] ? 'text-main' : 'text-muted'; ?>" style="font-size: 1.05rem; line-height: 1.4;"><?php echo sanitize($n['title']); ?></h4>
                                    <span class="text-muted small" style="white-space: nowrap; font-size: 0.75rem; background: var(--bg-card); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);"><?php echo timeAgo($n['created_at']); ?></span>
                                </div>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.5;"><?php echo sanitize($n['body']); ?></p>
                                
                                <?php if ($n['type'] === 'order'): ?>
                                    <a href="my_orders.php" class="btn btn-secondary btn-sm mt-3 hover-scale shadow-sm" style="border-radius: var(--radius-lg); background: white;">View Order Details →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
