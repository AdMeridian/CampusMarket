<?php
// pages/stripe_success.php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stripe_fulfillment.php';
requireLogin();

$sessionId = $_GET['session_id'] ?? '';
$paymentType = sanitize($_GET['type'] ?? 'promotion');
$fallbackProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$redirectPath = ($paymentType === 'donation') ? 'pages/donate.php' : (($fallbackProductId > 0) ? 'pages/product.php?id=' . $fallbackProductId : 'pages/promotions.php');

if (empty($sessionId)) {
    redirect(BASE_URL . $redirectPath);
}

$session = fetchStripeCheckoutSession($sessionId);
if (!$session) {
    setFlash('error', 'Could not verify payment with Stripe.');
    redirect(BASE_URL . $redirectPath);
}

$meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
$sessionUserId = (int) ($meta['user_id'] ?? 0);
if ($sessionUserId !== currentUserId()) {
    setFlash('error', 'This payment session does not belong to your account.');
    redirect(BASE_URL . $redirectPath);
}

$result = fulfillStripeCheckoutSession($pdo, $session);
$paymentType = sanitize((string) ($meta['payment_type'] ?? $paymentType));
$productId = !empty($meta['product_id']) ? (int) $meta['product_id'] : null;

if ($paymentType === 'promotion' && $productId) {
    $redirectPath = 'pages/product.php?id=' . $productId;
} elseif ($paymentType === 'donation') {
    $redirectPath = 'pages/donate.php';
}

if ($result['ok']) {
    if (!empty($result['already_processed'])) {
        setFlash('info', 'This payment was already processed.');
    } else {
        setFlash(
            'success',
            'Payment successful! Your ' . ($paymentType === 'promotion' ? 'listing is now featured.' : 'donation has been received. Thank you!')
        );
    }
} else {
    setFlash('error', 'Payment confirmed but database update failed. Please contact support with Session ID: ' . sanitize($sessionId));
}

redirect(BASE_URL . $redirectPath);
