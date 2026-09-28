<?php
// admin/users.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/functions_member2.php';
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

// Fetch all users
$stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$allUsers = $stmt->fetchAll();
$totalUsersCount = count($allUsers);

// Calculate Campus Distribution Stats
$campusDistribution = [];
foreach ($allUsers as $u) {
    $uInfo = getUniversityInfoFromEmail($u['email'] ?? '');
    $dKey = $uInfo['domain'];
    if (!isset($campusDistribution[$dKey])) {
        $campusDistribution[$dKey] = [
            'code'   => $uInfo['code'],
            'name'   => $uInfo['name'],
            'domain' => $dKey,
            'color'  => $uInfo['color'],
            'bg'     => $uInfo['bg'],
            'count'  => 0,
        ];
    }
    $campusDistribution[$dKey]['count']++;
}

// Sort by student count descending
uasort($campusDistribution, function ($a, $b) {
    return $b['count'] <=> $a['count'];
});

$campusesCount = count($campusDistribution);
$topCampus = !empty($campusDistribution) ? reset($campusDistribution) : null;
$topCampusShare = ($totalUsersCount > 0 && $topCampus) ? round(($topCampus['count'] / $totalUsersCount) * 100) : 0;

// Filter handling
$selectedCampus = trim($_GET['campus'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$users = [];
foreach ($allUsers as $u) {
    $uInfo = getUniversityInfoFromEmail($u['email'] ?? '');
    
    // Filter by Campus
    if ($selectedCampus !== '' && $selectedCampus !== 'all') {
        if (strtolower($uInfo['domain']) !== strtolower($selectedCampus) && strtolower($uInfo['code']) !== strtolower($selectedCampus)) {
            continue;
        }
    }
    
    // Filter by Search Query
    if ($searchQuery !== '') {
        $qLower = strtolower($searchQuery);
        $matchesUser = str_contains(strtolower((string)($u['username'] ?? '')), $qLower);
        $matchesEmail = str_contains(strtolower((string)($u['email'] ?? '')), $qLower);
        if (!$matchesUser && !$matchesEmail) {
            continue;
        }
    }
    
    $users[] = $u;
}

include '../includes/header.php';
?>

<div class="container mt-24 mb-16">
    <div class="flex justify-between items-end mb-8 flex-wrap gap-4">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Users</div>
            <h1 class="mb-0"><?= __('admin.manage_users') ?></h1>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <div class="badge" style="background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border-light); font-size: 0.88rem; padding: 0.5rem 0.9rem; border-radius: var(--radius-lg); font-weight: 600; box-shadow: var(--shadow-sm);">
                🎓 <?php echo $campusesCount; ?> Campuses Represented
            </div>
            <div class="badge" style="background: var(--primary-light); color: var(--primary); border: 1px solid rgba(var(--primary-rgb, 14, 165, 233), 0.25); font-size: 0.88rem; padding: 0.5rem 0.9rem; border-radius: var(--radius-lg); font-weight: 600;">
                👥 <?php echo $totalUsersCount; ?> Registered Users
            </div>
        </div>
    </div>

    <!-- ── KPI Summary Cards ──────────────────────────────────────── -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <!-- Card 1: Represented Campuses -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: var(--radius-lg); background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                🏛️
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Campuses Represented</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); line-height: 1.2;"><?php echo $campusesCount; ?></div>
                <div style="font-size: 0.76rem; color: var(--text-muted);">Active universities across students</div>
            </div>
        </div>

        <!-- Card 2: Total Registered Community -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--success); display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: var(--radius-lg); background: var(--success-bg, rgba(16, 185, 129, 0.1)); color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                👥
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Student Accounts</div>
                <div style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); line-height: 1.2;"><?php echo $totalUsersCount; ?></div>
                <div style="font-size: 0.76rem; color: var(--text-muted);">Total verified user accounts</div>
            </div>
        </div>

        <!-- Card 3: Leading Community -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: var(--radius-lg); background: rgba(245, 158, 11, 0.12); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                ⭐
            </div>
            <div>
                <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Leading Campus</div>
                <div style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">
                    <?php echo $topCampus ? htmlspecialchars($topCampus['code']) : 'None'; ?>
                    <?php if ($topCampus): ?>
                        <span style="font-size: 0.85rem; font-weight: 600; color: #d97706; margin-left: 4px;">(<?php echo $topCampus['count']; ?> · <?php echo $topCampusShare; ?>%)</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.76rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                    <?php echo $topCampus ? htmlspecialchars($topCampus['name']) : 'No student registrations yet'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Campus Breakdown & Filter Bar ──────────────────────────── -->
    <div class="card mb-6" style="padding: 1.25rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.05rem;">Campus Representation Breakdown</h3>
                <p class="text-muted small" style="margin: 0.2rem 0 0 0;">Click on any campus badge to filter the student list below.</p>
            </div>
            <?php if ($selectedCampus !== '' || $searchQuery !== ''): ?>
                <a href="users.php" class="btn btn-sm btn-secondary" style="font-size: 0.78rem; padding: 0.3rem 0.75rem; border-radius: var(--radius-full);">
                    ✕ Clear Filters
                </a>
            <?php endif; ?>
        </div>

        <!-- Campus Pills List -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center;">
            <a href="users.php<?php echo $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : ''; ?>"
               style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 600; transition: transform 0.15s ease, background 0.15s ease; <?php echo ($selectedCampus === '' || $selectedCampus === 'all') ? 'background: var(--primary); color: #fff; box-shadow: var(--shadow-sm);' : 'background: var(--bg-surface-2, #f1f5f9); color: var(--text-muted); border: 1px solid var(--border-light);'; ?>">
                All Campuses <span style="opacity: 0.85; font-size: 0.75rem; padding: 0.1rem 0.4rem; background: rgba(0,0,0,0.12); border-radius: var(--radius-full);"><?php echo $totalUsersCount; ?></span>
            </a>

            <?php foreach ($campusDistribution as $dKey => $cStat): ?>
                <?php 
                    $isActive = strtolower($selectedCampus) === strtolower($dKey) || strtolower($selectedCampus) === strtolower($cStat['code']);
                    $pct = $totalUsersCount > 0 ? round(($cStat['count'] / $totalUsersCount) * 100) : 0;
                ?>
                <a href="users.php?campus=<?php echo urlencode($dKey); ?><?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>"
                   title="<?php echo htmlspecialchars($cStat['name']); ?> (<?php echo $pct; ?>% of student base)"
                   style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.4rem 0.85rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 600; transition: transform 0.15s ease, box-shadow 0.15s ease; <?php echo $isActive ? 'background: ' . $cStat['color'] . '; color: #ffffff; box-shadow: 0 4px 12px ' . $cStat['bg'] . ';' : 'background: ' . $cStat['bg'] . '; color: ' . $cStat['color'] . '; border: 1px solid ' . $cStat['color'] . '33;'; ?>">
                    <span><?php echo htmlspecialchars($cStat['code']); ?></span>
                    <span style="font-size: 0.75rem; padding: 0.1rem 0.45rem; border-radius: var(--radius-full); <?php echo $isActive ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: ' . $cStat['color'] . '; color: #fff;'; ?>">
                        <?php echo $cStat['count']; ?>
                    </span>
                    <span style="font-size: 0.72rem; opacity: 0.85; font-weight: 500;">(<?php echo $pct; ?>%)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search input & Filters -->
        <form method="GET" action="users.php" style="display: flex; gap: 0.6rem; margin-top: 1.25rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px; position: relative;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by username or email..." class="premium-input" style="width: 100%; padding: 0.55rem 0.85rem; font-size: 0.88rem; border-radius: var(--radius-md);">
            </div>
            <select name="campus" class="premium-input" style="min-width: 180px; padding: 0.55rem 0.85rem; font-size: 0.88rem; border-radius: var(--radius-md);">
                <option value="">All Campuses (<?php echo $totalUsersCount; ?>)</option>
                <?php foreach ($campusDistribution as $dKey => $cStat): ?>
                    <option value="<?php echo htmlspecialchars($dKey); ?>" <?php echo (strtolower($selectedCampus) === strtolower($dKey) || strtolower($selectedCampus) === strtolower($cStat['code'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cStat['code']); ?> — <?php echo htmlspecialchars($cStat['name']); ?> (<?php echo $cStat['count']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.1rem; font-size: 0.88rem; border-radius: var(--radius-md);">
                Filter
            </button>
            <?php if ($selectedCampus !== '' || $searchQuery !== ''): ?>
                <a href="users.php" class="btn btn-secondary" style="padding: 0.55rem 1rem; font-size: 0.88rem; border-radius: var(--radius-md);">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Active Filter Feedback -->
    <?php if ($selectedCampus !== '' || $searchQuery !== ''): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-surface); padding: 0.6rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin-bottom: 1rem; font-size: 0.85rem;">
            <div>
                Showing <strong><?php echo count($users); ?></strong> of <strong><?php echo $totalUsersCount; ?></strong> user<?php echo $totalUsersCount != 1 ? 's' : ''; ?>
                <?php if ($selectedCampus !== ''): ?>
                    matching campus <strong><?php echo htmlspecialchars($selectedCampus); ?></strong>
                <?php endif; ?>
                <?php if ($searchQuery !== ''): ?>
                    matching "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                <?php endif; ?>
            </div>
            <a href="users.php" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.8rem;">View All Users →</a>
        </div>
    <?php endif; ?>

    <!-- ── Users Table ───────────────────────────────────────────── -->
    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">User Identity</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Campus / University</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Email Address</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Role</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Join Date</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Administrative Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php 
                        $uCampus = getUniversityInfoFromEmail($u['email'] ?? '');
                    ?>
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex items-center gap-4">
                                <div style="width: 42px; height: 42px; background: var(--primary-light); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--primary); flex-shrink: 0;">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div style="display: flex; flex-direction: column; justify-content: center;">
                                    <div class="font-bold text-main" style="line-height: 1.2;">@<?php echo sanitize($u['username']); ?></div>
                                    <div class="text-muted small mt-1" style="font-family: monospace; font-size: 0.75rem;">UID: #<?php echo $u['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <a href="users.php?campus=<?php echo urlencode($uCampus['domain']); ?>" 
                               style="text-decoration: none; display: inline-flex; flex-direction: column; align-items: flex-start; gap: 2px;">
                                <span class="badge" style="background: <?php echo $uCampus['bg']; ?>; color: <?php echo $uCampus['color']; ?>; border: 1px solid <?php echo $uCampus['color']; ?>33; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.55rem; border-radius: var(--radius-sm);">
                                    <?php echo htmlspecialchars($uCampus['code']); ?>
                                </span>
                                <span style="font-size: 0.72rem; color: var(--text-muted); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($uCampus['name']); ?>">
                                    <?php echo htmlspecialchars($uCampus['name']); ?>
                                </span>
                            </a>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light); font-weight: 500; font-size: 0.88rem;"><?php echo sanitize($u['email']); ?></td>
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
                No students found matching the selected criteria.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
