<?php
// pages/register.php — Member 2
// Create a CampusMarket account with a verified university email
// and email verification via Supabase Auth. No auto-login: user must verify
// before they can sign in.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'pages/profile.php');
}

$errors = [];
$old    = [
    'username' => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $old['username'] = $username;
    $old['email']    = $email;
    $old['phone']    = $phone;

    if ($username === '') {
        $errors['username'] = __('auth.register_error_username_required');
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = __('auth.register_error_username_format');
    }

    if ($email === '') {
        $errors['email'] = __('auth.register_error_email_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors['email'] = __('auth.register_error_email_invalid');
    } elseif (function_exists('isDisposableEmailDomain') && isDisposableEmailDomain(substr($email, strrpos($email, '@') + 1))) {
        $errors['email'] = 'Disposable/temporary email addresses are not allowed. Please use your campus email or personal email (e.g. Gmail).';
    } elseif (!isAllowedUniversityEmail($email)) {
        $errors['email'] = 'Please enter a valid university email or trusted email provider (such as Gmail, Outlook).';
    } elseif (($studentEmailError = validateUniversityStudentEmail($email)) !== null) {
        $errors['email'] = $studentEmailError;
    }

    if ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone)) {
        $errors['phone'] = __('auth.register_error_phone');
    }

    if (strlen($password) < 8) {
        $errors['password'] = __('auth.register_error_password_length');
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = __('auth.register_error_password_format');
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = __('auth.register_error_password_mismatch');
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = __('auth.register_terms_error');
    }

    if (!$errors) {
        $stmt = $pdo->prepare('
            SELECT username, email FROM users
            WHERE username = :u OR LOWER(email) = LOWER(:e)
            LIMIT 1
        ');
        $stmt->execute([':u' => $username, ':e' => $email]);
        $dup = $stmt->fetch();
        if ($dup) {
            if (strcasecmp($dup['username'], $username) === 0) {
                $errors['username'] = __('auth.register_error_username_taken');
            }
            if (strcasecmp($dup['email'], $email) === 0) {
                $errors['email'] = __('auth.register_error_email_exists');
            }
        }
    }

    if (!$errors) {
        $emailRedirectTo = supabaseSignupRedirectUrl();

        $signup = supabaseAuthRequest('POST', 'signup', [
            'email' => $email,
            'password' => $password,
            'options' => [
                'emailRedirectTo' => $emailRedirectTo,
            ],
            'data' => [
                'username'  => $username,
                'full_name' => $username,
                'phone'     => $phone,
            ],
        ]);

        if (!$signup['ok']) {
            $rawErr = strtolower(trim((string)($signup['error'] ?? 'unknown')));
            $status = (int)($signup['status'] ?? 0);
            error_log('[register] Supabase signup error status=' . $status . ' error=' . $rawErr);

            if (str_contains($rawErr, 'already registered') || str_contains($rawErr, 'user already registered')) {
                $errors['email'] = __('auth.register_error_email_exists');
            } elseif (str_contains($rawErr, 'redirect') || str_contains($rawErr, 'emailredirectto')) {
                $errors['form'] = __('auth.register_error_redirect');
            } elseif ($status === 429 || str_contains($rawErr, 'rate limit')) {
                $errors['form'] = __('auth.register_error_rate_limit');
            } elseif ($status === 403 || str_contains($rawErr, 'signup_disabled')) {
                $errors['form'] = __('auth.register_error_signup_disabled');
            } elseif (str_contains($rawErr, 'password') && (str_contains($rawErr, 'compromised') || str_contains($rawErr, 'leaked') || str_contains($rawErr, 'pwned'))) {
                $errors['password'] = __('auth.register_error_password_breach');
            } elseif (str_contains($rawErr, 'password')) {
                $errors['password'] = __('auth.register_error_password_rejected');
            } elseif (str_contains($rawErr, 'email') && str_contains($rawErr, 'invalid')) {
                $errors['email'] = __('auth.register_error_email_invalid');
            } else {
                $errors['form'] = __('auth.register_error_create');
            }
        }
    }

    if (!$errors) {
        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $supabaseUser = $signup['data']['user'] ?? [];
        $isVerified = !empty($supabaseUser['email_confirmed_at']) ? 1 : 0;
        $emailDomain = '';
        $atIdx = strrpos($email, '@');
        if ($atIdx !== false) {
            $emailDomain = substr($email, $atIdx + 1);
        }

        $pdo->beginTransaction();
        try {
            $studentId = studentIdFromUniversityEmail($email);

            $ins = $pdo->prepare("
                INSERT INTO users (username, email, student_id, password_hash, role, phone, is_verified)
                VALUES (:u, :e, :s, :h, 'user', :p, :v)
            ");
            $ins->execute([
                ':u' => $username,
                ':e' => $email,
                ':s' => $studentId,
                ':h' => $hash,
                ':p' => $phone !== '' ? $phone : null,
                ':v' => $isVerified,
            ]);

            $newUserId = (int)$pdo->lastInsertId();
            if ($newUserId > 0) {
                $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $adminId = $adminStmt->fetchColumn();
                if (!$adminId) {
                    $adminId = 1;
                }

                $welcomeMsg = "Welcome to CampusMarket, " . $username . "!\n\nBuy and sell with fellow students, and please meet in public campus spots for safety.\n\nNeed help? Reply to this chat and Support will assist you.\n\n- The CampusMarket Team";

                $msgStmt = $pdo->prepare("
                    INSERT INTO messages (sender_id, receiver_id, product_id, body)
                    VALUES (:sid, :rid, NULL, :body)
                ");
                $msgStmt->execute([
                    ':sid' => $adminId,
                    ':rid' => $newUserId,
                    ':body' => $welcomeMsg
                ]);

                createNotification($pdo, $newUserId, 'message', 'Welcome to CampusMarket', 'You received a welcome message from Support.', null);
            }

            $pdo->commit();
            $_SESSION['pending_verify_email'] = $email;
            queuePostHogEvent('signup_completed', [
                'method' => 'email',
                'university' => $emailDomain
            ]);
            queuePostHogEvent('user_signed_up', ['university' => $emailDomain]);
            redirect(BASE_URL . 'pages/check_email.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $dbErr = strtolower($e->getMessage());
            error_log('[register] DB error: ' . $e->getMessage());
            if (str_contains($dbErr, 'duplicate key') || str_contains($dbErr, 'unique') || str_contains($dbErr, 'already exists')) {
                $_SESSION['pending_verify_email'] = $email;
                redirect(BASE_URL . 'pages/check_email.php');
            } else {
                $errors['form'] = __('auth.register_error_save');
            }
        }
    }
}

$pageTitle = __('auth.register_page_title');
require_once '../includes/header.php';
?>


<div class="auth-page">
  <div class="auth-card">
  <div class="auth-head text-center mb-8">
    <h1><?= __('auth.register_title') ?></h1>
    <p><?= __('auth.register_subtitle_email') ?></p>
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
        <span>or register with email</span>
        <span style="flex: 1; height: 1px; background: var(--border-light, #e5e7eb);"></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors['form'])): ?>
    <div class="flash flash-error"><?php echo sanitize($errors['form']); ?></div>
  <?php endif; ?>

  <form method="post" novalidate class="js-form-loading" data-loading-text="<?= htmlspecialchars(__('auth.register_submitting'), ENT_QUOTES, 'UTF-8') ?>">
    <?php echo csrfTokenField(); ?>
    <div class="form-row mb-5">
      <label for="username" class="form-label"><?= __('auth.register_username_label') ?></label>
      <input type="text" id="username" name="username"
             value="<?php echo sanitize($old['username']); ?>"
             placeholder="<?= addslashes(__('auth.register_username_placeholder')) ?>"
             maxlength="50" required autofocus autocomplete="username"
             class="premium-input <?php echo isset($errors['username']) ? 'input-invalid' : ''; ?>">
      <?php if (isset($errors['username'])): ?>
        <div class="error"><?php echo sanitize($errors['username']); ?></div>
      <?php else: ?>
        <div class="hint"><?= __('auth.register_username_hint') ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-5">
      <label for="email" class="form-label"><?= __('auth.register_email_label') ?></label>
      <input type="email" id="email" name="email"
             value="<?php echo sanitize($old['email']); ?>"
             placeholder="<?= addslashes(__('auth.register_email_placeholder')) ?>"
             maxlength="100" required autocomplete="email"
             class="premium-input w-full <?php echo isset($errors['email']) ? 'input-invalid' : ''; ?>">
      <?php if (isset($errors['email'])): ?>
        <div class="error"><?php echo sanitize($errors['email']); ?></div>
      <?php else: ?>
        <div class="hint"><?= __('auth.register_email_hint') ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-5">
      <label for="phone" class="form-label"><?= __('auth.register_phone_label') ?> <span class="form-label--muted"><?= __('auth.register_phone_optional') ?></span></label>
      <input type="tel" id="phone" name="phone"
             value="<?php echo sanitize($old['phone']); ?>"
             placeholder="+90 555 123 4567"
             maxlength="20" autocomplete="tel"
             class="premium-input <?php echo isset($errors['phone']) ? 'input-invalid' : ''; ?>">
      <?php if (isset($errors['phone'])): ?>
        <div class="error"><?php echo sanitize($errors['phone']); ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-5">
      <label for="password" class="form-label"><?= __('auth.register_password_label') ?></label>
      <div class="input-with-toggle">
        <input type="password" id="password" name="password"
               placeholder="<?= addslashes(__('auth.register_password_placeholder')) ?>"
               minlength="8" required autocomplete="new-password"
               class="premium-input <?php echo isset($errors['password']) ? 'input-invalid' : ''; ?>">
        <button type="button" class="password-toggle" data-target="password" aria-label="<?= htmlspecialchars(__('auth.show_password')) ?>">
          <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a19.8 19.8 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c6.5 0 10 7 10 7a19.9 19.9 0 0 1-3.17 4.19"/>
            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
            <path d="M1 1l22 22"/>
          </svg>
        </button>
      </div>
      <?php if (isset($errors['password'])): ?>
        <div class="error"><?php echo sanitize($errors['password']); ?></div>
      <?php else: ?>
        <div class="hint"><?= __('auth.register_password_hint') ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-6">
      <label for="password_confirm" class="form-label"><?= __('auth.register_confirm_label') ?></label>
      <div class="input-with-toggle">
        <input type="password" id="password_confirm" name="password_confirm"
               placeholder="<?= addslashes(__('auth.register_confirm_placeholder')) ?>"
               minlength="8" required autocomplete="new-password"
               class="premium-input <?php echo isset($errors['password_confirm']) ? 'input-invalid' : ''; ?>">
        <button type="button" class="password-toggle" data-target="password_confirm" aria-label="<?= htmlspecialchars(__('auth.show_password')) ?>">
          <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a19.8 19.8 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c6.5 0 10 7 10 7a19.9 19.9 0 0 1-3.17 4.19"/>
            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
            <path d="M1 1l22 22"/>
          </svg>
        </button>
      </div>
      <?php if (isset($errors['password_confirm'])): ?>
        <div class="error"><?php echo sanitize($errors['password_confirm']); ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row" style="display: flex; align-items: flex-start; gap: 0.75rem; margin-top: 1.5rem; margin-bottom: 2rem;">
      <input type="checkbox" id="terms" name="terms" value="1" style="width: 20px; height: 20px; margin-top: 0.2rem; cursor: pointer;" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
      <label for="terms" style="font-weight: 500; font-size: 0.95rem; color: var(--text-muted); margin: 0; line-height: 1.5; cursor: pointer;">
        <?= __('auth.register_terms_agree', [
            'terms_url' => BASE_URL . 'pages/terms.php',
            'privacy_url' => BASE_URL . 'pages/privacy.php',
            'rules_url' => BASE_URL . 'pages/rules.php',
        ]) ?>
      </label>
    </div>
    <?php if (isset($errors['terms'])): ?>
      <div class="error" style="margin-top: -1rem; margin-bottom: 1rem; color: #b91c1c; font-size: 0.85rem;"><?php echo sanitize($errors['terms']); ?></div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary w-full py-4 shadow-sm" style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; letter-spacing: 0.01em;"><?= __('auth.register_submit') ?></button>
  </form>

  <p class="auth-foot mt-10">
    <?= __('auth.register_already_have') ?>
    <a href="<?php echo BASE_URL; ?>pages/login.php" style="font-weight: 600;"><?= __('auth.register_log_in') ?></a>
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
