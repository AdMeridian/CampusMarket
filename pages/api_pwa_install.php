<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rate_limit.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$limit = rateLimitAllow($pdo, 'pwa-install:' . clientIpAddress(), 60, 3600);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . (int)($limit['retry_after'] ?? 3600));
    echo json_encode(['success' => false, 'error' => 'Too many requests']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$installationId = trim((string)($payload['installation_id'] ?? ''));
$event = trim((string)($payload['event'] ?? 'heartbeat'));
$isStandalone = !empty($payload['standalone']);
$platform = substr(trim((string)($payload['platform'] ?? '')), 0, 40);
$displayMode = substr(trim((string)($payload['display_mode'] ?? '')), 0, 30);

if (!preg_match('/^[a-f0-9-]{36}$/i', $installationId) || !in_array($event, ['install', 'heartbeat'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid installation payload']);
    exit;
}

try {
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $userId = isLoggedIn() ? (int)currentUserId() : null;
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);

    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("INSERT INTO pwa_installations
            (installation_id, user_id, first_seen_at, last_seen_at, install_event_at, last_standalone_at, platform, display_mode, user_agent)
            VALUES (:id, :uid, NOW(), NOW(), :install_at, :standalone_at, :platform, :display_mode, :ua)
            ON DUPLICATE KEY UPDATE
                user_id = COALESCE(VALUES(user_id), user_id),
                last_seen_at = NOW(),
                install_event_at = COALESCE(install_event_at, VALUES(install_event_at)),
                last_standalone_at = COALESCE(VALUES(last_standalone_at), last_standalone_at),
                platform = VALUES(platform), display_mode = VALUES(display_mode), user_agent = VALUES(user_agent)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO public.pwa_installations
            (installation_id, user_id, first_seen_at, last_seen_at, install_event_at, last_standalone_at, platform, display_mode, user_agent)
            VALUES (:id, :uid, NOW(), NOW(), :install_at, :standalone_at, :platform, :display_mode, :ua)
            ON CONFLICT (installation_id) DO UPDATE SET
                user_id = COALESCE(EXCLUDED.user_id, pwa_installations.user_id),
                last_seen_at = NOW(),
                install_event_at = COALESCE(pwa_installations.install_event_at, EXCLUDED.install_event_at),
                last_standalone_at = COALESCE(EXCLUDED.last_standalone_at, pwa_installations.last_standalone_at),
                platform = EXCLUDED.platform, display_mode = EXCLUDED.display_mode, user_agent = EXCLUDED.user_agent");
    }

    $stmt->execute([
        ':id' => $installationId,
        ':uid' => $userId,
        ':install_at' => $event === 'install' ? date('Y-m-d H:i:s') : null,
        ':standalone_at' => $isStandalone ? date('Y-m-d H:i:s') : null,
        ':platform' => $platform !== '' ? $platform : null,
        ':display_mode' => $displayMode !== '' ? $displayMode : null,
        ':ua' => $userAgent !== '' ? $userAgent : null,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[pwa-install] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to record installation']);
}
