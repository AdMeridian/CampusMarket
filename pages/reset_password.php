<?php
// pages/reset_password.php
// Handles the Supabase password-recovery callback and lets the user set a new password.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

$errors      = [];
$success     = false;
$tokenHash   = trim((string)($_GET['token_hash'] ?? ''));
$accessToken = '';

// ── Step 1: Exchange token_hash for a session (GET request only) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($tokenHash !== '') {
        $verify = supabaseAuthRequest('POST', 'verify', [
            'token_hash' => $tokenHash,
            'type'       => 'recovery',
        ]);

        if (!$verify['ok']) {
            setFlash('error', __('auth.reset_flash_invalid'));
            redirect(BASE_URL . 'pages/forgot_password');
        }

        $accessToken = $verify['data']['access_token'] ?? '';
        if ($accessToken === '') {
            setFlash('error', __('auth.reset_flash_verify_failed'));
            redirect(BASE_URL . 'pages/forgot_password');
        }
    }
}

// ── Step 2: Handle the new-password form submission ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $newPassword     = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $postToken       = trim($_POST['access_token'] ?? '');

    if (strlen($newPassword) < 8) {
        $errors['password'] = __('auth.reset_error_length');
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = __('auth.reset_error_mismatch');
    } elseif ($postToken === '') {
        $errors['form'] = __('auth.reset_error_session');
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

        if ($status >= 200 && $status < 300) {
            // Fetch email from Supabase using the access token to safely update the local DB
            $userEmail = '';
            $chUser = curl_init($url);
            curl_setopt_array($chUser, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'apikey: '        . supabaseAnonKey(),
                    'Authorization: Bearer ' . $postToken,
                    'Content-Type: application/json',
                ],
            ]);
            $userBody = curl_exec($chUser);
            $userStatus = (int) curl_getinfo($chUser, CURLINFO_HTTP_CODE);

            if ($userStatus >= 200 && $userStatus < 300) {
                $userDecoded = json_decode((string)$userBody, true);
                $userEmail = strtolower(trim((string)($userDecoded['email'] ?? '')));
            }

            // Also update the password in our own users table (bcrypt using password_hash column)
            if ($userEmail !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd  = $pdo->prepare('UPDATE users SET password_hash = :p WHERE LOWER(email) = LOWER(:e)');
                $upd->execute([':p' => $hash, ':e' => $userEmail]);
            }
            $success = true;
        } else {
            $decoded = json_decode((string)$body, true);
            $errors['form'] = __('auth.reset_error_update', ['error' => $decoded['msg'] ?? $decoded['message'] ?? 'Unknown error. Please try again.']);
        }
    }

    // Keep the access token available for the form even after a failed submit
    $accessToken = $postToken ?: $accessToken;
}

$hasToken = ($accessToken !== '');
$pageTitle = __('auth.reset_page_title');
require_once '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <?php if ($success): ?>
                <div style="width: 64px; height: 64px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; animation: pop .5s cubic-bezier(.34,1.56,.64,1);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:32px;height:32px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h1><?= __('auth.reset_success_title') ?></h1>
                <p><?= __('auth.reset_success_body') ?></p>
            <?php else: ?>
                <div style="width: 64px; height: 64px; background: rgba(99,102,241,0.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:30px;height:30px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <h1><?= __('auth.reset_form_title') ?></h1>
                <p><?= __('auth.reset_form_subtitle') ?></p>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <a href="<?= BASE_URL ?>pages/login" class="btn btn-primary w-full py-4 shadow-sm"
               style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; display: block; text-align: center;">
                <?= __('auth.reset_login_now') ?>
            </a>
        <?php else: ?>
            <div id="reset-loading-container" style="<?= $hasToken ? 'display: none;' : '' ?> text-align: center; padding: 2rem 0;">
                <div class="spinner mb-4" style="border: 4px solid rgba(99,102,241,0.1); border-top-color: var(--primary); border-radius: 50%; width: 40px; height: 40px; margin: 0 auto; animation: spin 1s linear infinite;"></div>
                <p style="color: var(--text-muted);"><?= __('auth.reset_verifying') ?></p>
            </div>

            <div id="reset-error-container" style="display: none;">
                <div class="flash flash-error mb-6"><?= __('auth.reset_link_invalid') ?></div>
                <a href="<?= BASE_URL ?>pages/forgot_password" class="btn btn-primary w-full py-4 text-center" style="display: block;"><?= __('auth.reset_request_new') ?></a>
            </div>

            <!-- Form container -->
            <div id="reset-form-container" style="<?= $hasToken ? '' : 'display: none;' ?>">
                <?php if (!empty($errors['form'])): ?>
                    <div class="flash flash-error mb-6"><?= sanitize($errors['form']) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?php echo csrfTokenField(); ?>
                    <!-- Pass the Supabase access token through a hidden field -->
                    <input type="hidden" name="access_token" id="access_token_input" value="<?= htmlspecialchars($accessToken) ?>">

                    <div class="form-row mb-5">
                        <label for="password" class="form-label"><?= __('auth.reset_new_password') ?></label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password"
                                   class="premium-input w-full" required autocomplete="new-password"
                                   placeholder="<?= addslashes(__('auth.register_password_placeholder')) ?>">
                            <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePw('password', this)">
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="text-sm mt-1" style="color: var(--error);"><?= sanitize($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row mb-6">
                        <label for="confirm_password" class="form-label"><?= __('auth.reset_confirm_password') ?></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="premium-input w-full" required autocomplete="new-password"
                                   placeholder="<?= addslashes(__('auth.reset_confirm_placeholder')) ?>">
                            <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePw('confirm_password', this)">
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="text-sm mt-1" style="color: var(--error);"><?= sanitize($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-full py-4 shadow-sm"
                            style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem;">
                        <?= __('auth.reset_submit') ?>
                    </button>
                </form>

                <p class="auth-foot mt-8">
                    <?= __('auth.reset_remembered') ?> <a href="<?= BASE_URL ?>pages/login" style="font-weight: 600;"><?= __('auth.login_btn') ?></a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        const hasTokenInPhp = <?php echo $hasToken ? 'true' : 'false'; ?>;
        if (hasTokenInPhp) return; // Server already verified token_hash

        // Try to parse the access token from the URL hash fragment
        const hash = window.location.hash;
        if (hash && (hash.includes('access_token=') || hash.includes('type=recovery'))) {
            const params = new URLSearchParams(hash.replace(/^#/, ''));
            const token = params.get('access_token');
            if (token) {
                // Set token in the hidden form field
                document.getElementById('access_token_input').value = token;
                // Transition UI state
                document.getElementById('reset-loading-container').style.display = 'none';
                document.getElementById('reset-form-container').style.display = 'block';
                return;
            }
        }

        // If no token was found in PHP or in the hash, transition to error state
        document.getElementById('reset-loading-container').style.display = 'none';
        document.getElementById('reset-error-container').style.display = 'block';
    })();
</script>

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper .premium-input {
    padding-right: 2.75rem;
}
.pw-toggle {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
    line-height: 1;
}
.pw-toggle:hover {
    color: var(--primary, #6366f1);
}
.eye-icon {
    width: 18px;
    height: 18px;
    pointer-events: none;
}
</style>

<script>
function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('.eye-open').style.display  = isHidden ? 'none'  : '';
    btn.querySelector('.eye-closed').style.display = isHidden ? ''     : 'none';
    btn.setAttribute('aria-label', isHidden ? <?= json_encode(__('auth.hide_password')) ?> : <?= json_encode(__('auth.show_password')) ?>);
}
</script>

<?php require_once '../includes/footer.php'; ?>
