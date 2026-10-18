<?php
// admin/announcements.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_audit.php';
require_once __DIR__ . '/../includes/announcements.php';

requireAdmin();
ensureAnnouncementsTable($pdo);

$pageTitle = "Platform Announcements";
$currentAdminId = currentUserId();

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
                logAdminAction($pdo, 'create_announcement', 'announcement', $newId, ['title' => $title, 'type' => $type]);
                setFlash('success', 'Announcement published successfully.');
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
        <button type="button" class="btn btn-primary" onclick="openAnnouncementModal()" style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: var(--radius-md); font-weight: 600; padding: 0.7rem 1.25rem;">
            <span>+</span> New Announcement
        </button>
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
                        <span>Activate immediately</span>
                    </label>
                </div>
            </div>

            <!-- Live Banner Preview -->
            <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg-main); border-radius: var(--radius-lg); border: 1px dashed var(--border-light);">
                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">Live Banner Preview</div>
                <div id="livePreviewBanner" style="padding: 0.8rem 1rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #1e40af;">
                    <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
                        <span id="prevIcon" style="font-size: 1.2rem;">ℹ️</span>
                        <div>
                            <strong id="prevTitle">Scheduled System Upgrade</strong>
                            <span style="opacity: 0.75; margin: 0 0.35rem;">·</span>
                            <span id="prevMessage" style="font-size: 0.9rem;">Brief announcement details for students...</span>
                        </div>
                    </div>
                    <span id="prevBtn" style="font-size: 0.8rem; font-weight: 700; text-decoration: underline; white-space: nowrap;">Learn More &rarr;</span>
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
function openAnnouncementModal() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('announcementId').value = '0';
    document.getElementById('modalTitle').textContent = 'New Announcement';
    document.getElementById('modalSubmitBtn').textContent = 'Publish Announcement';
    document.getElementById('annTitle').value = '';
    document.getElementById('annMessage').value = '';
    document.getElementById('annCtaText').value = '';
    document.getElementById('annCtaUrl').value = '';
    document.getElementById('annActive').checked = true;
    document.querySelector('input[name="type"][value="info"]').checked = true;
    updatePreview();
    const modal = document.getElementById('announcementModal');
    modal.style.display = 'flex';
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

function editAnnouncement(data) {
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
    
    const typeRadio = document.querySelector('input[name="type"][value="' + (data.type || 'info') + '"]');
    if (typeRadio) typeRadio.checked = true;

    updatePreview();
    document.getElementById('announcementModal').style.display = 'flex';
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
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
