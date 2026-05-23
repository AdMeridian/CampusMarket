<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __('inbox.messages_tab');
requireLogin();

$currentUserId = currentUserId();

// Fetch Admin ID for quick support link
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$adminId = $adminStmt->fetchColumn();

// Fetch the latest message for each conversation
$stmt = $pdo->prepare("
    SELECT 
        m.id, m.sender_id, m.receiver_id, m.product_id, m.body, m.is_read, m.created_at,
        COALESCE(p.title, 'General Support') as product_title,
        CASE WHEN m.sender_id = :uid1 THEN m.receiver_id ELSE m.sender_id END as other_user_id,
        u.username as other_username,
        u.avatar as other_avatar,
        u.role as other_role
    FROM messages m
    LEFT JOIN products p ON m.product_id = p.id
    JOIN users u ON u.id = (CASE WHEN m.sender_id = :uid2 THEN m.receiver_id ELSE m.sender_id END)
    WHERE m.id IN (
        SELECT MAX(id)
        FROM messages 
        WHERE sender_id = :uid3 OR receiver_id = :uid4
        GROUP BY COALESCE(product_id, 0), (CASE WHEN sender_id = :uid5 THEN receiver_id ELSE sender_id END)
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
            <span><?= __('inbox.messages_tab') ?></span>
            <?php 
                $tabUnreadMessages = countUnreadMessages($pdo, $currentUserId);
                if ($tabUnreadMessages > 0): 
            ?>
                <span class="badge badge-primary"><?= $tabUnreadMessages ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/pages/notifications.php" class="flex items-center gap-2 px-4 py-2 text-muted hover-text-main" style="font-weight: 500;">
            <span><?= __('inbox.activity_tab') ?></span>
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
            <h1><?= __('inbox.title') ?></h1>
            <?php if ($unreadCount > 0): ?>
                <span class="inbox-unread-count"><?= $unreadCount ?> <?= __('inbox.new') ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($conversations)): ?>
            <span style="font-size: 0.82rem; color: var(--text-muted);"><?= count($conversations) ?> <?= count($conversations) === 1 ? __('inbox.conversation') : __('inbox.conversations') ?></span>
        <?php endif; ?>
    </div>

    <!-- Direct Message User Search Bar -->
    <div class="glass-panel mb-6 p-4" style="border-radius: var(--radius-lg); position: relative; z-index: 10;">
        <div class="relative" style="position: relative;">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted" style="position: absolute; top: 50%; transform: translateY(-50%); left: 0.75rem; color: var(--text-muted); display: flex; align-items: center;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input type="text" id="user-search-input" placeholder="<?= __('inbox.search_placeholder') ?>" 
                   class="w-full bg-surface border-light py-3 pl-10 pr-4 text-main transition-all duration-200" 
                   style="width: 100%; border-radius: var(--radius-md); font-size: 0.95rem; border: 1px solid var(--border-light); outline: none; background: var(--bg-surface); padding: 0.75rem 1rem 0.75rem 2.75rem; box-sizing: border-box;" 
                   autocomplete="off">
        </div>
        
        <!-- Suggestions dropdown -->
        <div id="search-suggestions" class="absolute w-full left-0 mt-2 bg-surface glass-panel hidden" 
             style="position: absolute; left: 0; width: 100%; margin-top: 0.5rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); overflow: hidden; border: 1px solid var(--border-light); max-height: 300px; overflow-y: auto; background: var(--bg-surface); z-index: 999; box-sizing: border-box;">
            <div id="suggestions-container" style="display: flex; flex-direction: column;">
                <!-- Dynamic items will be injected here -->
            </div>
        </div>
    </div>

    <?php if ($adminId && $adminId != $currentUserId): ?>
    <div class="mb-6">
        <a href="messages.php?other_user_id=<?= $adminId ?>&product_id=0" class="flex items-center gap-4 p-4 rounded-xl shadow-sm transition-all hover-scale" style="background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(139,92,246,0.08) 100%); border: 1px solid rgba(99,102,241,0.2); text-decoration: none;">
            <div class="flex items-center justify-center text-primary bg-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; border-radius: 50%;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <h4 class="m-0 font-bold" style="color: var(--primary); font-size: 1.05rem;"><?= __('inbox.need_help') ?></h4>
                <p class="m-0 text-muted small" style="font-size: 0.85rem; font-weight: 500;"><?= __('inbox.reach_admin') ?></p>
            </div>
            <div class="ml-auto text-primary opacity-60">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (empty($conversations)): ?>
        <!-- Empty State -->
        <div class="inbox-empty">
            <svg class="inbox-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"></path>
            </svg>
            <h3><?= __('inbox.empty_title') ?></h3>
            <p><?= __('inbox.empty_desc') ?></p>
            <a href="<?= BASE_URL ?>/pages/browse.php" class="btn btn-primary" style="border-radius: var(--radius-lg); padding: 0.6rem 1.75rem; font-weight: 600; font-size: 0.9rem;"><?= __('inbox.browse_items') ?></a>
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
                        <?php 
                            $isSupport = ($conv['product_id'] == 0 && ($conv['other_role'] === 'admin' || isAdmin()));
                            $convoLabel = $conv['product_id'] == 0 
                                ? ($isSupport ? __('inbox.support_label') : __('inbox.direct_message')) 
                                : __('inbox.re_prefix') . ' ' . htmlspecialchars($conv['product_title']);
                        ?>
                        <div class="convo-product" style="<?= $conv['product_id'] == 0 ? 'color: var(--secondary);' : '' ?>">
                            <?= $convoLabel ?>
                        </div>
                        <p class="convo-body">
                            <?php if ($conv['sender_id'] == $currentUserId): ?>
                                <span class="convo-body-prefix"><?= __('inbox.you_prefix') ?></span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('user-search-input');
    const suggestionsPanel = document.getElementById('search-suggestions');
    const suggestionsContainer = document.getElementById('suggestions-container');
    let debounceTimer;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 1) {
            suggestionsPanel.classList.add('hidden');
            suggestionsContainer.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`<?= BASE_URL ?>/pages/api_messages.php?action=search_users&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderSuggestions(data.users, query);
                    }
                })
                .catch(err => console.error('Search error:', err));
        }, 250);
    });

    function renderSuggestions(users, query) {
        suggestionsContainer.innerHTML = '';
        suggestionsPanel.classList.remove('hidden');

        if (users.length === 0) {
            const noUsersTemplate = <?= json_encode(__('inbox.no_users_found')) ?>;
            const noUsersText = noUsersTemplate.replace('{query}', escapeHtml(query));
            suggestionsContainer.innerHTML = `
                <div class="p-4 text-center text-muted small" style="color: var(--text-muted);">
                    ${noUsersText}
                </div>
            `;
            return;
        }

        users.forEach(user => {
            const item = document.createElement('div');
            item.className = 'flex items-center gap-3 p-3 cursor-pointer hover-bg-light transition-colors';
            item.style.borderBottom = '1px solid var(--border-light)';
            item.style.padding = '0.75rem 1rem';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.gap = '0.75rem';
            item.style.cursor = 'pointer';
            
            // Custom hover state utilizing active styles dynamically
            item.addEventListener('mouseenter', () => {
                item.style.background = 'rgba(99,102,241,0.08)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.background = 'transparent';
            });

            const avatarHTML = user.avatar_url 
                ? `<img src="${user.avatar_url}" style="width: 36px; height: 36px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid var(--border-light);">`
                : `<div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: var(--bg-main); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; border: 1px solid var(--border-light);">${user.username.substring(0, 2).toUpperCase()}</div>`;

            item.innerHTML = `
                <div style="flex-shrink: 0;">${avatarHTML}</div>
                <div style="flex-grow: 1; min-width: 0;">
                    <span class="font-bold text-main" style="display: block; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'Outfit', sans-serif;">@${escapeHtml(user.username)}</span>
                    <span class="text-muted small" style="font-size: 0.75rem; color: var(--text-muted);">${<?= json_encode(__('inbox.start_conversation')) ?>}</span>
                </div>
                <div style="flex-shrink: 0; color: var(--primary);">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </div>
            `;

            item.addEventListener('click', function() {
                window.location.href = `<?= BASE_URL ?>/pages/messages.php?product_id=0&other_user_id=${user.id}`;
            });

            suggestionsContainer.appendChild(item);
        });
    }

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsPanel.contains(e.target)) {
            suggestionsPanel.classList.add('hidden');
        }
    });

    function escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    let lastUnreadMessages = <?= (int)$unreadCount ?>;
    let reloadTimer = null;
    window.addEventListener('campusmarket:notifications-updated', function(e) {
        const next = Number(e?.detail?.messages ?? lastUnreadMessages);
        if (Number.isNaN(next) || next === lastUnreadMessages) return;
        lastUnreadMessages = next;
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => window.location.reload(), 200);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
