<?php
// pages/stripe_success.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$sessionId = $_GET['session_id'] ?? '';

if (empty($sessionId)) {
    redirect(BASE_URL . 'pages/promotions.php');
}

// Verify the session with Stripe via cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions/' . $sessionId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response = json_decode($result, true);

if ($httpCode === 200 && $response['payment_status'] === 'paid') {
    $meta = $response['metadata'];
    $userId      = (int)$meta['user_id'];
    $productId   = !empty($meta['product_id']) ? (int)$meta['product_id'] : null;
    $paymentType = sanitize($meta['payment_type']);
    $amount      = (float)$meta['amount'];
    
    // Check if this session was already processed to prevent duplicates
    $check = $pdo->prepare('SELECT id FROM promotion_payments WHERE transaction_ref = ?');
    $check->execute([$sessionId]);
    
    if (!$check->fetch()) {
        $pdo->beginTransaction();
        try {
            // Insert approved payment
            $ins = $pdo->prepare('
                INSERT INTO promotion_payments 
                    (user_id, product_id, payment_type, payment_method, amount, transaction_ref, status, approved_at, notes)
                VALUES 
                    (:uid, :pid, :ptype, 'stripe', :amount, :tx, 'approved', NOW(), 'Automated Stripe Sandbox Payment')
            ');
            $ins->execute([
                ':uid'    => $userId,
                ':pid'    => $productId,
                ':ptype'  => $paymentType,
                ':amount' => $amount,
                ':tx'     => $sessionId
            ]);
            
            // If it's a promotion, feature the product immediately
            if ($paymentType === 'promotion' && $productId) {
                $upd = $pdo->prepare('UPDATE products SET is_featured = TRUE, discount_set_at = NOW() WHERE id = ?');
                $upd->execute([$productId]);
            }
            
            $pdo->commit();
            setFlash('success', 'Payment successful! Your ' . ($paymentType === 'promotion' ? 'listing is now featured.' : 'donation has been received. Thank you!'));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Payment confirmed but database update failed. Please contact support with Session ID: ' . $sessionId);
        }
    } else {
        setFlash('info', 'This payment was already processed.');
    }
} else {
    setFlash('error', 'Could not verify payment with Stripe.');
}

redirect(BASE_URL . 'pages/promotions.php');
