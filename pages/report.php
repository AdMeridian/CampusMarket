<?php
require_once __DIR__ . '/../includes/mailer.php';
$page_title = "Report an Issue";
require_once __DIR__ . '/../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueType = sanitize($_POST['issue_type'] ?? '');
    $link = sanitize($_POST['link'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $currentUserId = currentUserId();

    // Try to extract product ID from link if it's a CampusMarket link
    $productId = null;
    if (preg_match('/product\.php\?id=(\d+)/', $link, $matches)) {
        $productId = (int)$matches[1];
    }

    $reason = "[" . strtoupper($issueType) . "] " . $description;
    if ($link) {
        $reason .= "\n\nReference Link: " . $link;
    }

    try {
        // 1. Save to Database
        $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, product_id, reason, status) VALUES (:rid, :pid, :reason, 'pending')");
        $stmt->execute([
            ':rid' => $currentUserId ?: null, // Allow guest reports if needed, though header usually requires login
            ':pid' => $productId,
            ':reason' => $reason
        ]);

        // 2. Send Email to Admin
        $adminEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : (getenv('RESEND_FROM_EMAIL') ?: 'support@campusmarket.edu');
        $subject = "New Report: " . ucfirst($issueType);
        $username = $_SESSION['username'] ?? 'Guest';
        
        $html = "<h2>New Support Report</h2>";
        $html .= "<p><strong>From:</strong> {$username} (ID: " . ($currentUserId ?: 'N/A') . ")</p>";
        $html .= "<p><strong>Type:</strong> {$issueType}</p>";
        $html .= "<p><strong>Link:</strong> " . ($link ?: 'None') . "</p>";
        $html .= "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>";
        $html .= "<hr><p>This report has been saved to the database (ID: " . $pdo->lastInsertId() . ")</p>";

        sendEmail($adminEmail, $subject, $html);

        $message = '<div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #059669; padding: 1rem; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 1.5rem;">Thank you for your report. Our moderation team has been notified and will review it shortly.</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #dc2626; padding: 1rem; border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.2); margin-bottom: 1.5rem;">There was an error submitting your report. Please try again later.</div>';
    }
}
?>

<div class="container py-4">
    <div class="card p-5" style="max-width: 600px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;">Report an Issue</h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;">Help us keep CampusMarket safe by reporting suspicious listings, abusive users, or technical problems.</p>

        <?= $message ?>

        <form action="report.php" method="POST">
            <div class="mb-3">
                <label for="issue_type" class="form-label" style="font-weight: 600; color: var(--text-main);">Issue Type</label>
                <select name="issue_type" id="issue_type" class="form-control premium-input" required style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%;">
                    <option value="scam">Suspected Scam or Fraud</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="harassment">Harassment or Abusive Behavior</option>
                    <option value="technical">Technical Bug or Error</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="link" class="form-label" style="font-weight: 600; color: var(--text-main);">Link to Listing/User (Optional)</label>
                <input type="url" name="link" id="link" class="form-control premium-input" placeholder="https://..." style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%;">
            </div>

            <div class="mb-4">
                <label for="description" class="form-label" style="font-weight: 600; color: var(--text-main);">Description</label>
                <textarea name="description" id="description" rows="5" class="form-control premium-input" required placeholder="Please provide details about the issue..." style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.75rem; font-weight: 600;">Submit Report</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
