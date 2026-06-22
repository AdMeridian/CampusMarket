<?php
// pages/login.php — Member 2
// Authenticate via Supabase Auth using email-or-username + password.
// Keeps local app sessions/users table for marketplace data.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';

if (isLoggedIn()) {
    redirect(BASE_URL);
}

$errors    = [];
$identity  = '';
$unverified = false;
$unverifiedEmail = '';
$resendMessage = '';
$resendError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();

    if (($_POST['action'] ?? '') === 'resend_verification') {
        $resendEmail = trim(strtolower($_POST['resend_email'] ?? ''));
        if ($resendEmail === '' || !filter_var($resendEmail, FILTER_VALIDATE_EMAIL)) {
            $resendError = __('auth.resend_invalid_email');
        } else {
            $result = resendSignupVerificationEmail($resendEmail);
            if ($result['ok']) {
                $resendMessage = $result['message'] ?? 'Verification email sent.';
                $unverified = true;
                $unverifiedEmail = $resendEmail;
                $identity = $resendEmail;
            } else {
                $resendError = $result['error'] ?? 'Could not resend verification email.';
                $unverifiedEmail = $resendEmail;
                $identity = $resendEmail;
            }
        }
    } else {

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        $errors['form'] = __('auth.error_credentials');
    } else {
        $loginEmail = $identity;
        if (!filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
            $lookup = $pdo->prepare('SELECT email FROM users WHERE username = :u LIMIT 1');
            $lookup->execute([':u' => $identity]);
            $foundEmail = $lookup->fetchColumn();
            if (is_string($foundEmail) && $foundEmail !== '') {
                $loginEmail = $foundEmail;
            }
        }

        $auth = supabaseAuthRequest('POST', 'token?grant_type=password', [
            'email' => $loginEmail,
            'password' => $password,
        ]);

        if (!$auth['ok']) {
            $msg = strtolower((string) ($auth['error'] ?? ''));
            if (strpos($msg, 'email not confirmed') !== false) {
                $unverified = true;
                $unverifiedEmail = strtolower($loginEmail);
                $errors['form'] = __('auth.error_verify_email');
            } else {
                if (!isSupabaseConfigured()) {
                    // Legacy local auth only when Supabase is not configured (local dev).
                    $stmt = $pdo->prepare('SELECT id, password_hash, is_verified FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                    $stmt->execute([':email' => $loginEmail]);
                    $localUser = $stmt->fetch();
                    if ($localUser && password_verify($password, $localUser['password_hash'])) {
                        $auth['ok'] = true;
                        $auth['data']['user'] = [
                            'email' => $loginEmail,
                            'email_confirmed_at' => $localUser['is_verified'] ? date('Y-m-d H:i:s') : null
                        ];
                    } else {
                        $errors['form'] = __('auth.error_invalid');
                    }
                } else {
                    $errors['form'] = __('auth.error_invalid');
                }
            }
        }
        
        if ($auth['ok']) {
            $authUser = $auth['data']['user'] ?? [];
            $authEmail = strtolower((string) ($authUser['email'] ?? $loginEmail));
            $isVerified = !empty($authUser['email_confirmed_at']) ? 1 : 0;

            $stmt = $pdo->prepare('
                SELECT id, username, email, role, is_verified, phone
                FROM users
                WHERE LOWER(email) = LOWER(:email)
                LIMIT 1
            ');
            $stmt->execute([':email' => $authEmail]);
            $user = $stmt->fetch();

            if (!$user) {
                $baseUsername = preg_replace('/[^A-Za-z0-9_]/', '_', strstr($authEmail, '@', true) ?: 'user');
                $baseUsername = trim((string) $baseUsername, '_');
                if ($baseUsername === '') {
                    $baseUsername = 'user';
                }
                $candidate = substr($baseUsername, 0, 40);
                $suffix = 0;
                while (true) {
                    $tryUsername = $suffix > 0 ? substr($candidate, 0, 35) . '_' . $suffix : $candidate;
                    $exists = $pdo->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
                    $exists->execute([':u' => $tryUsername]);
                    if (!$exists->fetchColumn()) {
                        break;
                    }
                    $suffix++;
                }

                $ins = $pdo->prepare("
                    INSERT INTO users (username, email, student_id, password_hash, role, phone, is_verified)
                    VALUES (:u, :e, :s, :h, 'user', :p, :v)
                ");
                $studentId = studentIdFromUniversityEmail($authEmail);
                $ins->execute([
                    ':u' => $tryUsername,
                    ':e' => $authEmail,
                    ':s' => $studentId,
                    ':h' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    ':p' => null,
                    ':v' => $isVerified,
                ]);
                $stmt->execute([':email' => $authEmail]);
                $user = $stmt->fetch();
            }

            if (!$user) {
                $errors['form'] = __('auth.error_profile_init');
            } elseif ($isVerified !== 1) {
                $unverified = true;
                $unverifiedEmail = $authEmail;
                $errors['form'] = __('auth.error_verify_email');
            } elseif (isUserSuspended($pdo, (int)$user['id'])) {
                $errors['form'] = __('auth.error_suspended');
            } else {
                if ((int) $user['is_verified'] !== 1) {
                    $upd = $pdo->prepare('UPDATE users SET is_verified = TRUE WHERE id = :id');
                    $upd->execute([':id' => $user['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['supabase_access_token'] = (string) ($auth['data']['access_token'] ?? '');
                $_SESSION['supabase_refresh_token'] = (string) ($auth['data']['refresh_token'] ?? '');

                require_once __DIR__ . '/../includes/web_push.php';
                syncSupabaseAppUserMetadata($pdo, (int)$user['id'], (string)$user['role']);

                $_SESSION['prompt_push'] = true;
                $_SESSION['posthog_event'] = ['name' => 'user_logged_in', 'properties' => []];
                setFlash('success', __('auth.welcome_back', ['username' => sanitize($user['username'])]));

                $target = $_GET['redirect'] ?? '';
                if ($target && strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
                    redirect(BASE_URL . ltrim($target, '/'));
                }
                redirect(BASE_URL);
            }
        }
    }
    }
}

$pageTitle = __('auth.login_page_title');
require_once '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <h1><?= __('auth.login_title') ?></h1>
            <p><?= __('auth.login_subtitle') ?></p>
        </div>

        <?php if (!empty($errors['form'])): ?>
            <div class="flash <?php echo $unverified ? 'flash-warning' : 'flash-error'; ?> mb-8">
                <?php echo sanitize($errors['form']); ?>
            </div>
        <?php endif; ?>

        <?php if ($resendMessage !== ''): ?>
            <div class="flash flash-success mb-8"><?php echo sanitize($resendMessage); ?></div>
        <?php endif; ?>
        <?php if ($resendError !== ''): ?>
            <div class="flash flash-error mb-8"><?php echo sanitize($resendError); ?></div>
        <?php endif; ?>

        <?php if ($unverified && $unverifiedEmail !== ''): ?>
            <form method="post" class="mb-8 verify-resend-box">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="action" value="resend_verification">
                <input type="hidden" name="resend_email" value="<?php echo sanitize($unverifiedEmail); ?>">
                <p class="hint mb-3"><?= __('auth.resend_hint', ['email' => '<strong>' . sanitize($unverifiedEmail) . '</strong>']) ?></p>
                <button type="submit" class="btn btn-secondary w-full py-3" style="border-radius: var(--radius-md); font-weight: 600;">
                    <?= __('auth.resend_btn') ?>
                </button>
            </form>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="action" value="login">
            <?php echo csrfTokenField(); ?>
            <div class="form-row mb-6">
                <label for="identity" class="form-label"><?= __('auth.email_or_username') ?></label>
                <input type="text" id="identity" name="identity"
                       value="<?php echo sanitize($identity); ?>"
                       placeholder="20227014@ciu.edu.tr"
                       class="premium-input w-full"
                       required autofocus autocomplete="username">
            </div>

            <div class="form-row mb-8">
                <div class="flex justify-between items-center mb-1.5">
                    <label for="password" class="form-label"><?= __('auth.password') ?></label>
                    <a href="<?php echo BASE_URL; ?>pages/forgot_password.php" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);"><?= __('auth.forgot_password') ?></a>
                </div>
                <div class="input-with-toggle">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           class="premium-input w-full"
                           required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="password" aria-label="<?= htmlspecialchars(__('auth.show_password')) ?>" style="right: 12px;">
                        <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0112 19c-6.5 0-10-7-10-7a19.8 19.8 0 015.06-5.94M9.9 4.24A10.94 10.94 0 0112 4c6.5 0 10 7 10 7a19.9 19.9 0 01-3.17 4.19M9.88 9.88a3 3 0 104.24 4.24M1 1l22 22"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full py-4 shadow-sm" style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; letter-spacing: 0.01em;"><?= __('auth.login_btn') ?></button>
        </form>

        <p class="auth-foot mt-10">
            <?= __('auth.new_here') ?> 
            <a href="<?php echo BASE_URL; ?>pages/register.php" style="font-weight: 600;"><?= __('auth.create_account_link') ?></a>
        </p>
    </div>
</div>

<script>
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.classList.toggle('is-shown', show);
      btn.setAttribute('aria-label', show ? <?= json_encode(__('auth.hide_password')) ?> : <?= json_encode(__('auth.show_password')) ?>);
    });
  });
</script>

<?php require_once '../includes/footer.php'; ?>
