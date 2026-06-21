<?php
// admin/users.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/admin_audit.php';
require_once __DIR__ . '/../includes/report_moderation.php';

$pageTitle = __('admin.manage_users');
$currentAdminId = currentUserId();

function adminUsersFindSupabaseUuid(string $userEmail): ?string {
    if ($userEmail === '' || supabaseUrl() === '' || supabaseServiceRoleKey() === '') {
        return null;
    }

    $authResponse = supabaseAdminRequest('GET', 'admin/users?per_page=1000');
    if (!$authResponse['ok'] || empty($authResponse['data']['users'])) {
        return null;
    }

    foreach ($authResponse['data']['users'] as $su) {
        if (isset($su['email']) && strtolower((string) $su['email']) === strtolower($userEmail)) {
            return $su['id'] ?? null;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    verifyCsrfToken();

    $action = sanitize((string) $_POST['action']);
    $id = (int) $_POST['id'];

    if ($id <= 0) {
        setFlash('error', 'Invalid user selected.');
        redirect('users.php');
    }

    $targetStmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ? LIMIT 1');
    $targetStmt->execute([$id]);
    $targetUser = $targetStmt->fetch();

    if (!$targetUser) {
        setFlash('error', 'User not found.');
        redirect('users.php');
    }

    if ($id === $currentAdminId && in_array($action, ['remove_admin', 'delete'], true)) {
        setFlash('error', 'You cannot perform this action on your own account.');
        redirect('users.php');
    }

    if ($action === 'remove_admin' && ($targetUser['role'] ?? '') === 'admin') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            setFlash('error', 'Cannot remove the last admin account.');
            redirect('users.php');
        }
    }

    $userEmail = (string) ($targetUser['email'] ?? '');
    $supabaseUserUuid = adminUsersFindSupabaseUuid($userEmail);
    $supabaseConfigured = supabaseUrl() !== '' && supabaseServiceRoleKey() !== '';

    if ($action === 'make_admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->execute([$id]);

        $syncMsg = '';
        if ($supabaseConfigured) {
            if ($supabaseUserUuid) {
                $res = supabaseAdminRequest('PUT', 'admin/users/' . $supabaseUserUuid, [
                    'app_metadata' => ['role' => 'admin'],
                ]);
                if (!$res['ok']) {
                    $syncMsg = ' (Warning: Supabase sync failed: ' . ($res['error'] ?? 'Unknown error') . ')';
                }
            } else {
                $syncMsg = ' (Warning: User not found in Supabase Auth)';
            }
        }

        setFlash($syncMsg !== '' ? 'warning' : 'success', ($syncMsg !== '' ? 'User promoted to Admin locally' . $syncMsg : __('admin.flash_user_promoted')));
        logAdminAction($pdo, 'make_admin', 'user', $id, ['email' => $userEmail]);
    } elseif ($action === 'remove_admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
        $stmt->execute([$id]);

        $syncMsg = '';
        if ($supabaseConfigured) {
            if ($supabaseUserUuid) {
                $res = supabaseAdminRequest('PUT', 'admin/users/' . $supabaseUserUuid, [
                    'app_metadata' => ['role' => 'user'],
                ]);
                if (!$res['ok']) {
                    $syncMsg = ' (Warning: Supabase sync failed: ' . ($res['error'] ?? 'Unknown error') . ')';
                }
            } else {
                $syncMsg = ' (Warning: User not found in Supabase Auth)';
            }
        }

        setFlash($syncMsg !== '' ? 'warning' : 'success', ($syncMsg !== '' ? 'Admin privileges removed locally' . $syncMsg : __('admin.flash_user_demoted')));
        logAdminAction($pdo, 'remove_admin', 'user', $id, ['email' => $userEmail]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        $syncMsg = '';
        if ($supabaseConfigured) {
            if ($supabaseUserUuid) {
                $res = supabaseAdminRequest('DELETE', 'admin/users/' . $supabaseUserUuid);
                if (!$res['ok']) {
                    $syncMsg = ' (Warning: Supabase delete failed: ' . ($res['error'] ?? 'Unknown error') . ')';
                }
            } else {
                $syncMsg = ' (Warning: User not found in Supabase Auth)';
            }
        }

        setFlash($syncMsg !== '' ? 'warning' : 'success', ($syncMsg !== '' ? 'User account deleted locally' . $syncMsg : __('admin.flash_user_deleted')));
        logAdminAction($pdo, 'delete_user', 'user', $id, ['email' => $userEmail]);
    } elseif ($action === 'unsuspend') {
        if (reportUnsuspendUser($pdo, $id)) {
            createNotification($pdo, $id, 'system', __('admin.report_unsuspend_title'), __('admin.report_unsuspend_body'));
            setFlash('success', __('admin.flash_user_unsuspended'));
            logAdminAction($pdo, 'unsuspend_user', 'user', $id, ['email' => $userEmail]);
        } else {
            setFlash('error', __('admin.report_unsuspend_failed'));
        }
    } else {
        setFlash('error', 'Unknown action.');
    }

    redirect('users.php');
}

$stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-24 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Users</div>
            <h1 class="mb-0"><?= __('admin.manage_users') ?></h1>
        </div>
        <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($users); ?> Registered Users</div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">User Identity</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Email Address</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Role</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Join Date</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Administrative Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex items-center gap-4">
                                <div style="width: 44px; height: 44px; background: var(--primary-light); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--primary); flex-shrink: 0;">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div style="display: flex; flex-direction: column; justify-content: center;">
                                    <div class="font-bold text-main" style="line-height: 1.2;">@<?php echo sanitize($u['username']); ?></div>
                                    <div class="text-muted small mt-1" style="font-family: monospace; font-size: 0.75rem;">UID: #<?php echo $u['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light); font-weight: 500;"><?php echo sanitize($u['email']); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-primary shadow-sm">Admin</span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);">Member</span>
                            <?php endif; ?>
                            <?php if (reportsAccountStatusSupported($pdo) && ($u['account_status'] ?? 'active') === 'suspended'): ?>
                                <span class="badge badge-danger shadow-sm" style="margin-left: 0.35rem;"><?= __('admin.user_status_suspended') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-muted small" style="border-bottom: 1px solid var(--border-light);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex justify-end gap-2">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <?php if ((int) $u['id'] !== $currentAdminId): ?>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Remove admin privileges from this user?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="action" value="remove_admin">
                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Demote</button>
                                    </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post" style="margin: 0;">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="action" value="make_admin">
                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm hover-scale shadow-sm" style="background: var(--secondary); border-radius: var(--radius-lg);">Make Admin</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (reportsAccountStatusSupported($pdo) && ($u['account_status'] ?? 'active') === 'suspended' && (int)$u['id'] !== $currentAdminId): ?>
                                <form method="post" style="margin: 0;">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="unsuspend">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);"><?= __('admin.user_action_unsuspend') ?></button>
                                </form>
                                <?php endif; ?>
                                <?php if ((int) $u['id'] !== $currentAdminId): ?>
                                <form method="post" style="margin: 0;" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($users)): ?>
            <div class="text-center p-8 text-muted">
                No users found.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
