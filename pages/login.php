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
            $lookup = $pdo->prepare('SELECT email FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
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

        <?php if (isSupabaseConfigured()): ?>
            <div class="social-auth-stack mb-6" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="<?php echo htmlspecialchars(supabaseOAuthUrl('google')); ?>" class="btn w-full flex items-center justify-center gap-3 py-3" style="background: #ffffff; color: #1f2937; border: 1.5px solid var(--border-light, #e5e7eb); border-radius: var(--radius-md); font-weight: 600; font-size: 0.95rem; text-decoration: none; box-shadow: var(--shadow-sm); transition: transform 0.15s, box-shadow 0.15s;">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                    </svg>
                    Continue with Google
                </a>
                <a href="<?php echo htmlspecialchars(supabaseOAuthUrl('apple')); ?>" class="btn w-full flex items-center justify-center gap-3 py-3" style="background: #000000; color: #ffffff; border-radius: var(--radius-md); font-weight: 600; font-size: 0.95rem; text-decoration: none; box-shadow: var(--shadow-sm); transition: transform 0.15s, box-shadow 0.15s;">
                    <svg width="18" height="18" viewBox="0 0 170 170" fill="currentColor">
                        <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.7-3.04-7.69-7.79-11.97-14.24-6.74-10.12-12.04-21.46-15.89-34.02-3.85-12.56-5.78-24.32-5.78-35.29 0-14.68 3.64-26.79 10.92-36.32 7.28-9.53 16.48-14.45 27.6-14.75 4.9.11 10.1 1.25 15.61 3.42 5.51 2.18 9.38 3.33 11.62 3.46 1.85-.23 5.86-1.48 12.04-3.76 6.18-2.28 11.51-3.23 16-2.85 13.92 1.19 24.81 6.55 32.68 16.07-12.18 7.39-18.15 17.51-17.9 30.34.25 10.23 4.11 18.79 11.58 25.68 7.47 6.89 16.29 10.74 26.46 11.55-2.28 6.86-4.89 13.78-7.82 20.76zM119.22 33.02c0-7.39 2.66-14.47 7.98-21.24 5.32-6.77 11.93-11.08 19.83-12.93.98 7.82-1.39 15.22-7.1 22.2-5.72 6.98-12.63 11.23-20.71 12.76v-.79z"/>
                    </svg>
                    Sign in with Apple
                </a>
            </div>

            <div class="auth-divider flex items-center gap-3 my-6" style="color: var(--text-muted); font-size: 0.82rem; font-weight: 600; text-transform: uppercase;">
                <span style="flex: 1; height: 1px; background: var(--border-light, #e5e7eb);"></span>
                <span>or continue with email</span>
                <span style="flex: 1; height: 1px; background: var(--border-light, #e5e7eb);"></span>
            </div>
        <?php endif; ?>

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

        <form method="post" novalidate class="js-form-loading" data-loading-text="<?= htmlspecialchars(__('auth.login_submitting'), ENT_QUOTES, 'UTF-8') ?>">
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
