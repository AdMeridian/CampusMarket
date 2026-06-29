<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$channel = strtolower(trim((string)($_POST['channel'] ?? 'other')));
$allowedChannels = ['native', 'copy', 'whatsapp', 'telegram', 'twitter', 'facebook', 'other'];

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

if (!in_array($channel, $allowedChannels, true)) {
    $channel = 'other';
}

try {
    $pdo->query('SELECT 1 FROM product_shares LIMIT 1');
} catch (Throwable $e) {
    echo json_encode(['success' => true, 'share_count' => 0, 'note' => 'tracking_unavailable']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.status, p.user_id AS seller_id
    FROM products p
    WHERE p.id = :id
      AND p.status <> 'deleted'
    LIMIT 1
");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

$isOwner = isLoggedIn() && (
    (int)currentUserId() === (int)$product['seller_id'] || isAdmin()
);
if (($product['status'] ?? '') !== 'active' && !$isOwner) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Listing not shareable']);
    exit;
}

$userId = isLoggedIn() ? (int)currentUserId() : null;

try {
    $ins = $pdo->prepare('
        INSERT INTO product_shares (product_id, user_id, channel)
        VALUES (:pid, :uid, :channel)
    ');
    $ins->execute([
        ':pid' => $productId,
        ':uid' => $userId,
        ':channel' => $channel,
    ]);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_shares WHERE product_id = ?');
    $countStmt->execute([$productId]);
    $shareCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'share_count' => $shareCount,
    ]);
} catch (Throwable $e) {
    error_log('[api_product_share] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not record share']);
}
