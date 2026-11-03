<?php
// admin/announcements.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_audit.php';
require_once __DIR__ . '/../includes/announcements.php';

requireAdmin();
ensureAnnouncementsTable($pdo);

require_once __DIR__ . '/../includes/web_push.php';

if (!function_exists('getWebPushSubscriberUserIds')) {
    function getWebPushSubscriberUserIds(PDO $pdo, string $targetRole = 'all'): array {
        try {
            $sql = "SELECT DISTINCT s.user_id 
                    FROM web_push_subscriptions s 
                    JOIN users u ON u.id = s.user_id 
                    WHERE u.account_status = 'active'";
            if ($targetRole === 'admin') {
                $sql .= " AND u.role = 'admin'";
            } elseif ($targetRole === 'user') {
                $sql .= " AND u.role = 'user'";
            }
            $stmt = $pdo->query($sql);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('getAnnouncementTargetUserIds')) {
    function getAnnouncementTargetUserIds(PDO $pdo, string $targetRole = 'all'): array {
        try {
            $sql = "SELECT id FROM users WHERE account_status = 'active'";
            if ($targetRole === 'admin') {
                $sql .= " AND role = 'admin'";
            } elseif ($targetRole === 'user') {
                $sql .= " AND role = 'user'";
            } elseif ($targetRole === 'guest') {
                return [];
            }
            $stmt = $pdo->query($sql);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }
}

$pushSubscriberCount = count(getWebPushSubscriberUserIds($pdo, 'all'));


$pageTitle = "Platform Announcements";
$currentAdminId = currentUserId();

$announcementTemplates = [
    [
        'id' => 'services_pilot',
        'icon' => '🛠️',
        'category' => 'Feature Launch',
        'badge' => 'Services Pilot',
        'type' => 'success',
        'title' => 'Campus Services Pilot is Now Live!',
        'message' => 'Hire fellow students or offer your skills for tutoring, tech support, moving, design, and campus gigs.',
        'cta_text' => 'Explore Services',
        'cta_url' => BASE_URL . 'pages/services.php',
        'target_role' => 'all',
        'desc' => 'Announce the newly launched student skills directory.'
    ],
    [
        'id' => 'service_requests',
        'icon' => '📝',
        'category' => 'Job Board',
        'badge' => 'Service Gigs',
        'type' => 'info',
        'title' => 'Need Help with a Task or Project?',
        'message' => 'Post a free service request on the campus board to connect with student providers ready to help.',
        'cta_text' => 'Post a Request',
        'cta_url' => BASE_URL . 'pages/service_jobs.php?post=1',
        'target_role' => 'user',
        'desc' => 'Prompt students to post freelance tasks and gig requests.'
    ],
    [
        'id' => 'maintenance',
        'icon' => '⚠️',
        'category' => 'Operations',
        'badge' => 'Maintenance',
        'type' => 'warning',
        'title' => 'Scheduled System Maintenance',
        'message' => 'CampusMarket will undergo brief scheduled maintenance tonight at 02:00 AM UTC. Active listings will remain safe.',
        'cta_text' => 'System Health',
        'cta_url' => BASE_URL . 'pages/health.php',
        'target_role' => 'all',
        'desc' => 'Notice for scheduled downtime or infrastructure maintenance.'
    ],
    [
        'id' => 'campus_safety',
        'icon' => '🛡️',
        'category' => 'Trust & Safety',
        'badge' => 'Safety Tip',
        'type' => 'alert',
        'title' => 'Campus Safety Reminder',
        'message' => 'Always meet in well-lit, public campus areas (Student Center, Library) during daylight and inspect items thoroughly before paying.',
        'cta_text' => 'Safety Guide',
        'cta_url' => BASE_URL . 'pages/safety.php',
        'target_role' => 'all',
        'desc' => 'Remind users about safe campus meetup and exchange rules.'
    ],
    [
        'id' => 'textbook_fair',
        'icon' => '📚',
        'category' => 'Academic',
        'badge' => 'Term Start',
        'type' => 'info',
        'title' => 'New Semester Textbook & Supply Exchange',
        'message' => 'Get ready for classes! Find discounted course textbooks, calculators, dorm gear, and notes from senior students.',
        'cta_text' => 'Browse Textbooks',
        'cta_url' => BASE_URL . 'pages/browse.php?q=textbook',
        'target_role' => 'all',
        'desc' => 'Textbook and academic materials announcement for term start.'
    ],
    [
        'id' => 'verify_email',
        'icon' => '🎓',
        'category' => 'Verification',
        'badge' => 'Trust Badge',
        'type' => 'info',
        'title' => 'Verify Your Student Email for a Verified Badge',
        'message' => 'Build instant trust with buyers and clients by verifying your official campus email address in your profile.',
        'cta_text' => 'Verify Profile',
        'cta_url' => BASE_URL . 'pages/edit_profile.php',
        'target_role' => 'user',
        'desc' => 'Encourage unverified students to confirm their student status.'
    ],
    [
        'id' => 'mobile_pwa',
        'icon' => '📱',
        'category' => 'Mobile App',
        'badge' => 'App Install',
        'type' => 'info',
        'title' => 'Add CampusMarket to Your Phone Home Screen',
        'message' => 'Never miss a buyer message or deal! Install our progressive web app for instant push notifications and fast browsing.',
        'cta_text' => 'Install App',
        'cta_url' => BASE_URL . 'pages/home.php#install-app',
        'target_role' => 'all',
        'desc' => 'Promote PWA installation for push notifications.'
    ],
    [
        'id' => 'dorm_clearance',
        'icon' => '📦',
        'category' => 'Seasonal',
        'badge' => 'Term End',
        'type' => 'warning',
        'title' => 'End-of-Term Dorm Move-Out & Clearance',
        'message' => 'Moving out for break? List unwanted dorm items, appliances, and furniture before dorm checkout week.',
        'cta_text' => 'List Items Free',
        'cta_url' => BASE_URL . 'pages/create_listing.php',
        'target_role' => 'user',
        'desc' => 'Clearance and move-out reminder before holidays.'
    ]
];


// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim(sanitize($_POST['title'] ?? ''));
        $message = trim($_POST['message'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['info', 'warning', 'success', 'alert'], true) ? $_POST['type'] : 'info';
        $targetRole = in_array($_POST['target_role'] ?? '', ['all', 'user', 'guest', 'admin'], true) ? $_POST['target_role'] : 'all';
        $ctaText = trim(sanitize($_POST['cta_text'] ?? ''));
        $ctaUrl = trim($_POST['cta_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startsAt = !empty($_POST['starts_at']) ? date('Y-m-d H:i:s', strtotime($_POST['starts_at'])) : null;
        $endsAt = !empty($_POST['ends_at']) ? date('Y-m-d H:i:s', strtotime($_POST['ends_at'])) : null;

        if ($title === '' || $message === '') {
            setFlash('error', 'Title and message are required.');
        } else {
            $sendPush = !empty($_POST['send_push']);
            $sendBell = !empty($_POST['send_bell']);
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $activeVal = $driver === 'mysql' ? $isActive : ($isActive ? 'TRUE' : 'FALSE');

            if ($action === 'create') {
                $sql = "
                    INSERT INTO announcements (title, message, type, cta_text, cta_url, target_role, is_active, starts_at, ends_at, created_by)
                    VALUES (:t, :m, :tp, :ct, :cu, :tr, {$activeVal}, :sa, :ea, :cb)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':t' => $title,
                    ':m' => $message,
                    ':tp' => $type,
                    ':ct' => $ctaText !== '' ? $ctaText : null,
                    ':cu' => $ctaUrl !== '' ? $ctaUrl : null,
                    ':tr' => $targetRole,
                    ':sa' => $startsAt,
                    ':ea' => $endsAt,
                    ':cb' => $currentAdminId,
                ]);
                $newId = (int)$pdo->lastInsertId();
                
                $dispatchedChannels = ['Banner active'];
                if ($sendPush) {
                    $subscribers = getWebPushSubscriberUserIds($pdo, $targetRole);
                    $pushUrl = !empty($ctaUrl) ? $ctaUrl : (rtrim(BASE_URL, '/') . '/pages/home.php');
                    $pushCount = 0;
                    foreach ($subscribers as $sUid) {
                        triggerWebPushBestEffort((int)$sUid, $title, $message, $pushUrl);
                        $pushCount++;
                    }
                    if ($pushCount > 0) {
                        $dispatchedChannels[] = "{$pushCount} push notifications sent";
                    }
                }

                if ($sendBell) {
                    $targetUsers = getAnnouncementTargetUserIds($pdo, $targetRole);
                    $bellUrl = !empty($ctaUrl) ? $ctaUrl : (rtrim(BASE_URL, '/') . '/pages/home.php');
                    $bellCount = 0;
                    foreach ($targetUsers as $bUid) {
                        createNotification($pdo, (int)$bUid, 'system', "📢 " . $title, $message, null);
                        $bellCount++;
                    }
                    if ($bellCount > 0) {
                        $dispatchedChannels[] = "{$bellCount} bell alerts delivered";
                    }
                }

                logAdminAction($pdo, 'create_announcement', 'announcement', $newId, [
                    'title' => $title,
                    'type' => $type,
                    'send_push' => $sendPush,
                    'send_bell' => $sendBell
                ]);
                setFlash('success', 'Announcement published successfully (' . implode(' · ', $dispatchedChannels) . ').');
            } else {
                $sql = "
                    UPDATE announcements
                    SET title = :t, message = :m, type = :tp, cta_text = :ct, cta_url = :cu,
                        target_role = :tr, is_active = {$activeVal}, starts_at = :sa, ends_at = :ea,
                        updated_at = " . ($driver === 'mysql' ? 'NOW()' : 'CURRENT_TIMESTAMP') . "
                    WHERE id = :id
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $id,
                    ':t' => $title,
                    ':m' => $message,
                    ':tp' => $type,
                    ':ct' => $ctaText !== '' ? $ctaText : null,
                    ':cu' => $ctaUrl !== '' ? $ctaUrl : null,
                    ':tr' => $targetRole,
                    ':sa' => $startsAt,
                    ':ea' => $endsAt,
                ]);
                logAdminAction($pdo, 'update_announcement', 'announcement', $id, ['title' => $title]);
                setFlash('success', 'Announcement updated successfully.');
            }
        }
        redirect(BASE_URL . 'admin/announcements.php');
    }

    if ($action === 'broadcast_push') {
        $id = (int)($_POST['id'] ?? 0);
        $annStmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? LIMIT 1");
        $annStmt->execute([$id]);
        $targetAnn = $annStmt->fetch(PDO::FETCH_ASSOC);

        if ($targetAnn) {
            $subscribers = getWebPushSubscriberUserIds($pdo, $targetAnn['target_role']);
            $pushUrl = !empty($targetAnn['cta_url']) ? $targetAnn['cta_url'] : (rtrim(BASE_URL, '/') . '/pages/home.php');
            $pushCount = 0;
            foreach ($subscribers as $sUid) {
                triggerWebPushBestEffort((int)$sUid, $targetAnn['title'], $targetAnn['message'], $pushUrl);
                $pushCount++;
            }
            logAdminAction($pdo, 'broadcast_announcement_push', 'announcement', $id, ['push_recipients' => $pushCount]);
            setFlash('success', "🚀 Dispatched web push notification to {$pushCount} subscribed device(s).");
        } else {
            setFlash('error', 'Announcement not found.');
        }
        redirect(BASE_URL . 'admin/announcements.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'mysql') {
                $pdo->prepare("UPDATE announcements SET is_active = IF(is_active=1, 0, 1) WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            }
            logAdminAction($pdo, 'toggle_announcement', 'announcement', $id);
            setFlash('success', 'Announcement status toggled.');
        }
        redirect(BASE_URL . 'admin/announcements.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
            logAdminAction($pdo, 'delete_announcement', 'announcement', $id);
            setFlash('success', 'Announcement deleted.');
        }
        redirect(BASE_URL . 'admin/announcements.php');
    }
}

// Fetch all announcements
$announcements = $pdo->query("
    SELECT a.*, u.username AS creator_username
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.is_active DESC, a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin: calc(70px + 1.5rem) auto 4rem; padding: 0 1.25rem;">

    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
        <div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                <a href="index.php" style="color: inherit; text-decoration: none;">&larr; Admin Dashboard</a>
            </div>
            <h1 style="margin: 0; font-size: 1.85rem; font-weight: 800; color: var(--text-main);">Platform Announcements</h1>
            <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.92rem;">
                Broadcast high-priority notices, maintenance warnings, or promotional announcements across the marketplace.
            </p>
        </div>
        <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="toggleTemplatesSection()" style="display: inline-flex; align-items: center; gap: 0.4rem; border-radius: var(--radius-md); font-weight: 600; padding: 0.7rem 1.1rem;">
                <span>⚡</span> Presets & Templates
            </button>
            <button type="button" class="btn btn-primary" onclick="openAnnouncementModal()" style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: var(--radius-md); font-weight: 600; padding: 0.7rem 1.25rem;">
                <span>+</span> New Announcement
            </button>
        </div>
    </div>

    <!-- Quick Preset Templates Gallery -->
    <div id="templatesGallerySection" class="card mb-6" style="padding: 1.5rem; border-radius: var(--radius-lg); background: var(--bg-surface); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.25rem;">⚡</span>
                    <h2 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main);">Announcement Templates</h2>
                    <span style="font-size: 0.75rem; font-weight: 700; background: var(--bg-main); border: 1px solid var(--border-light); padding: 0.2rem 0.5rem; border-radius: 99px; color: var(--text-muted);"><?= count($announcementTemplates) ?> ready to use</span>
                </div>
                <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                    Click any template to instantly pre-fill the announcement banner and preview it.
                </p>
            </div>
            <button type="button" onclick="toggleTemplatesSection()" style="background: none; border: none; font-size: 0.85rem; color: var(--text-muted); cursor: pointer; text-decoration: underline;">
                Hide Templates
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
            <?php foreach ($announcementTemplates as $tpl): 
                $badgeColors = [
                    'info' => '#3b82f6',
                    'warning' => '#f59e0b',
                    'success' => '#10b981',
                    'alert' => '#ef4444'
                ];
                $color = $badgeColors[$tpl['type']] ?? '#3b82f6';
            ?>
            <div style="border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 1rem; background: var(--bg-main); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-light)'">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.35rem;"><?= $tpl['icon'] ?></span>
                        <span style="font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.45rem; border-radius: var(--radius-sm); background: color-mix(in srgb, <?= $color ?> 15%, transparent); color: <?= $color ?>;">
                            <?= strtoupper($tpl['type']) ?>
                        </span>
                    </div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.35rem; line-height: 1.3;">
                        <?= htmlspecialchars($tpl['title']) ?>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= htmlspecialchars($tpl['message']) ?>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.5rem; border-top: 1px solid var(--border-light); margin-top: 0.5rem;">
                    <span style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($tpl['category']) ?></span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAnnouncementModalWithTemplate('<?= $tpl['id'] ?>')" style="padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 600; border-radius: var(--radius-sm);">
                        Use Template &rarr;
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active List -->
    <div class="card" style="padding: 1.5rem; border-radius: var(--radius-lg); background: var(--bg-surface); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
        <?php if (empty($announcements)): ?>
            <div style="text-align: center; padding: 3rem 1rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">📢</div>
                <h3 style="margin: 0 0 0.5rem; color: var(--text-main);">No Announcements Yet</h3>
                <p style="color: var(--text-muted); max-width: 420px; margin: 0 auto 1.5rem; font-size: 0.9rem;">
                    Create your first site-wide banner to alert students about new features, holidays, or safety guidelines.
                </p>
                <button type="button" class="btn btn-primary" onclick="openAnnouncementModal()">Create Announcement</button>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid var(--border-light); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 0.75rem 0.5rem;">Status</th>
                            <th style="padding: 0.75rem 0.5rem;">Type</th>
                            <th style="padding: 0.75rem 0.5rem;">Title & Message</th>
                            <th style="padding: 0.75rem 0.5rem;">Audience</th>
                            <th style="padding: 0.75rem 0.5rem;">CTA</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $a): 
                            $isActive = !empty($a['is_active']);
                            $typeIcons = ['info' => 'ℹ️', 'warning' => '⚠️', 'success' => '✅', 'alert' => '🚨'];
                            $typeColors = [
                                'info' => '#3b82f6',
                                'warning' => '#f59e0b',
                                'success' => '#10b981',
                                'alert' => '#ef4444'
                            ];
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-light); vertical-align: middle;">
                            <td style="padding: 1rem 0.5rem; white-space: nowrap;">
                                <form method="POST" style="margin: 0; display: inline;">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; cursor: pointer; <?= $isActive ? 'background: #ecfdf5; color: #047857; border: 1px solid #10b981;' : 'background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db;' ?>">
                                        <?= $isActive ? '● ACTIVE' : '○ INACTIVE' ?>
                                    </button>
                                </form>
                            </td>
                            <td style="padding: 1rem 0.5rem; white-space: nowrap;">
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.55rem; border-radius: var(--radius-sm); background: color-mix(in srgb, <?= $typeColors[$a['type']] ?? '#3b82f6' ?> 15%, transparent); color: <?= $typeColors[$a['type']] ?? '#3b82f6' ?>;">
                                    <span><?= $typeIcons[$a['type']] ?? 'ℹ️' ?></span>
                                    <span><?= strtoupper($a['type']) ?></span>
                                </span>
                            </td>
                            <td style="padding: 1rem 0.5rem; max-width: 380px;">
                                <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.2rem;"><?= htmlspecialchars($a['title']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars($a['message']) ?>
                                </div>
                            </td>
                            <td style="padding: 1rem 0.5rem; white-space: nowrap;">
                                <span style="font-size: 0.8rem; background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); color: var(--text-muted);">
                                    <?= ucfirst($a['target_role']) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 0.5rem; white-space: nowrap; font-size: 0.82rem;">
                                <?php if (!empty($a['cta_text'])): ?>
                                    <a href="<?= htmlspecialchars($a['cta_url'] ?: '#') ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                        <?= htmlspecialchars($a['cta_text']) ?> &rarr;
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">None</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem 0.5rem; text-align: right; white-space: nowrap;">
                                <form method="POST" style="display: inline; margin: 0 0.25rem 0 0;" onsubmit="return confirm('Send push notification for this announcement to all subscribed devices?');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="broadcast_push">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-sm" title="Send push notification to subscribed devices" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-main); padding: 0.35rem 0.55rem; border-radius: var(--radius-sm); font-size: 0.78rem;">
                                        🔔 Push
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm" onclick='editAnnouncement(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-main); padding: 0.35rem 0.65rem; border-radius: var(--radius-sm); margin-right: 0.25rem; font-size: 0.78rem;">
                                    Edit
                                </button>
                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this announcement?');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.35rem 0.65rem; border-radius: var(--radius-sm); font-size: 0.78rem;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Dialog -->
