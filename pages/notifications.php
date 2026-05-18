<?php
$pageTitle = "Notifications";
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$currentUserId = currentUserId();

// Handle specific actions like marking single/all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
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
$unreadIds = array_column(array_filter($notifications, fn($n) => empty($n['is_read'])), 'id');
if (!empty($unreadIds)) {
    $placeholders = str_repeat('?,', count($unreadIds) - 1) . '?';
    $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)");
    $updateStmt->execute($unreadIds);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container main-content mb-20" style="max-width: 800px; margin-top: 6rem;">
    <div class="glass-panel" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-lg);">
        <!-- Inbox Tabs -->
        <div class="flex gap-4 mb-8" style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem; padding: 0 2rem;">
            <a href="<?= BASE_URL ?>/pages/inbox.php" class="flex items-center gap-2 px-4 py-2 text-muted hover-text-main" style="font-weight: 500;">
                <span>Messages</span>
                <?php 
                    $navUnreadMessages = countUnreadMessages($pdo, $currentUserId);
                    if ($navUnreadMessages > 0): 
                ?>
                    <span class="badge badge-primary"><?= $navUnreadMessages ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/pages/notifications.php" class="flex items-center gap-2 px-4 py-2" style="font-weight: 700; border-bottom: 2px solid var(--accent); color: var(--accent);">
                <span>Activity</span>
                <?php 
                    $navUnreadNotifs = countUnreadNotifications($pdo, $currentUserId);
                    if ($navUnreadNotifs > 0): 
                ?>
                    <span class="badge badge-accent"><?= $navUnreadNotifs ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div style="background: var(--bg-surface); padding: 2rem; border-bottom: 1px solid var(--border-light);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 class="mb-0 text-main font-bold" style="letter-spacing: -0.5px; font-size: 2rem;">Activity Updates</h1>
                <?php if (!empty($notifications)): ?>
                    <form method="post" class="m-0">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" name="action" value="mark_all" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 1rem; border: 1px solid var(--border-focus);"><svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mark All Read</button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="text-muted mt-2 mb-0">Stay up to date with your orders, messages, and account security.</p>
        </div>
        
        <div style="padding: 1rem 2rem 2rem 2rem;">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-16">
                    <div class="mb-4 opacity-50" style="display: flex; justify-content: center; align-items: center;"><svg style="width: 48px; height: 48px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                    <h3 class="mb-2 font-bold text-muted">All Caught Up</h3>
                    <p class="text-muted mb-6">You have no new notifications right now.</p>
                    <a href="browse.php" class="btn btn-primary hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Explore CampusMarket</a>
                </div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($notifications as $n): ?>
                        <div class="p-5 flex gap-5 items-start hover-scale" style="background: <?php echo $n['is_read'] ? 'var(--bg-main)' : 'rgba(0,0,0,0.02)'; ?>; border: 1px solid var(--border-light); border-radius: var(--radius-md); transition: all 0.2s; border-left: 4px solid <?php echo $n['type'] === 'order' ? 'var(--primary)' : 'var(--secondary)'; ?>;">
                            <div style="width: 44px; height: 44px; border-radius: var(--radius-lg); background: <?php echo $n['is_read'] ? 'var(--bg-card)' : 'var(--bg-main)'; ?>; border: 1px solid var(--border-light); display: flex; justify-content: center; align-items: center; box-shadow: var(--shadow-sm); flex-shrink: 0; color: var(--text-muted);">
                                <?php if ($n['type'] === 'order'): ?>
                                    <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                <?php else: ?>
                                    <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <?php endif; ?>
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
