<?php
// pages/forgot_password.php — Password recovery page
// Implements Supabase Auth password recovery flow.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

if (isLoggedIn()) {
    // If user already logged in, redirect to profile.
    redirect(BASE_URL . 'pages/profile.php');
}

$errors = [];
$successMessage = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $isSecureRequest = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
        $originHost = $_SERVER['HTTP_HOST'] ?? '';
        $originScheme = $isSecureRequest ? 'https' : 'http';
        // Use extensionless URL — Vercel's front controller 301-redirects
        // .php → no-.php, which can drop the hash fragment (#access_token=...)
        // on some browsers (Outlook embedded, older Safari, Android WebViews).
        // Sending the clean URL avoids the redirect entirely.
        $emailRedirectTo = $originHost !== ''
            ? ($originScheme . '://' . $originHost . '/pages/reset_password')
            : (BASE_URL . 'pages/reset_password');

        // Call Supabase password recovery endpoint.
        $response = supabaseAuthRequest('POST', 'recover', [
            'email' => $email,
            'redirect_to' => $emailRedirectTo,
            'options' => [
                'emailRedirectTo' => $emailRedirectTo,
            ],
        ]);
        if ($response['ok']) {
            $successMessage = 'If an account exists for this email, a recovery link has been sent.';
        } else {
            // Supabase returns 400 for unknown email – treat the same for security.
            $errors['form'] = 'If an account exists for this email, a recovery link has been sent.';
        }
    }
}

$pageTitle = 'Forgot Password';
require_once '../includes/header.php';
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <h1>Forgot your password?</h1>
            <p>Enter your email below and we’ll send you a link to reset your password.</p>
        </div>
        <?php if (!empty($errors['form'])): ?>
            <div class="flash flash-error mb-8"><?php echo sanitize($errors['form']); ?></div>
        <?php elseif ($successMessage): ?>
            <div class="flash flash-success mb-8"><?php echo sanitize($successMessage); ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?php echo csrfTokenField(); ?>
            <div class="form-row mb-6">
                <label for="email" class="form-label">Email address</label>
                <input type="email" id="email" name="email" value="<?php echo sanitize($email); ?>"
                       placeholder="you@std.neu.edu.tr" class="premium-input w-full" required autocomplete="email">
                <?php if (!empty($errors['email'])): ?>
                    <div class="text-sm text-red-500 mt-1"><?php echo sanitize($errors['email']); ?></div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary w-full py-4 shadow-sm"
                    style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.1rem; letter-spacing: 0.01em;">
                Send Reset Link
            </button>
        </form>
        <p class="auth-foot mt-10">Remembered your password? <a href="<?php echo BASE_URL; ?>pages/login.php" style="font-weight: 600;">Log in</a></p>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
