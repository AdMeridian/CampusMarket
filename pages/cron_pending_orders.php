<?php
/**
 * Daily job: remind sellers before pending orders expire, then auto-cancel stale requests.
 * Secured via CRON_SECRET Bearer token.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/account_cleanup.php';
require_once __DIR__ . '/../includes/order_expiry.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthorizedCronRequest()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $result = processPendingOrderExpiry($pdo);
    echo json_encode(['ok' => true] + $result);
} catch (Throwable $e) {
    error_log('[cron_pending_orders] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Pending order expiry failed']);
}
