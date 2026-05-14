<?php
// admin/users.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';

// Auth Check
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = "Manage Users";

// Handle Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'make_admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User promoted to Admin.');
    } elseif ($_GET['action'] === 'remove_admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Admin privileges removed.');
    } elseif ($_GET['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User account deleted.');
    }
    redirect('users.php');
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-8 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Users</div>
            <h1 class="mb-0 gradient-text">User Management</h1>
        </div>
        <div class="badge" style="background: rgba(16,185,129,0.1); color: #059669; font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo count($users); ?> Registered Users</div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
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
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(16,185,129,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex items-center gap-4">
                                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; flex-shrink: 0; box-shadow: var(--shadow-sm);">
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
                                <span class="badge badge-primary shadow-sm" style="background: linear-gradient(135deg, var(--primary), #818cf8);">Admin</span>
                            <?php else: ?>
                                <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border-light); color: var(--text-muted);">Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-muted small" style="border-bottom: 1px solid var(--border-light);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                            <div class="flex justify-end gap-2">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <a href="?action=remove_admin&id=<?php echo $u['id']; ?>" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Demote</a>
                                <?php else: ?>
                                    <a href="?action=make_admin&id=<?php echo $u['id']; ?>" class="btn btn-primary btn-sm hover-scale shadow-sm" style="background: var(--secondary); border-radius: var(--radius-lg);">Make Admin</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" onclick="return confirm('Delete this user account? This cannot be undone.')">Delete</a>
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
