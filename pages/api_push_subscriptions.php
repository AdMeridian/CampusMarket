<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrfTokenJson();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$action = sanitize((string)($payload['action'] ?? 'subscribe'));
$subscription = $payload['subscription'] ?? [];
$endpoint = trim((string)($subscription['endpoint'] ?? ''));
$keys = $subscription['keys'] ?? [];
$p256dh = trim((string)($keys['p256dh'] ?? ''));
$auth = trim((string)($keys['auth'] ?? ''));
$userId = currentUserId();

try {
    if ($action === 'unsubscribe') {
        if ($endpoint !== '') {
            $stmt = $pdo->prepare('DELETE FROM web_push_subscriptions WHERE user_id = :uid AND endpoint = :endpoint');
            $stmt->execute([':uid' => $userId, ':endpoint' => $endpoint]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing subscription keys']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO web_push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
        VALUES (:uid, :endpoint, :p256dh, :auth, :ua)
        ON CONFLICT (user_id, endpoint)
        DO UPDATE SET p256dh = EXCLUDED.p256dh,
                      auth = EXCLUDED.auth,
                      user_agent = EXCLUDED.user_agent,
                      updated_at = NOW()
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':endpoint' => $endpoint,
        ':p256dh' => $p256dh,
        ':auth' => $auth,
        ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[push-subscriptions] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save subscription']);
}

