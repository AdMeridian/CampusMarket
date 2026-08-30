<?php
// pages/unsubscribe.php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$pageTitle = "Email Preferences — " . APP_NAME;

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isValid = !empty($email) && !empty($token) && verifyUnsubscribeToken($email, $token);
$isDone  = false;
$error   = '';

if ($isValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'unsubscribe';
    
    try {
        if ($action === 'unsubscribe') {
            // Find user_id if exists
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $uStmt->execute([':email' => $email]);
            $userId = $uStmt->fetchColumn() ?: null;

            // Insert into email_unsubscribes
            $insStmt = $pdo->prepare("
                INSERT INTO email_unsubscribes (email, user_id, reason)
                VALUES (:email, :user_id, :reason)
                ON CONFLICT (email) DO UPDATE SET created_at = NOW()
            ");
            // Fallback for MySQL/PostgreSQL compatibility
            try {
                $insStmt->execute([
                    ':email'   => strtolower($email),
                    ':user_id' => $userId,
                    ':reason'  => 'User clicked one-click unsubscribe'
                ]);
            } catch (Exception $e) {
                // If ON CONFLICT isn't supported (e.g. standard MySQL), fallback to IGNORE/REPLACE
                $fallbackStmt = $pdo->prepare("
                    INSERT IGNORE INTO email_unsubscribes (email, user_id, reason)
                    VALUES (:email, :user_id, :reason)
                ");
                $fallbackStmt->execute([
                    ':email'   => strtolower($email),
                    ':user_id' => $userId,
                    ':reason'  => 'User clicked one-click unsubscribe'
                ]);
            }
            $isDone = true;
        } elseif ($action === 'resubscribe') {
            $delStmt = $pdo->prepare("DELETE FROM email_unsubscribes WHERE LOWER(email) = LOWER(:email)");
            $delStmt->execute([':email' => $email]);
            setFlash('success', 'You have been re-subscribed to campus promotional announcements.');
            redirect(BASE_URL);
        }
    } catch (Exception $e) {
        $error = "Unable to update preference: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-16" style="max-width: 540px;">
    <div class="glass-panel text-center" style="padding: 2.5rem 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--shadow-lg);">
        <div style="width: 56px; height: 56px; margin: 0 auto 1.25rem; border-radius: 50%; background: rgba(26, 127, 100, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center;">
            <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
        </div>

        <h1 style="font-size: 1.6rem; margin-bottom: 0.5rem;">Email Preferences</h1>

        <?php if (!$isValid): ?>
            <div class="alert alert-error" style="margin-top: 1.5rem; text-align: left;">
                Invalid or expired unsubscribe link. If you need help, please contact <a href="mailto:support@campusmarketplace.site">support@campusmarketplace.site</a>.
            </div>
            <div class="mt-6">
                <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary btn-sm">Return to Home</a>
            </div>
        <?php elseif ($isDone): ?>
            <div class="alert alert-success" style="margin: 1.5rem 0; text-align: left;">
                <strong>Unsubscribed:</strong> <code><?php echo htmlspecialchars($email); ?></code> will no longer receive promotional emails from CampusMarket.
            </div>
            <p class="text-muted small mb-6">
                Important transactional emails (such as order updates, account security, and message notifications) will still be delivered.
            </p>
            <form method="POST" style="margin-bottom: 1rem;">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="action" value="resubscribe">
                <button type="submit" class="btn btn-secondary btn-sm">Resubscribe</button>
            </form>
            <a href="<?php echo BASE_URL; ?>" class="small text-primary font-semibold">Back to Campus Marketplace →</a>
        <?php else: ?>
            <p class="text-muted" style="line-height: 1.5; margin-bottom: 1.5rem;">
                You are managing email preferences for: <br>
                <strong class="text-main" style="font-size: 1.05rem;"><?php echo htmlspecialchars($email); ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="action" value="unsubscribe">
                <button type="submit" class="btn btn-primary w-full" style="padding: 0.75rem 1.5rem; font-weight: 700;">
                    Confirm Unsubscribe
                </button>
            </form>
            <div class="mt-4">
                <a href="<?php echo BASE_URL; ?>" class="small text-muted">Cancel and return to site</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
