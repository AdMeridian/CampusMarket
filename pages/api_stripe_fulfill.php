<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stripe_fulfillment.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$internalKey = getenv('INTERNAL_PUSH_KEY') ?: '';
$providedKey = trim((string) ($_SERVER['HTTP_X_INTERNAL_PUSH_KEY'] ?? ''));
if ($internalKey === '' || !hash_equals($internalKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$sessionId = trim((string) ($payload['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'session_id is required']);
    exit;
}

$session = fetchStripeCheckoutSession($sessionId);
if (!$session) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Stripe session not found']);
    exit;
}

$result = fulfillStripeCheckoutSession($pdo, $session);
http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result);