<div id="announcementModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-xl); max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-xl);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 id="modalTitle" style="margin: 0; font-size: 1.35rem; font-weight: 800; color: var(--text-main);">New Announcement</h2>
            <button type="button" onclick="closeAnnouncementModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>

        <form method="POST" id="announcementForm">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="announcementId" value="0">

            <!-- Template Preset Selector -->
            <div style="margin-bottom: 1.25rem; background: var(--bg-main); padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1.5px solid var(--border-light);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label for="templateSelector" style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.35rem;">
                        <span>⚡</span> Preset Template (Autofill)
                    </label>
                    <span id="templateNotice" style="font-size: 0.75rem; color: var(--text-muted);">Select a preset to fill form</span>
                </div>
                <select id="templateSelector" class="premium-input w-full" onchange="applyAnnouncementTemplate(this.value)" style="background: var(--bg-surface);">
                    <option value="">-- Choose a template to autofill --</option>
                    <?php foreach ($announcementTemplates as $tpl): ?>
                        <option value="<?= htmlspecialchars($tpl['id']) ?>">
                            <?= $tpl['icon'] ?> <?= htmlspecialchars($tpl['title']) ?> (<?= htmlspecialchars($tpl['category']) ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="__blank__">✕ Clear / Blank Form</option>
                </select>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Banner Type</label>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.6rem; border: 1.5px solid var(--border-light); border-radius: var(--radius-md); cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600;">
                        <input type="radio" name="type" value="info" checked onchange="updatePreview()">
                        <span>ℹ️ Info</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.6rem; border: 1.5px solid var(--border-light); border-radius: var(--radius-md); cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600;">
                        <input type="radio" name="type" value="warning" onchange="updatePreview()">
                        <span>⚠️ Warning</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.6rem; border: 1.5px solid var(--border-light); border-radius: var(--radius-md); cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600;">
                        <input type="radio" name="type" value="success" onchange="updatePreview()">
                        <span>✅ Success</span>
                    </label>
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.6rem; border: 1.5px solid var(--border-light); border-radius: var(--radius-md); cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600;">
                        <input type="radio" name="type" value="alert" onchange="updatePreview()">
                        <span>🚨 Alert</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label for="annTitle" style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Headline / Title *</label>
                <input type="text" id="annTitle" name="title" class="premium-input w-full" placeholder="e.g. Scheduled System Upgrade" required maxlength="150" oninput="updatePreview()">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label for="annMessage" style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Message Content *</label>
                <textarea id="annMessage" name="message" class="premium-input w-full" rows="3" placeholder="Brief announcement details for students..." required oninput="updatePreview()"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label for="annCtaText" style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Button Text (Optional)</label>
                    <input type="text" id="annCtaText" name="cta_text" class="premium-input w-full" placeholder="e.g. Learn More" maxlength="60" oninput="updatePreview()">
                </div>
                <div>
                    <label for="annCtaUrl" style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Button Link (Optional)</label>
                    <input type="text" id="annCtaUrl" name="cta_url" class="premium-input w-full" placeholder="e.g. /pages/rules.php or https://...">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label for="annTarget" style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--text-main);">Audience</label>
                    <select id="annTarget" name="target_role" class="premium-input w-full">
                        <option value="all">Everyone (All Visitors)</option>
                        <option value="user">Logged-in Students Only</option>
                        <option value="guest">Guests Only (Not logged in)</option>
                        <option value="admin">Admins Only (Internal)</option>
                    </select>
                </div>
                <div style="display: flex; align-items: center; padding-top: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="annActive" name="is_active" value="1" checked style="width: 18px; height: 18px;">
                        <span>Activate banner on site</span>
                    </label>
                </div>
            </div>

            <!-- Additional Broadcast Channels -->
            <div style="background: var(--bg-main); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem;">
                <div style="font-weight: 700; font-size: 0.82rem; color: var(--text-main); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                    <span>📢</span> Additional Delivery Channels (Optional)
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 500; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="sendPushCheckbox" name="send_push" value="1" style="width: 17px; height: 17px;">
                        <span>📱 Send as <strong>Web Push Notification</strong> to subscribed phones/desktops <small style="color: var(--text-muted);">(<?= $pushSubscriberCount ?> subscriber devices)</small></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 500; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="sendBellCheckbox" name="send_bell" value="1" style="width: 17px; height: 17px;">
                        <span>🔔 Deliver to user in-app <strong>Notification Inbox (Bell icon)</strong></span>
                    </label>
                </div>
            </div>

            <!-- Live Dual Preview Section (Website Banner + Push Notification) -->
            <div style="margin: 1.5rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                        Live Preview
                    </span>
                    <div style="display: flex; gap: 0.35rem; background: var(--bg-main); padding: 0.2rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                        <button type="button" id="prevTabBanner" onclick="switchPreviewTab('banner')" style="background: var(--bg-surface); border: none; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.78rem; font-weight: 700; color: var(--text-main); cursor: pointer; box-shadow: var(--shadow-sm);">
                            🖥️ Website Banner
                        </button>
                        <button type="button" id="prevTabPush" onclick="switchPreviewTab('push')" style="background: transparent; border: none; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); cursor: pointer;">
                            📱 Push Notification
                        </button>
                    </div>
                </div>

                <!-- 1. Simulated Website Top Banner Preview -->
                <div id="previewBannerContainer" style="background: var(--bg-main); padding: 0.85rem; border-radius: var(--radius-lg); border: 1px dashed var(--border-light);">
                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span>🌐</span> <span>Sticky top banner below navbar:</span>
                    </div>
                    <div id="livePreviewBanner" style="padding: 0.85rem 1rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 0.85rem; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #1e40af;">
                        <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                            <span id="prevIcon" style="font-size: 1.25rem; flex-shrink: 0;">ℹ️</span>
                            <div style="min-width: 0;">
                                <strong id="prevTitle" style="display: inline-block; font-weight: 700; margin-right: 0.35rem;">Scheduled System Upgrade</strong>
                                <span style="opacity: 0.6; margin-right: 0.35rem;">—</span>
                                <span id="prevMessage" style="font-size: 0.88rem; opacity: 0.95;">Brief announcement details for students...</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0;">
                            <span id="prevBtn" style="font-size: 0.82rem; font-weight: 700; text-decoration: underline; white-space: nowrap; cursor: pointer;">Learn More &rarr;</span>
                            <span style="opacity: 0.5; font-size: 1.1rem; line-height: 1; cursor: pointer;">&times;</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Simulated OS Push Notification Preview -->
                <div id="previewPushContainer" style="display: none; background: var(--bg-main); padding: 1rem; border-radius: var(--radius-lg); border: 1px dashed var(--border-light);">
                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span>🔔</span> <span>Device push notification (Android / Windows / macOS):</span>
                    </div>
                    <div style="max-width: 420px; margin: 0 auto; background: #ffffff; color: #111827; border-radius: 14px; padding: 0.85rem 1rem; box-shadow: 0 4px 16px rgba(0,0,0,0.12); border: 1px solid #e5e7eb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.45rem;">
                            <div style="display: flex; align-items: center; gap: 0.45rem;">
                                <img src="<?= rtrim(BASE_URL, '/') ?>/public/images/logo.png" alt="CampusMarket" style="width: 18px; height: 18px; border-radius: 4px; object-fit: contain;">
                                <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #4b5563;">CampusMarket</span>
                            </div>
                            <span style="font-size: 0.7rem; color: #9ca3af;">just now</span>
                        </div>
                        <div id="prevPushTitle" style="font-size: 0.92rem; font-weight: 700; color: #111827; margin-bottom: 0.2rem; line-height: 1.25;">
                            Scheduled System Upgrade
                        </div>
                        <div id="prevPushMessage" style="font-size: 0.82rem; color: #4b5563; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            Brief announcement details for students...
                        </div>
                        <div style="margin-top: 0.5rem; padding-top: 0.4rem; border-top: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                            <span id="prevPushUrl" style="font-size: 0.7rem; color: #6b7280;">tap to open</span>
                            <span id="prevPushBadge" style="font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 4px; background: #eff6ff; color: #1d4ed8;">SYSTEM NOTICE</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAnnouncementModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn" style="font-weight: 600;">Save Announcement</button>
            </div>
        </form>
    </div>
