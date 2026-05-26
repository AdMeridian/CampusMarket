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

<style>
/* ── Inbox/Activity Styles ─────────────────────────────────────── */
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

/* Card layout */
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
}

.convo-card.unread {
    background: rgba(99,102,241,0.02);
}

body.dark-mode .convo-card.unread {
    background: rgba(99,102,241,0.04);
}

/* Avatar / Icon */
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
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    overflow: hidden;
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
    font-weight: 600;
}

.convo-product {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.convo-body {
    font-size: 0.88rem;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.4;
}

.convo-card.unread .convo-body {
    color: var(--text-main);
    font-weight: 500;
}

/* Unread dot */
.convo-unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 2px;
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
</style>

<div class="inbox-wrap">
    <!-- Inbox Tabs -->
    <div class="flex gap-4 mb-8" style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem;">
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

    <!-- Header -->
    <div class="inbox-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h1>Activity Updates</h1>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="button" id="enable-browser-notifs" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 1rem; border: 1px solid var(--border-focus);">
                Enable Browser Alerts
            </button>
            <?php if (!empty($notifications)): ?>
                <form method="post" class="m-0">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" name="action" value="mark_all" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 1rem; border: 1px solid var(--border-focus); display: flex; align-items: center; gap: 0.35rem;">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <!-- Empty State -->
        <div class="inbox-empty">
            <svg class="inbox-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <h3>All caught up</h3>
            <p>You have no new updates or activity notifications at this time.</p>
            <a href="<?= BASE_URL ?>/pages/browse.php" class="btn btn-primary" style="border-radius: var(--radius-lg); padding: 0.6rem 1.75rem; font-weight: 600; font-size: 0.9rem;">Explore CampusMarket</a>
        </div>
    <?php else: ?>
        <!-- Notification List -->
        <div class="convo-list">
            <?php foreach ($notifications as $n): ?>
                <?php 
                    $isUnread = !$n['is_read'];
                    $isOrder = ($n['type'] === 'order');
                    $accentColor = $isOrder ? 'var(--primary)' : 'var(--secondary)';
                ?>
                <div class="convo-card <?= $isUnread ? 'unread' : '' ?>" style="border-left: 3px solid <?= $accentColor ?>;">
                    <!-- Icon Avatar -->
                    <div class="convo-avatar" style="color: var(--text-muted);">
                        <?php if ($isOrder): ?>
                            <svg style="width: 20px; height: 20px; color: var(--primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        <?php else: ?>
                            <svg style="width: 20px; height: 20px; color: var(--secondary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="convo-content">
                        <div class="convo-top">
                            <span class="convo-username" style="font-weight: 700;"><?= htmlspecialchars($n['title']) ?></span>
                            <span class="convo-time" style="<?= $isUnread ? 'color: ' . $accentColor . ';' : '' ?>"><?= timeAgo($n['created_at']) ?></span>
                        </div>
                        
                        <div class="convo-product" style="color: <?= $accentColor ?>;">
                            <?= $isOrder ? 'Order Update' : 'System Update' ?>
                        </div>
                        
                        <p class="convo-body" style="white-space: normal; line-height: 1.5; color: var(--text-main);">
                            <?= htmlspecialchars($n['body']) ?>
                        </p>
                        
                        <?php if ($isOrder): ?>
                            <div style="margin-top: 0.75rem;">
                                <a href="my_orders.php" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); background: var(--bg-surface); border: 1px solid var(--border-light); font-size: 0.8rem; padding: 0.35rem 0.8rem;">View Order Details →</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Indicators -->
                    <?php if ($isUnread): ?>
                        <div class="convo-unread-dot" style="background: <?= $accentColor ?>;"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('enable-browser-notifs');
    if (!btn) return;

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function saveSubscription(subscription) {
        const res = await fetch(window.__baseUrl + 'pages/api_push_subscriptions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.__csrfToken || ''
            },
            body: JSON.stringify({
                action: 'subscribe',
                subscription
            })
        });
        return res.ok;
    }

    btn.addEventListener('click', async function() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

        if (isIOS && !isStandalone) {
            alert('To enable browser alerts on your iPhone/iPad, please add this app to your Home Screen first:\n\n1. Tap the Share button in Safari (square with up arrow)\n2. Scroll down and select "Add to Home Screen"\n3. Open the app from your Home Screen and try again.');
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            alert('Push notifications are not supported on this device/browser.');
            return;
        }

        const vapidPublicKey = window.__env?.WEB_PUSH_PUBLIC_KEY || '';
        if (!vapidPublicKey) {
            alert('Push notifications are not configured yet. Please add WEB_PUSH_PUBLIC_KEY on the server.');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            alert('Browser alerts were blocked. You can enable them from browser site settings.');
            return;
        }

        const reg = await navigator.serviceWorker.ready;
        let subscription = await reg.pushManager.getSubscription();
        if (!subscription) {
            subscription = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });
        }

        const ok = await saveSubscription(subscription.toJSON());
        alert(ok ? 'Browser alerts enabled.' : 'Subscription saved locally but server sync failed.');
    });

    let lastUnreadNotifs = <?= (int)$navUnreadNotifs ?>;
    let reloadTimer = null;
    window.addEventListener('campusmarket:notifications-updated', function(e) {
        const next = Number(e?.detail?.notifs ?? lastUnreadNotifs);
        if (Number.isNaN(next) || next === lastUnreadNotifs) return;
        lastUnreadNotifs = next;
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => window.location.reload(), 200);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
