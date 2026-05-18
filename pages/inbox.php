<?php
$pageTitle = "Inbox";
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$currentUserId = currentUserId();

// Fetch the latest message for each conversation
$stmt = $pdo->prepare("
    SELECT 
        m.id, m.sender_id, m.receiver_id, m.product_id, m.body, m.is_read, m.created_at,
        COALESCE(p.title, 'General Support') as product_title,
        CASE WHEN m.sender_id = :uid1 THEN m.receiver_id ELSE m.sender_id END as other_user_id,
        u.username as other_username,
        u.avatar as other_avatar
    FROM messages m
    LEFT JOIN products p ON m.product_id = p.id
    JOIN users u ON u.id = (CASE WHEN m.sender_id = :uid2 THEN m.receiver_id ELSE m.sender_id END)
    WHERE m.id IN (
        SELECT MAX(id)
        FROM messages 
        WHERE sender_id = :uid3 OR receiver_id = :uid4
        GROUP BY product_id, (CASE WHEN sender_id = :uid5 THEN receiver_id ELSE sender_id END)
    )
    ORDER BY m.created_at DESC
");
$stmt->execute([
    ':uid1' => $currentUserId,
    ':uid2' => $currentUserId,
    ':uid3' => $currentUserId,
    ':uid4' => $currentUserId,
    ':uid5' => $currentUserId,
]);
$conversations = $stmt->fetchAll();

$unreadCount = array_reduce($conversations, function($carry, $item) use ($currentUserId) {
    return $carry + ($item['receiver_id'] == $currentUserId && empty($item['is_read']) ? 1 : 0);
}, 0);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Inbox Styles ─────────────────────────────────────── */
.inbox-wrap {
    max-width: 820px;
    margin: 6rem auto 5rem;
    padding: 0 1.25rem;
}

.inbox-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.inbox-header h1 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-main);
    margin: 0;
}

.inbox-unread-count {
    background: var(--primary);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.25rem 0.7rem;
    border-radius: var(--radius-lg);
    letter-spacing: 0.02em;
}

/* Conversation card */
.convo-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: inherit;
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
    position: relative;
    cursor: pointer;
}

.convo-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
    border-color: var(--primary);
    color: inherit;
}

.convo-card.unread {
    border-left: 3px solid var(--primary);
    background: rgba(99,102,241,0.02);
}

/* Avatar */
.convo-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-lg);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    color: var(--primary);
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.convo-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Content */
.convo-content {
    flex: 1;
    min-width: 0;
}

.convo-top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.15rem;
}

.convo-username {
    font-family: 'Outfit', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}

.convo-card.unread .convo-username {
    font-weight: 800;
}

.convo-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    flex-shrink: 0;
    white-space: nowrap;
}

.convo-card.unread .convo-time {
    color: var(--primary);
    font-weight: 600;
}

.convo-product {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--primary);
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.convo-body {
    font-size: 0.88rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
    line-height: 1.4;
}

.convo-card.unread .convo-body {
    color: var(--text-main);
    font-weight: 500;
}

.convo-body-prefix {
    opacity: 0.55;
    font-size: 0.85em;
}

/* Arrow indicator */
.convo-arrow {
    flex-shrink: 0;
    color: var(--text-muted);
    opacity: 0.4;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.convo-card:hover .convo-arrow {
    opacity: 0.8;
    transform: translateX(2px);
    color: var(--primary);
}

/* Unread dot */
.convo-unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 2px;
    background: var(--primary);
    flex-shrink: 0;
}

/* List */
.convo-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

/* Empty state */
.inbox-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
    background: var(--bg-surface);
    border: 1px dashed var(--border-light);
    border-radius: var(--radius-lg);
}

.inbox-empty-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 1.25rem;
    color: var(--text-muted);
    opacity: 0.35;
}

.inbox-empty h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 0.4rem;
}

.inbox-empty p {
    font-size: 0.9rem;
    max-width: 380px;
    margin: 0 auto 1.5rem;
    line-height: 1.5;
}