</div>

<script>
const announcementTemplates = <?= json_encode($announcementTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function toggleTemplatesSection() {
    const sec = document.getElementById('templatesGallerySection');
    if (!sec) return;
    sec.style.display = (sec.style.display === 'none') ? 'block' : 'none';
}

function openAnnouncementModal() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('announcementId').value = '0';
    document.getElementById('modalTitle').textContent = 'New Announcement';
    document.getElementById('modalSubmitBtn').textContent = 'Publish Announcement';
    document.getElementById('templateSelector').value = '';
    document.getElementById('templateNotice').textContent = 'Select a preset to fill form';
    document.getElementById('annTitle').value = '';
    document.getElementById('annMessage').value = '';
    document.getElementById('annCtaText').value = '';
    document.getElementById('annCtaUrl').value = '';
    document.getElementById('annActive').checked = true;
    document.getElementById('annTarget').value = 'all';
    document.getElementById('sendPushCheckbox').checked = false;
    document.getElementById('sendBellCheckbox').checked = false;
    document.querySelector('input[name="type"][value="info"]').checked = true;
    updatePreview();
    const modal = document.getElementById('announcementModal');
    modal.style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', function() {
    const annForm = document.getElementById('announcementForm');
    if (annForm) {
        annForm.addEventListener('submit', function(e) {
            const isBanner = document.getElementById('annActive')?.checked;
            const isPush = document.getElementById('sendPushCheckbox')?.checked;
            const isBell = document.getElementById('sendBellCheckbox')?.checked;
            if (!isBanner && !isPush && !isBell) {
                if (!confirm('This announcement is marked inactive and has no delivery channels selected (Banner, Push, or Bell). It will be saved as an unpublished draft. Continue?')) {
                    e.preventDefault();
                }
            }
        });
    }
});

