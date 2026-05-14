<?php
// pages/register.php — Member 2
// Create a CampusMarket account with university-email allowlist
// and email verification via Resend. No auto-login: user must verify
// before they can sign in.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';
require_once '../includes/mailer.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'pages/profile.php');
}

$errors = [];
$old    = ['username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Gather + normalize
    $username = trim($_POST['username'] ?? '');
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $old['username'] = $username;
    $old['email']    = $email;
    $old['phone']    = $phone;

    // Validate username
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = '3–50 characters. Letters, numbers, underscores only.';
    }

    // Validate email — format first, then domain allowlist
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (!isAllowedUniversityEmail($email)) {
        $errors['email'] = 'Only university emails are allowed (' . allowedDomainsList() . ').';
    }

    // Validate phone (optional)
    if ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone)) {
        $errors['phone'] = 'Phone number looks invalid.';
    }

    // Validate password
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'You must agree to the Terms of Service and Privacy Policy.';
    }

    // Uniqueness check (case-insensitive on email)
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
                $errors['username'] = 'That username is already taken.';
            }
            if (strcasecmp($dup['email'], $email) === 0) {
                $errors['email'] = 'An account with that email already exists.';
            }
        }
    }

    // Persist + send verification email (no auto-login)
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        $token = '';
        try {
            $ins = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, phone, is_verified)
                VALUES (:u, :e, :h, 'user', :p, 0)
            ");
            $ins->execute([
                ':u' => $username,
                ':e' => $email,
                ':h' => $hash,
                ':p' => $phone !== '' ? $phone : null,
            ]);
            $newId = (int) $pdo->lastInsertId();

            $token   = generateVerificationToken();
            $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

            $tok = $pdo->prepare('
                INSERT INTO email_verifications (user_id, token, expires_at)
                VALUES (:u, :t, :x)
            ');
            $tok->execute([':u' => $newId, ':t' => $token, ':x' => $expires]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[register] DB error: ' . $e->getMessage());
            $errors['form'] = 'Could not create account. Please try again.';
        }

        // Send the verification email outside the transaction (network call).
        if (empty($errors)) {
            $verifyUrl = BASE_URL . 'pages/verify_email.php?token=' . urlencode($token);
            $result    = sendVerificationEmail($email, $username, $verifyUrl);

            if (!$result['ok']) {
                // Account exists but the email didn't go out. Tell the user;
                // they can ask for a resend later (or you can re-register).
                setFlash(
                    'error',
                    'Account created, but we couldn\'t send the verification email. '
                    . 'Check the server log or contact support.'
                );
            } else {
                setFlash(
                    'success',
                    'Account created. Check your inbox at ' . sanitize($email) . ' to verify your email before logging in.'
                );
            }
            redirect(BASE_URL . 'pages/login.php');
        }
    }
}

$pageTitle = 'Create account';
require_once '../includes/header.php';
?>


<div class="auth-page">
  <div class="auth-card">
  <div class="auth-head text-center mb-8">
    <h1>Create your account</h1>
    <p>University email required. We'll send you a link to verify it.</p>
  </div>

  <?php if (!empty($errors['form'])): ?>
    <div class="flash flash-error"><?php echo sanitize($errors['form']); ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="form-row mb-5">
      <label for="username" class="form-label">Username</label>
      <input type="text" id="username" name="username"
             value="<?php echo sanitize($old['username']); ?>"
             placeholder="e.g. ahmet_yilmaz"
             maxlength="50" required autofocus autocomplete="username"
             class="premium-input <?php echo isset($errors['username']) ? 'input-invalid' : ''; ?>">
      <?php if (isset($errors['username'])): ?>
        <div class="error"><?php echo sanitize($errors['username']); ?></div>
      <?php else: ?>
        <div class="hint">3–50 characters. Letters, numbers, underscores.</div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-5">
      <label for="email" class="form-label">University email</label>
      <input type="email" id="email" name="email"
             value="<?php echo sanitize($old['email']); ?>"
             placeholder="you@std.neu.edu.tr"
             maxlength="100" required autocomplete="email"
             class="premium-input <?php echo isset($errors['email']) ? 'input-invalid' : ''; ?>">
      <?php if (isset($errors['email'])): ?>
        <div class="error"><?php echo sanitize($errors['email']); ?></div>
      <?php else: ?>
        <div class="hint">Allowed: <?php echo sanitize(allowedDomainsList()); ?></div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-5">
      <label for="phone" class="form-label">Phone <span class="form-label--muted">(optional)</span></label>
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
      <label for="password" class="form-label">Password</label>
      <div class="input-with-toggle">
        <input type="password" id="password" name="password"
               placeholder="At least 8 characters"
               minlength="8" required autocomplete="new-password"
               class="premium-input <?php echo isset($errors['password']) ? 'input-invalid' : ''; ?>">
        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
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
        <div class="hint">Mix letters and numbers. At least 8 characters.</div>
      <?php endif; ?>
    </div>

    <div class="form-row mb-6">
      <label for="password_confirm" class="form-label">Confirm password</label>
      <div class="input-with-toggle">
        <input type="password" id="password_confirm" name="password_confirm"
               placeholder="Re-enter your password"
               minlength="8" required autocomplete="new-password"
               class="premium-input <?php echo isset($errors['password_confirm']) ? 'input-invalid' : ''; ?>">
        <button type="button" class="password-toggle" data-target="password_confirm" aria-label="Show password">
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
        I agree to the <a href="<?php echo BASE_URL; ?>pages/terms.php" style="font-weight: 700; text-decoration: underline;" target="_blank">Terms of Service</a> and <a href="<?php echo BASE_URL; ?>pages/privacy.php" style="font-weight: 700; text-decoration: underline;" target="_blank">Privacy Policy</a>.
      </label>
    </div>
    <?php if (isset($errors['terms'])): ?>
      <div class="error" style="margin-top: -1rem; margin-bottom: 1rem; color: #b91c1c; font-size: 0.85rem;"><?php echo sanitize($errors['terms']); ?></div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary w-full py-4 shadow-lg hover-scale" style="border-radius: 14px; font-weight: 800; font-size: 1.1rem; letter-spacing: 0.01em;">Create account</button>
  </form>

  <p class="auth-foot mt-10">
    Already have an account?
    <a href="<?php echo BASE_URL; ?>pages/login.php" style="font-weight: 800;">Log in</a>
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
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
</script>

<?php require_once '../includes/footer.php'; ?>
