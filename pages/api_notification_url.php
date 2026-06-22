<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$type = sanitize($_GET['type'] ?? 'system');
$title = (string)($_GET['title'] ?? '');
$refId = (int)($_GET['ref'] ?? 0);

$url = notificationTargetUrl($pdo, [
    'type' => $type,
    'title' => $title,
    'reference_id' => $refId > 0 ? $refId : null,
], currentUserId());

echo json_encode([
    'success' => true,
    'url' => $url,
]);
