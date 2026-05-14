<?php
// admin/reports.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

// Auth Check
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = "Moderation Queue";

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $action   = sanitize($_POST['action']);
    $reportId = (int)$_POST['report_id'];

    if ($action === 'dismiss') {
        $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?")->execute([$reportId]);
        setFlash('success', 'Report dismissed.');
    } elseif ($action === 'flag') {
        $stmt = $pdo->prepare("SELECT product_id FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $productId = $stmt->fetchColumn();
        if ($productId) {
            $pdo->prepare("UPDATE products SET status = 'flagged' WHERE id = ?")->execute([$productId]);
            $pdo->prepare("UPDATE reports SET status = 'reviewed' WHERE id = ?")->execute([$reportId]);
            setFlash('error', 'Item flagged and hidden from the marketplace.');
        }
    }
    redirect('reports.php');
}

// Fetch pending reports
$stmt = $pdo->query("
    SELECT r.*, p.title as product_title, u.username as reporter_name
    FROM reports r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.reporter_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
");
$reports = $stmt->fetchAll();
?>

<div class="container mt-8 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Moderation</div>
            <h1 class="mb-0 gradient-text" style="background: linear-gradient(135deg, #ef4444, #f43f5e); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Safety & Moderation Queue</h1>
        </div>
        <div class="badge" style="background: rgba(239,68,68,0.1); color: #b91c1c; font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><span class="animate-pulse inline-block mr-2" style="color: #ef4444;">●</span><?php echo count($reports); ?> Pending Reviews</div>
    </div>

    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Reported Item</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Reason Documented</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Reporter Identity</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Time Submitted</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Resolution Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="5" class="p-16 text-center text-muted" style="border-bottom: none;">
                            <div class="text-5xl mb-4 opacity-70">🛡️</div>
                            <h3 style="color: var(--success); font-weight: 600;">System Clear</h3>
                            <p>All items comply with community standards.<br>No pending reports in the queue!</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.02)'" onmouseout="this.style.background='transparent'">
                            <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                                <div class="font-bold text-main"><?php echo sanitize($r['product_title']); ?></div>
                                <a href="../pages/product.php?id=<?php echo $r['product_id']; ?>" target="_blank" class="small text-primary hover-scale inline-block mt-1" style="text-decoration: none; font-weight: 600;">View Live Item ↗</a>
                            </td>
                            <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                                <div style="background: rgba(239,68,68,0.05); border-left: 3px solid #ef4444; padding: 0.5rem 0.75rem; border-radius: 4px; font-style: italic; color: #7f1d1d; font-size: 0.9rem;">
                                    "<?php echo sanitize($r['reason']); ?>"
                                </div>
                            </td>
                            <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light);">
                                @<?php echo sanitize($r['reporter_name']); ?>
                            </td>
                            <td class="p-4 text-muted small" style="border-bottom: 1px solid var(--border-light);">
                                <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.75rem;"><?php echo timeAgo($r['created_at']); ?></span>
                            </td>
                            <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                                <form method="POST" class="flex justify-end gap-2 m-0">
                                    <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" name="action" value="dismiss" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Keep & Dismiss</button>
                                    <button type="submit" name="action" value="flag" class="btn btn-danger btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);" onclick="return confirm('Flag this item and hide it from the marketplace?')">Flag & Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