/* Dark mode tweaks */
body.dark-mode .convo-card.unread {
    background: rgba(99,102,241,0.04);
}
</style>

<div class="inbox-wrap">

    <!-- Inbox Tabs -->
    <div class="flex gap-4 mb-8" style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">
        <a href="<?= BASE_URL ?>/pages/inbox.php" class="flex items-center gap-2 px-4 py-2" style="font-weight: 700; border-bottom: 2px solid var(--primary); color: var(--primary);">
            <span>Messages</span>
            <?php 
                $tabUnreadMessages = countUnreadMessages($pdo, $currentUserId);
                if ($tabUnreadMessages > 0): 
            ?>
                <span class="badge badge-primary"><?= $tabUnreadMessages ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/pages/notifications.php" class="flex items-center gap-2 px-4 py-2 text-muted hover-text-main" style="font-weight: 500;">
            <span>Activity</span>
            <?php 
                $navUnreadNotifs = countUnreadNotifications($pdo, $currentUserId);
                if ($navUnreadNotifs > 0): 
            ?>
                <span class="badge badge-accent"><?= $navUnreadNotifs ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Header -->
    <div class="inbox-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h1>Direct Messages</h1>
            <?php if ($unreadCount > 0): ?>
                <span class="inbox-unread-count"><?= $unreadCount ?> new</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($conversations)): ?>
            <span style="font-size: 0.82rem; color: var(--text-muted);"><?= count($conversations) ?> conversation<?= count($conversations) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>

    <?php if (empty($conversations)): ?>
        <!-- Empty State -->
        <div class="inbox-empty">
            <svg class="inbox-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"></path>
            </svg>
            <h3>Your inbox is empty</h3>
            <p>When you reach out to sellers or receive inquiries about your items, conversations will appear here.</p>
            <a href="<?= BASE_URL ?>/pages/browse.php" class="btn btn-primary" style="border-radius: var(--radius-lg); padding: 0.6rem 1.75rem; font-weight: 600; font-size: 0.9rem;">Browse Items</a>
        </div>
    <?php else: ?>
        <!-- Conversation List -->
        <div class="convo-list">
            <?php foreach ($conversations as $conv): ?>
                <?php
                    $isUnread = ($conv['receiver_id'] == $currentUserId && empty($conv['is_read']));
                    $initials = strtoupper(substr($conv['other_username'], 0, 2));
                    $hasAvatar = !empty($conv['other_avatar']);
                ?>
                <a href="<?= BASE_URL ?>/pages/messages.php?product_id=<?= $conv['product_id'] ?>&other_user_id=<?= $conv['other_user_id'] ?>"
                   class="convo-card <?= $isUnread ? 'unread' : '' ?>">

                    <!-- Avatar -->
                    <div class="convo-avatar">
                        <?php if ($hasAvatar): ?>
                            <img src="<?= avatarUrl($conv['other_avatar']) ?>" alt="<?= htmlspecialchars($conv['other_username']) ?>">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="convo-content">
                        <div class="convo-top">
                            <span class="convo-username"><?= htmlspecialchars($conv['other_username']) ?></span>
                            <span class="convo-time"><?= timeAgo($conv['created_at']) ?></span>
                        </div>
                        <div class="convo-product" style="<?= $conv['product_id'] == 0 ? 'color: var(--secondary);' : '' ?>">
                            <?= $conv['product_id'] == 0 ? 'Support Inquiry' : 'Re: ' . htmlspecialchars($conv['product_title']) ?>
                        </div>
                        <p class="convo-body">
                            <?php if ($conv['sender_id'] == $currentUserId): ?>
                                <span class="convo-body-prefix">You:</span>
                            <?php endif; ?>
                            <?= htmlspecialchars(mb_strimwidth($conv['body'], 0, 90, '...')) ?>
                        </p>
                    </div>

                    <!-- Indicators -->
                    <?php if ($isUnread): ?>
                        <div class="convo-unread-dot"></div>
                    <?php endif; ?>

                    <svg class="convo-arrow" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
