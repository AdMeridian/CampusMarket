<?php
// pages/verify_email.php — Member 2
// Consumes the ?token=... param from the email, marks the user verified,
// and deletes the token. All cases redirect to login with a flash message.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

$token = trim($_GET['token'] ?? '');

// Reject obviously malformed tokens before touching the DB.
if ($token === '' || !preg_match('/^[a-f0-9]{40,128}$/i', $token)) {
    setFlash('error', 'Invalid verification link.');
    redirect(BASE_URL . 'pages/login.php');
}

$stmt = $pdo->prepare('
    SELECT v.id, v.user_id, v.expires_at, u.is_verified, u.email
    FROM email_verifications v
    JOIN users u ON u.id = v.user_id
    WHERE v.token = :t
    LIMIT 1
');
$stmt->execute([':t' => $token]);
$row = $stmt->fetch();

if (!$row) {
    setFlash('error', 'This verification link is invalid or has already been used.');
    redirect(BASE_URL . 'pages/login.php');
}

// Already-verified accounts get a friendly message instead of a confusing error.
if ((int) $row['is_verified'] === 1) {
    $del = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = :u');
    $del->execute([':u' => $row['user_id']]);
    setFlash('success', 'Your email is already verified. Please log in.');
    redirect(BASE_URL . 'pages/login.php');
}

// Expired tokens — clean up and tell the user.
if (strtotime($row['expires_at']) < time()) {
    $del = $pdo->prepare('DELETE FROM email_verifications WHERE id = :id');
    $del->execute([':id' => $row['id']]);
    setFlash('error', 'This verification link has expired. Please register again to receive a new one.');
    redirect(BASE_URL . 'pages/login.php');
}

// Happy path — mark verified + nuke any tokens for this user.
try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare('UPDATE users SET is_verified = TRUE WHERE id = :id');
    $upd->execute([':id' => $row['user_id']]);

    $del = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = :u');
    $del->execute([':u' => $row['user_id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[verify_email] DB error: ' . $e->getMessage());
    setFlash('error', 'Something went wrong while verifying your email. Please try again.');
    redirect(BASE_URL . 'pages/login.php');
}

setFlash('success', 'Email verified! You can now log in.');
redirect(BASE_URL . 'pages/login.php');
