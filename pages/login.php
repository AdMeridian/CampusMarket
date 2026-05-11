<?php
// pages/login.php — Member 2
// Authenticate via email-or-username + password.
// Blocks unverified accounts (is_verified = 0) until they click the
// link in their verification email.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'pages/profile.php');
}

$errors    = [];
$identity  = '';
$unverified = false;   // true → render "check your inbox" message instead of the generic error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        $errors['form'] = 'Please enter your email/username and password.';
    } else {
        // Look up by email (case-insensitive) OR username.
        // Note: named placeholders can't be reused when EMULATE_PREPARES=false,
        // so :id_email and :id_user are bound separately to the same value.
        $stmt = $pdo->prepare('
            SELECT id, username, email, password_hash, role, is_verified
            FROM users
            WHERE LOWER(email) = LOWER(:id_email) OR username = :id_user
            LIMIT 1
        ');
        $stmt->execute([
            ':id_email' => $identity,
            ':id_user'  => $identity,
        ]);
        $user = $stmt->fetch();

        // Same error message for "no such user" and "wrong password"
        // prevents account enumeration.
        $bad = 'Incorrect email/username or password.';

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['form'] = $bad;
        } elseif ((int) $user['is_verified'] !== 1) {
            // Correct credentials, but email not verified yet.
            $unverified = true;
            $errors['form'] = 'Please verify your email before logging in. '
                            . 'Check your inbox at ' . sanitize($user['email']) . '.';
        } else {
            // Transparent rehash if default cost moved up since last login.
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                $upd->execute([
                    ':h'  => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $user['id'],
                ]);
            }

            // Fixation defense.
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['username'] = $user['username'];

            setFlash('success', 'Welcome back, ' . sanitize($user['username']) . '!');

            // Safe internal redirect: allow ?redirect=/pages/..., reject anything else.
            $target = $_GET['redirect'] ?? '';
            if ($target && strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
                redirect(BASE_URL . ltrim($target, '/'));
            }
            redirect(BASE_URL . 'pages/profile.php');
        }
    }
}

$pageTitle = 'Log in';
require_once '../includes/header.php';
?>


<div class="auth-page">
  <div class="auth-card">
  <h1>Log in</h1>

  <?php if (!empty($errors['form'])): ?>
    <div class="<?php echo $unverified ? 'flash flash-warning' : 'flash flash-error'; ?>">
      <?php echo sanitize($errors['form']); ?>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="form-row">
      <label for="identity">Email or username</label>
      <input type="text" id="identity" name="identity"
             value="<?php echo sanitize($identity); ?>"
             placeholder="you@std.neu.edu.tr or your username"
             required autofocus autocomplete="username">
    </div>

    <div class="form-row">
      <label for="password">Password</label>
      <div class="password-wrap">
        <input type="password" id="password" name="password"
               placeholder="Your password"
               required autocomplete="current-password">
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
    </div>

    <button type="submit" class="btn btn-primary w-full py-4 mt-4">Log in</button>
  </form>

  <p class="auth-foot">
    New here?
    <a href="<?php echo BASE_URL; ?>pages/register.php">Create an account</a>
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
