<?php
// pages/reset_password.php
// Handles the Supabase password-recovery callback and lets the user set a new password.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

$errors   = [];
$success  = false;
$tokenHash = trim((string)($_GET['token_hash'] ?? ''));
$accessToken = ''; // Supabase access_token exchanged from the recovery hash

// ── Step 1: Exchange token_hash for a session ──────────────────────────────
if ($tokenHash === '') {
    setFlash('error', 'Invalid or missing password reset link. Please request a new one.');
    redirect(BASE_URL . 'pages/forgot_password.php');
}

$verify = supabaseAuthRequest('POST', 'verify', [
    'token_hash' => $tokenHash,
    'type'       => 'recovery',
]);

if (!$verify['ok']) {
    setFlash('error', 'This password reset link is invalid or has expired. Please request a new one.');
    redirect(BASE_URL . 'pages/forgot_password.php');
}

$accessToken = $verify['data']['access_token'] ?? '';
$userEmail   = strtolower(trim((string)($verify['data']['user']['email'] ?? '')));

if ($accessToken === '') {
    setFlash('error', 'Could not verify reset link. Please request a new one.');
    redirect(BASE_URL . 'pages/forgot_password.php');
}

// ── Step 2: Handle the new-password form submission ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $newPassword     = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $postToken       = trim($_POST['access_token'] ?? '');

    if (strlen($newPassword) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    } elseif ($postToken === '') {
        $errors['form'] = 'Session expired. Please request a new reset link.';
    }

    if (empty($errors)) {
        // Call Supabase user update endpoint with the access token
        $url = rtrim(supabaseUrl(), '/') . '/auth/v1/user';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_HTTPHEADER     => [
                'apikey: '        . supabaseAnonKey(),
                'Authorization: Bearer ' . $postToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['password' => $newPassword]),
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            // Also update the password in our own users table (bcrypt)
            if ($userEmail !== '') {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $upd  = $pdo->prepare('UPDATE users SET password = :p WHERE LOWER(email) = LOWER(:e)');
                $upd->execute([':p' => $hash, ':e' => $userEmail]);
            }
            $success = true;
        } else {
            $decoded = json_decode((string)$body, true);
            $errors['form'] = 'Failed to update password: ' . ($decoded['msg'] ?? $decoded['message'] ?? 'Unknown error. Please try again.');
        }
    }

    // Keep the access token available for the form even after a failed submit
    $accessToken = $postToken ?: $accessToken;
}

$pageTitle = 'Reset Password';
require_once '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <?php if ($success): ?>
                <div style="width: 64px; height: 64px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; animation: pop .5s cubic-bezier(.34,1.56,.64,1);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:32px;height:32px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h1>Password updated!</h1>
                <p>Your password has been changed successfully. You can now log in with your new password.</p>
            <?php else: ?>
                <div style="width: 64px; height: 64px; background: rgba(99,102,241,0.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:30px;height:30px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <h1>Set new password</h1>
                <p>Choose a strong password for your CampusMarket account.</p>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-primary w-full py-4 shadow-sm"
               style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; display: block; text-align: center;">
                Log in now
            </a>
        <?php else: ?>
            <?php if (!empty($errors['form'])): ?>
                <div class="flash flash-error mb-6"><?= sanitize($errors['form']) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?php echo csrfTokenField(); ?>
                <!-- Pass the Supabase access token through a hidden field -->
                <input type="hidden" name="access_token" value="<?= htmlspecialchars($accessToken) ?>">

                <div class="form-row mb-5">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" id="password" name="password"
                           class="premium-input w-full" required autocomplete="new-password"
                           placeholder="At least 8 characters">
                    <?php if (!empty($errors['password'])): ?>
                        <div class="text-sm mt-1" style="color: var(--error);"><?= sanitize($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row mb-6">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="premium-input w-full" required autocomplete="new-password"
                           placeholder="Re-enter your new password">
                    <?php if (!empty($errors['confirm_password'])): ?>
                        <div class="text-sm mt-1" style="color: var(--error);"><?= sanitize($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-full py-4 shadow-sm"
                        style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem;">
                    Update Password
                </button>
            </form>

            <p class="auth-foot mt-8">
                Remembered it? <a href="<?= BASE_URL ?>pages/login.php" style="font-weight: 600;">Log in</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
@keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>

<?php require_once '../includes/footer.php'; ?>
