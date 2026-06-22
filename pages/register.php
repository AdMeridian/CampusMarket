<?php
// pages/register.php — Member 2
// Create a CampusMarket account with university-email allowlist
// and email verification via Supabase Auth. No auto-login: user must verify
// before they can sign in.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'pages/profile.php');
}

$errors = [];
$old    = ['username' => '', 'email' => '', 'email_local' => '', 'university_domain' => '', 'phone' => ''];
$universityDomains = allowedUniversityDomains();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();
    // Gather + normalize
    $username = trim($_POST['username'] ?? '');
    $emailLocal = trim(strtolower($_POST['email_local'] ?? ''));
    $universityDomain = trim(strtolower($_POST['university_domain'] ?? ''));
    $email    = $emailLocal !== '' && $universityDomain !== ''
        ? buildUniversityEmail($emailLocal, $universityDomain)
        : trim(strtolower($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $old['username'] = $username;
    $old['email']    = $email;
    $old['email_local'] = $emailLocal;
    $old['university_domain'] = $universityDomain;
    $old['phone']    = $phone;

    // Validate username
    if ($username === '') {
        $errors['username'] = __('auth.register_error_username_required');
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = __('auth.register_error_username_format');
    }

    if ($emailLocal === '' || $universityDomain === '') {
        $errors['email'] = __('auth.register_error_email_university');
    } elseif (!array_key_exists($universityDomain, $universityDomains)) {
        $errors['email'] = __('auth.register_error_email_invalid_uni');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors['email'] = __('auth.register_error_email_invalid');
    } elseif (!isAllowedUniversityEmail($email)) {
        $errors['email'] = __('auth.register_error_email_domains', ['domains' => allowedDomainsList()]);
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
                $errors['username'] = __('auth.register_error_username_taken');
            }
            if (strcasecmp($dup['email'], $email) === 0) {
                $errors['email'] = __('auth.register_error_email_exists');
            }
        }
    }

    // Persist with Supabase Auth + local app profile (no auto-login)
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
                'full_name' => $username, // shown as display name in Supabase Auth dashboard
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
                // Find an admin to send the welcome message
                $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $adminId = $adminStmt->fetchColumn();
                if (!$adminId) {
                    $adminId = 1; // Fallback to 1
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

                // Notify user of the welcome message
                createNotification($pdo, $newUserId, 'message', 'Welcome to CampusMarket', 'You received a welcome message from Support.', null);
            }

            $pdo->commit();
            $_SESSION['pending_verify_email'] = $email;
            $_SESSION['posthog_event'] = [
                'name' => 'user_signed_up',
                'properties' => ['university' => $universityDomain]
            ];
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

  <?php if (!empty($errors['form'])): ?>
    <div class="flash flash-error"><?php echo sanitize($errors['form']); ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
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
      <label for="university_domain" class="form-label"><?= __('auth.register_university_label') ?></label>
      <select id="university_domain" name="university_domain" required
              class="premium-input <?php echo isset($errors['email']) ? 'input-invalid' : ''; ?>">
        <option value=""><?= __('auth.register_university_placeholder') ?></option>
        <?php foreach ($universityDomains as $domain => $label): ?>
          <option value="<?php echo sanitize($domain); ?>"
            <?php echo $old['university_domain'] === $domain ? 'selected' : ''; ?>>
            <?php echo sanitize($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row mb-5">
      <label for="email_local" class="form-label"><?= __('auth.register_email_label') ?></label>
      <div class="email-compose <?php echo isset($errors['email']) ? 'input-invalid' : ''; ?>">
        <input type="text" id="email_local" name="email_local"
               value="<?php echo sanitize($old['email_local']); ?>"
               placeholder="<?= addslashes(__('auth.register_email_placeholder')) ?>"
               maxlength="64" required autocomplete="username"
               class="premium-input email-compose-input"
               aria-describedby="email_domain_suffix">
        <span id="email_domain_suffix" class="email-compose-suffix">@<?php echo sanitize($old['university_domain'] !== '' ? $old['university_domain'] : 'university.edu.tr'); ?></span>
      </div>
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
  (function () {
    var domainSelect = document.getElementById('university_domain');
    var suffix = document.getElementById('email_domain_suffix');
    if (domainSelect && suffix) {
      function syncSuffix() {
        suffix.textContent = domainSelect.value ? '@' + domainSelect.value : '@university.edu.tr';
      }
      domainSelect.addEventListener('change', syncSuffix);
      syncSuffix();
    }
  })();

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

