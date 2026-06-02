<?php
// pages/verify_email.php
// Handles Supabase email confirmation callback and keeps a legacy token fallback.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

if (($_GET['source'] ?? '') === 'supabase') {
    $tokenHash = trim((string)($_GET['token_hash'] ?? ''));
    $type = trim((string)($_GET['type'] ?? 'email'));

    // Password recovery links must go to the reset password page, not here.
    if ($type === 'recovery') {
        if ($tokenHash === '') {
            setFlash('error', 'Invalid password reset link. Please request a new one.');
            redirect(BASE_URL . 'pages/forgot_password.php');
        }
        redirect(BASE_URL . 'pages/reset_password.php?token_hash=' . urlencode($tokenHash));
    }

    if ($tokenHash === '') {
        setFlash('error', 'Invalid verification link.');
        redirect(BASE_URL . 'pages/login.php');
    }

    $verify = supabaseAuthRequest('POST', 'verify', [
        'token_hash' => $tokenHash,
        'type' => $type,
    ]);

    if (!$verify['ok']) {
        setFlash('error', 'Verification failed or link expired. Please request a new verification email.');
        redirect(BASE_URL . 'pages/login.php');
    }

    $supabaseUser = $verify['data']['user'] ?? [];
    $verifiedEmail = strtolower(trim((string)($supabaseUser['email'] ?? '')));

    if ($verifiedEmail !== '') {
        $upd = $pdo->prepare('UPDATE users SET is_verified = TRUE WHERE LOWER(email) = LOWER(:e)');
        $upd->execute([':e' => $verifiedEmail]);
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verified - CampusMarket</title>
        <style>
            body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; color: #0f172a; }
            .card { max-width: 480px; width: 100%; background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(0,0,0,.10); padding: 52px 44px; text-align: center; }
            .icon { width: 80px; height: 80px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; animation: pop .5s cubic-bezier(.34,1.56,.64,1); }
            .icon svg { width: 40px; height: 40px; color: #16a34a; }
            @keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            h1 { margin: 0 0 10px; font-size: 1.75rem; font-weight: 700; }
            .subtitle { margin: 0 0 28px; color: #475569; font-size: 1rem; line-height: 1.65; }
            .instruction { background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 18px 22px; margin-bottom: 28px; color: #15803d; font-size: 0.95rem; line-height: 1.6; text-align: left; }
            .instruction strong { display: block; margin-bottom: 4px; font-size: 1rem; }
            .btn-close { display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 10px; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; transition: background .18s, transform .15s; }
            .btn-close:hover { background: #4338ca; transform: translateY(-1px); }
            .divider { margin: 22px 0; color: #cbd5e1; font-size: 0.85rem; }
            .login-link { color: #4f46e5; text-decoration: none; font-size: 0.9rem; }
            .login-link:hover { text-decoration: underline; }
            .brand { font-size: 0.8rem; color: #94a3b8; margin-top: 32px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h1>Email verified!</h1>
            <p class="subtitle">Your CampusMarket account is now active and ready to use.</p>
            <div class="instruction">
                <strong>✅ You're all set!</strong>
                Close this tab and go back to the CampusMarket tab where you signed up, then log in with your email and password.
            </div>
            <button class="btn-close" onclick="window.close()">Close this tab</button>
            <div class="divider">— or —</div>
            <a href="<?php echo BASE_URL; ?>pages/login.php?redirect=/pages/profile.php" class="login-link">Log in here instead</a>
            <p class="brand">CampusMarket · NEU</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

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
redirect(BASE_URL . 'pages/login.php?redirect=/pages/profile.php');