function openAnnouncementModalWithTemplate(templateId) {
    openAnnouncementModal();
    applyAnnouncementTemplate(templateId);
}

function applyAnnouncementTemplate(templateId) {
    const sel = document.getElementById('templateSelector');
    if (sel && sel.value !== templateId) {
        sel.value = templateId;
    }

    if (templateId === '__blank__') {
        document.getElementById('annTitle').value = '';
        document.getElementById('annMessage').value = '';
        document.getElementById('annCtaText').value = '';
        document.getElementById('annCtaUrl').value = '';
        document.getElementById('annTarget').value = 'all';
    document.getElementById('sendPushCheckbox').checked = false;
    document.getElementById('sendBellCheckbox').checked = false;
        document.querySelector('input[name="type"][value="info"]').checked = true;
        document.getElementById('templateNotice').textContent = 'Form cleared';
        updatePreview();
        return;
    }

    const tpl = announcementTemplates.find(t => t.id === templateId);
    if (!tpl) return;

    document.getElementById('annTitle').value = tpl.title || '';
    document.getElementById('annMessage').value = tpl.message || '';
    document.getElementById('annCtaText').value = tpl.cta_text || '';
    document.getElementById('annCtaUrl').value = tpl.cta_url || '';
    document.getElementById('annTarget').value = tpl.target_role || 'all';

    const typeRadio = document.querySelector('input[name="type"][value="' + (tpl.type || 'info') + '"]');
    if (typeRadio) {
        typeRadio.checked = true;
    }

    const notice = document.getElementById('templateNotice');
    if (notice) {
        notice.textContent = 'Template loaded: ' + tpl.badge;
    }

    updatePreview();
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

function editAnnouncement(data) {
    const sel = document.getElementById('templateSelector');
    if (sel) sel.value = '';
    const notice = document.getElementById('templateNotice');
    if (notice) notice.textContent = 'Custom announcement';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('announcementId').value = data.id;
    document.getElementById('modalTitle').textContent = 'Edit Announcement #' + data.id;
    document.getElementById('modalSubmitBtn').textContent = 'Update Announcement';
    document.getElementById('annTitle').value = data.title || '';
    document.getElementById('annMessage').value = data.message || '';
    document.getElementById('annCtaText').value = data.cta_text || '';
    document.getElementById('annCtaUrl').value = data.cta_url || '';
    document.getElementById('annActive').checked = (data.is_active == 1 || data.is_active === true || data.is_active === 't');
    document.getElementById('annTarget').value = data.target_role || 'all';
    document.getElementById('sendPushCheckbox').checked = false;
    document.getElementById('sendBellCheckbox').checked = false;
    
    const typeRadio = document.querySelector('input[name="type"][value="' + (data.type || 'info') + '"]');
    if (typeRadio) typeRadio.checked = true;

    updatePreview();
    document.getElementById('announcementModal').style.display = 'flex';
}

function switchPreviewTab(tab) {
    const bannerContainer = document.getElementById('previewBannerContainer');
    const pushContainer = document.getElementById('previewPushContainer');
    const btnBanner = document.getElementById('prevTabBanner');
    const btnPush = document.getElementById('prevTabPush');

    if (tab === 'push') {
        bannerContainer.style.display = 'none';
        pushContainer.style.display = 'block';
        btnPush.style.background = 'var(--bg-surface)';
        btnPush.style.color = 'var(--text-main)';
        btnPush.style.fontWeight = '700';
        btnPush.style.boxShadow = 'var(--shadow-sm)';
        btnBanner.style.background = 'transparent';
        btnBanner.style.color = 'var(--text-muted)';
        btnBanner.style.fontWeight = '600';
        btnBanner.style.boxShadow = 'none';
    } else {
        bannerContainer.style.display = 'block';
        pushContainer.style.display = 'none';
        btnBanner.style.background = 'var(--bg-surface)';
        btnBanner.style.color = 'var(--text-main)';
        btnBanner.style.fontWeight = '700';
        btnBanner.style.boxShadow = 'var(--shadow-sm)';
        btnPush.style.background = 'transparent';
        btnPush.style.color = 'var(--text-muted)';
        btnPush.style.fontWeight = '600';
        btnPush.style.boxShadow = 'none';
    }
}

function updatePreview() {
    const title = document.getElementById('annTitle').value || 'Scheduled System Upgrade';
    const message = document.getElementById('annMessage').value || 'Brief announcement details for students...';
    const cta = document.getElementById('annCtaText').value || '';
    const type = document.querySelector('input[name="type"]:checked')?.value || 'info';

    const prev = document.getElementById('livePreviewBanner');
    const prevIcon = document.getElementById('prevIcon');
    const prevTitle = document.getElementById('prevTitle');
    const prevMessage = document.getElementById('prevMessage');
    const prevBtn = document.getElementById('prevBtn');

    prevTitle.textContent = title;
    prevMessage.textContent = message;

    if (cta) {
        prevBtn.textContent = cta + ' →';
        prevBtn.style.display = 'inline';
    } else {
        prevBtn.style.display = 'none';
    }

    const configs = {
        info: { icon: 'ℹ️', bg: 'rgba(59, 130, 246, 0.12)', border: 'rgba(59, 130, 246, 0.3)', color: '#1e40af' },
        warning: { icon: '⚠️', bg: 'rgba(245, 158, 11, 0.12)', border: 'rgba(245, 158, 11, 0.35)', color: '#92400e' },
        success: { icon: '✅', bg: 'rgba(16, 185, 129, 0.12)', border: 'rgba(16, 185, 129, 0.35)', color: '#065f46' },
        alert: { icon: '🚨', bg: 'rgba(239, 68, 68, 0.12)', border: 'rgba(239, 68, 68, 0.35)', color: '#991b1b' }
    };

    const cfg = configs[type] || configs.info;
    prevIcon.textContent = cfg.icon;
    prev.style.background = cfg.bg;
    prev.style.borderColor = cfg.border;
    prev.style.color = cfg.color;

    // Update Push Notification preview
    const prevPushTitle = document.getElementById('prevPushTitle');
    const prevPushMessage = document.getElementById('prevPushMessage');
    const prevPushUrl = document.getElementById('prevPushUrl');
    const prevPushBadge = document.getElementById('prevPushBadge');

    if (prevPushTitle) prevPushTitle.textContent = title;
    if (prevPushMessage) prevPushMessage.textContent = message;
    if (prevPushUrl) prevPushUrl.textContent = cta ? (cta + ' →') : 'tap to open';
    if (prevPushBadge) {
        const badgeMeta = {
            info: { text: 'CAMPUS UPDATE', bg: '#eff6ff', color: '#1d4ed8' },
            warning: { text: 'ADVISORY', bg: '#fffbeb', color: '#b45309' },
            success: { text: 'NEW UPDATE', bg: '#ecfdf5', color: '#047857' },
            alert: { text: 'URGENT ALERT', bg: '#fef2f2', color: '#b91c1c' }
        };
        const meta = badgeMeta[type] || badgeMeta.info;
        prevPushBadge.textContent = meta.text;
        prevPushBadge.style.background = meta.bg;
        prevPushBadge.style.color = meta.color;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
