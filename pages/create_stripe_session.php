<?php
// pages/create_stripe_session.php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stripe_fulfillment.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'pages/promotions.php');
}

verifyCsrfToken();

$paymentType = sanitize($_POST['payment_type'] ?? '');
$productId   = (int)($_POST['product_id'] ?? 0);
$postedAmount = (float)($_POST['amount'] ?? 0);
$promotionDays = 0;

$redirectPath = ($paymentType === 'donation') ? 'pages/donate.php' : 'pages/promotions.php';

if (!in_array($paymentType, ['promotion', 'donation'], true)) {
    setFlash('error', 'Invalid payment details.');
    redirect(BASE_URL . $redirectPath);
}

if ($paymentType === 'promotion') {
    $resolved = resolvePromotionPayment($postedAmount);
    if ($resolved === null) {
        setFlash('error', 'Invalid promotion amount. Choose ₺50, ₺100, ₺200, or a custom amount of at least ₺15.');
        redirect(BASE_URL . 'pages/promotions.php');
    }
    $amount = $resolved['amount'];
    $promotionDays = $resolved['days'];
} else {
    $donationAmount = resolveDonationPayment($postedAmount);
    if ($donationAmount === null) {
        setFlash('error', 'Invalid donation amount.');
        redirect(BASE_URL . 'pages/donate.php');
    }
    $amount = $donationAmount;
}

if ($paymentType === 'promotion' && $productId <= 0) {
    setFlash('error', 'Please select a listing to promote.');
    redirect(BASE_URL . 'pages/promotions.php');
}

if ($paymentType === 'promotion') {
    $ownActiveCheck = $pdo->prepare("SELECT id FROM products WHERE id = :pid AND user_id = :uid AND status = 'active'");
    $ownActiveCheck->execute([':pid' => $productId, ':uid' => currentUserId()]);
    if (!$ownActiveCheck->fetchColumn()) {
        setFlash('error', 'Only active listings can be promoted.');
        redirect(BASE_URL . 'pages/promotions.php');
    }
}

$unitAmount = (int) round($amount * 100);

$ch = curl_init();

$successUrl = BASE_URL . 'pages/stripe_success.php?session_id={CHECKOUT_SESSION_ID}&type=' . $paymentType;
if ($paymentType === 'promotion' && $productId > 0) {
    $successUrl .= '&product_id=' . $productId;
}
$cancelUrl  = BASE_URL . (($paymentType === 'donation') ? 'pages/donate.php' : 'pages/promotions.php');

$postFields = [
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
    'mode'        => 'payment',
    'line_items[0][price_data][currency]' => 'try',
    'line_items[0][price_data][product_data][name]' => ($paymentType === 'promotion' ? 'Product Promotion' : 'CampusMarket Donation'),
    'line_items[0][price_data][unit_amount]' => $unitAmount,
    'line_items[0][quantity]' => 1,
    'metadata[user_id]' => currentUserId(),
    'metadata[product_id]' => $productId,
    'metadata[payment_type]' => $paymentType,
    'metadata[amount]' => $amount,
    'metadata[promotion_days]' => $promotionDays,
];

curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$response = json_decode($result, true);

if ($httpCode === 200 && isset($response['url'])) {
    header('Location: ' . $response['url']);
    exit;
}

$errorMsg = $response['error']['message'] ?? 'Stripe communication error.';
setFlash('error', 'Could not initiate Stripe session: ' . $errorMsg);
redirect(BASE_URL . $redirectPath);
