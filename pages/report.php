<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page_title = "Report an Issue";
require_once __DIR__ . '/../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mock processing
    $message = '<div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #059669; padding: 1rem; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 1.5rem;">Thank you for your report. Our moderation team will review it shortly.</div>';
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
