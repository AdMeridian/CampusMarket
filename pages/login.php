<?php
// pages/login.php — Member 2
// Authenticate via Supabase Auth using email-or-username + password.
// Keeps local app sessions/users table for marketplace data.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'pages/profile.php');
}

$errors    = [];
$identity  = '';
$unverified = false;   // true → render "check your inbox" message instead of the generic error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        $errors['form'] = 'Please enter your email/username and password.';
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
                $errors['form'] = 'Please verify your email before logging in. Check your inbox at ' . sanitize($loginEmail) . '.';
            } else {
                $errors['form'] = 'Incorrect email/username or password.';
            }
        } else {
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
                $studentId = null;
                $parts = explode('@', $authEmail);
                if (($parts[1] ?? '') === 'std.neu.edu.tr' && preg_match('/^\d+$/', $parts[0])) {
                    $studentId = $parts[0];
                }
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
                $errors['form'] = 'Could not initialize your account profile.';
            } elseif ($isVerified !== 1) {
                // Should be rare if Supabase already returned a session.
                $unverified = true;
                $errors['form'] = 'Please verify your email before logging in. Check your inbox at ' . sanitize($authEmail) . '.';
            } else {
                if ((int) $user['is_verified'] !== 1) {
                    $upd = $pdo->prepare('UPDATE users SET is_verified = TRUE WHERE id = :id');
                    $upd->execute([':id' => $user['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['supabase_access_token'] = (string) ($auth['data']['access_token'] ?? '');
                $_SESSION['supabase_refresh_token'] = (string) ($auth['data']['refresh_token'] ?? '');

                setFlash('success', 'Welcome back, ' . sanitize($user['username']) . '!');

                $target = $_GET['redirect'] ?? '';
                if ($target && strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
                    redirect(BASE_URL . ltrim($target, '/'));
                }
                redirect(BASE_URL . 'pages/profile.php');
            }
        }
    }
}

$pageTitle = 'Log in';
require_once '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <h1>Log in to your account</h1>
            <p>Enter your credentials to access the marketplace.</p>
        </div>

        <?php if (!empty($errors['form'])): ?>
            <div class="flash <?php echo $unverified ? 'flash-warning' : 'flash-error'; ?> mb-8">
                <?php echo sanitize($errors['form']); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?php echo csrfTokenField(); ?>
            <div class="form-row mb-6">
                <label for="identity" class="form-label">Email or username</label>
                <input type="text" id="identity" name="identity"
                       value="<?php echo sanitize($identity); ?>"
                       placeholder="you@std.neu.edu.tr"
                       class="premium-input w-full"
                       required autofocus autocomplete="username">
            </div>

            <div class="form-row mb-8">
                <div class="flex justify-between items-center mb-1.5">
                    <label for="password" class="form-label">Password</label>
                    <a href="#" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">Forgot password?</a>
                </div>
                <div class="input-with-toggle">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           class="premium-input w-full"
                           required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" style="right: 12px;">
                        <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0112 19c-6.5 0-10-7-10-7a19.8 19.8 0 015.06-5.94M9.9 4.24A10.94 10.94 0 0112 4c6.5 0 10 7 10 7a19.9 19.9 0 01-3.17 4.19M9.88 9.88a3 3 0 104.24 4.24M1 1l22 22"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full py-4 shadow-sm" style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; letter-spacing: 0.01em;">Log in</button>
        </form>

        <p class="auth-foot mt-10">
            New to CampusMarket? 
            <a href="<?php echo BASE_URL; ?>pages/register.php" style="font-weight: 600;">Create an account</a>
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
