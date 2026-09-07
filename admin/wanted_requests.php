<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/admin_audit.php';

$pageTitle = 'Item Suggestions';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    verifyCsrfToken();
    $requestId = (int)$_POST['request_id'];
    $action = sanitize($_POST['action']);
    $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : '');

    if ($requestId > 0 && $status !== '') {
        $requestStmt = $pdo->prepare('SELECT id, requester_id, search_term, status FROM wanted_item_requests WHERE id = ? LIMIT 1');
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if ($request && $request['status'] === 'pending') {
            $pdo->beginTransaction();
            try {
                $updateStmt = $pdo->prepare("UPDATE wanted_item_requests SET status = ?, reviewed_by_admin_id = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$status, currentUserId(), $requestId]);
                $title = $status === 'approved' ? 'Wanted request approved' : 'Wanted request update';
                $body = $status === 'approved'
                    ? "Your request for '{$request['search_term']}' was approved and is now featured to sellers on campus."
                    : "Your request for '{$request['search_term']}' was not approved.";
                createNotification($pdo, (int)$request['requester_id'], 'system', $title, $body, $requestId);
                $pdo->commit();
                logAdminAction($pdo, $status === 'approved' ? 'approve_wanted_request' : 'reject_wanted_request', 'wanted_item_request', $requestId);
                setFlash('success', $status === 'approved' ? 'Suggestion approved and featured.' : 'Suggestion rejected.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('error', 'Could not update this suggestion.');
            }
        }
    }
    redirect(BASE_URL . 'admin/wanted_requests.php');
}

$filter = sanitize($_GET['status'] ?? 'pending');
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) $filter = 'pending';
$where = $filter === 'all'
    ? "WHERE (r.status <> 'pending' OR r.expires_at IS NULL OR r.expires_at > CURRENT_TIMESTAMP)"
    : "WHERE r.status = :status AND (r.status <> 'pending' OR r.expires_at IS NULL OR r.expires_at > CURRENT_TIMESTAMP)";
$stmt = $pdo->prepare("SELECT r.*, u.username FROM wanted_item_requests r JOIN users u ON u.id = r.requester_id {$where} ORDER BY r.created_at DESC LIMIT 100");
$filter === 'all' ? $stmt->execute() : $stmt->execute([':status' => $filter]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-page">
    <div class="admin-page-header"><div><div class="admin-breadcrumb"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a> › Item Suggestions</div><h1>Item suggestions</h1><p style="margin: .35rem 0 0; color: var(--text-muted);">Review product suggestions captured from searches with no matching listings.</p></div></div>
    <div class="flex gap-2 flex-wrap mb-6"><?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?><a class="btn btn-sm <?php echo $filter === $key ? 'btn-primary' : 'btn-secondary'; ?>" href="?status=<?php echo $key; ?>"><?php echo $label; ?></a><?php endforeach; ?></div>
    <?php if (empty($requests)): ?><div class="card" style="padding: 3rem; text-align: center; color: var(--text-muted);">No item suggestions in this view.</div><?php else: ?>
        <?php foreach ($requests as $request): ?><article class="card mb-4" style="padding: 1.25rem;"><div class="flex justify-between items-start gap-4"><div><h2 style="font-size: 1.1rem; margin: 0 0 .35rem;"><?php echo htmlspecialchars($request['search_term']); ?></h2><p class="text-muted" style="font-size: .8rem; margin: 0;">Suggested by @<?php echo htmlspecialchars($request['username']); ?> · <?php echo timeAgo($request['created_at']); ?></p></div><span class="badge"><?php echo htmlspecialchars($request['status']); ?></span></div><?php if ($request['status'] === 'pending'): ?><div class="flex gap-2 flex-wrap mt-4"><form method="POST"><?php echo csrfTokenField(); ?><input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>"><button class="btn btn-primary btn-sm" type="submit" name="action" value="approve">Approve &amp; feature</button></form><form method="POST"><?php echo csrfTokenField(); ?><input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>"><button class="btn btn-secondary btn-sm" type="submit" name="action" value="reject">Reject</button></form></div><?php endif; ?></article><?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>