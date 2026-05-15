<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$uid = currentUserId();

$unreadMessages = countUnreadMessages($pdo, $uid);
$unreadNotifs = countUnreadNotifications($pdo, $uid);

echo json_encode([
    'success' => true,
    'unreadMessages' => $unreadMessages,
    'unreadNotifs' => $unreadNotifs
]);
