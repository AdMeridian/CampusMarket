<?php
// admin/campaigns.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/admin_audit.php';

$pageTitle = "Email Marketing & Campaigns";
$currentAdmin = currentUser();
$adminEmail = $currentAdmin['email'] ?? '';

// Ensure tables exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id BIGSERIAL PRIMARY KEY,
            admin_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            subject VARCHAR(255) NOT NULL,
            preview_text VARCHAR(255) NULL,
            audience_type VARCHAR(50) NOT NULL DEFAULT 'all',
            template_preset VARCHAR(50) NOT NULL DEFAULT 'custom',
            body_html TEXT NOT NULL,
            total_recipients INT NOT NULL DEFAULT 0,
            successful_sends INT NOT NULL DEFAULT 0,
            failed_sends INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            sent_at TIMESTAMPTZ NULL
        );
        CREATE TABLE IF NOT EXISTS email_unsubscribes (
            id BIGSERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        );
    ");
} catch (Exception $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                subject VARCHAR(255) NOT NULL,
                preview_text VARCHAR(255) NULL,
                audience_type VARCHAR(50) NOT NULL DEFAULT 'all',
                template_preset VARCHAR(50) NOT NULL DEFAULT 'custom',
                body_html TEXT NOT NULL,
                total_recipients INT NOT NULL DEFAULT 0,
                successful_sends INT NOT NULL DEFAULT 0,
                failed_sends INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL
            ) ENGINE=InnoDB;
            CREATE TABLE IF NOT EXISTS email_unsubscribes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                user_id INT NULL,
                reason VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
    } catch (Exception $e2) {
        // Continue silently if already exists or permissions restricted
    }
}

// Fetch audience counts
$stats = [
    'all' => 0,
    'sellers' => 0,
    'buyers' => 0,
    'inactive' => 0,
    'unsubscribed' => 0,
];

try {
    $stats['unsubscribed'] = (int)$pdo->query("SELECT COUNT(*) FROM email_unsubscribes")->fetchColumn();
    
    $stats['all'] = (int)$pdo->query("
        SELECT COUNT(*) FROM users u
        WHERE u.account_status = 'active'
          AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
    ")->fetchColumn();

    $stats['sellers'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM users u
        JOIN products p ON p.user_id = u.id
        WHERE u.account_status = 'active'
          AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
    ")->fetchColumn();

    $stats['buyers'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM users u
        LEFT JOIN orders o ON o.buyer_id = u.id
        LEFT JOIN deal_confirmations dc ON dc.buyer_id = u.id
        WHERE u.account_status = 'active'
          AND (o.id IS NOT NULL OR dc.id IS NOT NULL)
          AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
    ")->fetchColumn();

    $stats['inactive'] = (int)$pdo->query("
        SELECT COUNT(*) FROM users u
        WHERE u.account_status = 'active'
          AND u.created_at <= (NOW() - INTERVAL '30 days')
          AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
    ")->fetchColumn();
} catch (Exception $e) {
    // If Postgres interval syntax fails in local mock MySQL, fallback safely
    try {
        $stats['inactive'] = (int)$pdo->query("
            SELECT COUNT(*) FROM users u
            WHERE u.account_status = 'active'
              AND u.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
        ")->fetchColumn();
    } catch (Exception $e2) {
        $stats['inactive'] = 0;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    $subject     = trim($_POST['subject'] ?? '');
    $headline    = trim($_POST['headline'] ?? '');
    $bodyContent = trim($_POST['body_content'] ?? '');
    $ctaText     = trim($_POST['cta_text'] ?? 'Explore Marketplace');
    $ctaUrl      = trim($_POST['cta_url'] ?? (BASE_URL . 'pages/browse.php'));
    $audience    = trim($_POST['audience_type'] ?? 'all');
    $preset      = trim($_POST['template_preset'] ?? 'custom');

    if (empty($subject) || empty($headline) || empty($bodyContent)) {
        setFlash('error', 'Subject line, headline, and message body are required.');
        redirect('campaigns.php');
    }

    if ($action === 'send_test') {
        $testRecipient = trim($_POST['test_email'] ?? $adminEmail);
        if (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Invalid test email destination.');
            redirect('campaigns.php');
        }

        $sampleName = $currentAdmin['username'] ?? 'Admin';
        $personalizedBody = str_replace(
            ['{{username}}', '{{app_name}}', '{{campus_name}}', '{{browse_url}}'],
            [$sampleName, APP_NAME, 'Campus', BASE_URL . 'pages/browse.php'],
            $bodyContent
        );
        $personalizedHeadline = str_replace(
            ['{{username}}', '{{app_name}}', '{{campus_name}}'],
            [$sampleName, APP_NAME, 'Campus'],
            $headline
        );

        $html = buildMarketingEmailHtml($personalizedHeadline, nl2br($personalizedBody), $ctaUrl, $ctaText, generateUnsubscribeUrl($testRecipient));
        $res = sendMarketingEmail($testRecipient, "[TEST] " . $subject, $html);

        if ($res['ok']) {
            setFlash('success', "Test email successfully sent to {$testRecipient} via marketing@campusmarketplace.site.");
        } else {
            setFlash('error', "Failed to send test email: " . ($res['error'] ?? 'Unknown mailer error'));
        }
        redirect('campaigns.php');
    }

    if ($action === 'launch_campaign') {
        // Build audience SQL query
        $audienceSql = "
            SELECT u.id, u.username, u.email FROM users u
            WHERE u.account_status = 'active'
              AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
        ";

        if ($audience === 'sellers') {
            $audienceSql = "
                SELECT DISTINCT u.id, u.username, u.email FROM users u
                JOIN products p ON p.user_id = u.id
                WHERE u.account_status = 'active'
                  AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
            ";
        } elseif ($audience === 'buyers') {
            $audienceSql = "
                SELECT DISTINCT u.id, u.username, u.email FROM users u
                LEFT JOIN orders o ON o.buyer_id = u.id
                LEFT JOIN deal_confirmations dc ON dc.buyer_id = u.id
                WHERE u.account_status = 'active'
                  AND (o.id IS NOT NULL OR dc.id IS NOT NULL)
                  AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
            ";
        } elseif ($audience === 'inactive') {
            $audienceSql = "
                SELECT u.id, u.username, u.email FROM users u
                WHERE u.account_status = 'active'
                  AND u.created_at <= (NOW() - INTERVAL '30 days')
                  AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
            ";
        }

        try {
            $recipients = $pdo->query($audienceSql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback for MySQL date sub
            $recipients = $pdo->query("
                SELECT u.id, u.username, u.email FROM users u
                WHERE u.account_status = 'active'
                  AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $totalRecipients = count($recipients);
        if ($totalRecipients === 0) {
            setFlash('error', 'No active recipients found for the selected audience segment.');
            redirect('campaigns.php');
        }

        // Insert campaign record
        $campStmt = $pdo->prepare("
            INSERT INTO email_campaigns (
                admin_id, subject, preview_text, audience_type, template_preset,
                body_html, total_recipients, status, created_at
            ) VALUES (
                :admin_id, :subject, :preview_text, :audience_type, :template_preset,
                :body_html, :total_recipients, 'sending', NOW()
            )
        ");
        $campStmt->execute([
            ':admin_id'         => currentUserId(),
            ':subject'          => $subject,
            ':preview_text'     => mb_substr(strip_tags($bodyContent), 0, 120),
            ':audience_type'    => $audience,
            ':template_preset'  => $preset,
            ':body_html'        => $bodyContent,
            ':total_recipients' => $totalRecipients,
        ]);
        $campaignId = (int)$pdo->lastInsertId();

        // Dispatch loop
        $successCount = 0;
        $failCount = 0;

        foreach ($recipients as $r) {
            $uName  = $r['username'] ?: 'Student';
            $uEmail = $r['email'];

            $personalizedBody = str_replace(
                ['{{username}}', '{{app_name}}', '{{campus_name}}', '{{browse_url}}'],
                [$uName, APP_NAME, 'Campus', BASE_URL . 'pages/browse.php'],
                $bodyContent
            );
            $personalizedHeadline = str_replace(
                ['{{username}}', '{{app_name}}', '{{campus_name}}'],
                [$uName, APP_NAME, 'Campus'],
                $headline
            );

            $html = buildMarketingEmailHtml(
                $personalizedHeadline,
                nl2br($personalizedBody),
                $ctaUrl,
                $ctaText,
                generateUnsubscribeUrl($uEmail)
            );

            $res = sendMarketingEmail($uEmail, $subject, $html);
            if ($res['ok']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        // Update campaign record
        $upStmt = $pdo->prepare("
            UPDATE email_campaigns
            SET successful_sends = :succ,
                failed_sends = :fail,
                status = :status,
                sent_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':succ'   => $successCount,
            ':fail'   => $failCount,
            ':status' => ($successCount > 0 ? 'sent' : 'failed'),
            ':id'     => $campaignId,
        ]);

        logAdminAction($pdo, 'launch_email_campaign', 'campaign', $campaignId, [
            'subject'    => $subject,
            'audience'   => $audience,
            'recipients' => $totalRecipients,
            'success'    => $successCount,
            'failed'     => $failCount,
        ]);

        setFlash('success', "Campaign successfully dispatched! Sent to {$successCount} recipient(s) from marketing@campusmarketplace.site (" . ($failCount ? "{$failCount} failed" : "0 errors") . ").");
        redirect('campaigns.php');
    }
}

// Fetch past campaigns
$pastCampaigns = [];
try {
    $pastStmt = $pdo->query("
        SELECT c.*, u.username as admin_username
        FROM email_campaigns c
        LEFT JOIN users u ON c.admin_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $pastCampaigns = $pastStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pastCampaigns = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.campaign-studio-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

@media (max-width: 960px) {
    .campaign-studio-grid {
        grid-template-columns: 1fr;
    }
}

.sender-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(26, 127, 100, 0.08);
    border: 1px solid rgba(26, 127, 100, 0.25);
    padding: 0.4rem 0.8rem;
    border-radius: var(--radius-md);
    color: var(--primary);
    font-size: 0.85rem;
    font-weight: 600;
}

.audience-pill-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.audience-option {
    border: 1.5px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 0.85rem 1rem;
    cursor: pointer;
    background: var(--bg-main);
    transition: var(--transition);
}

.audience-option:hover {
    border-color: var(--primary);
    background: rgba(26, 127, 100, 0.03);
}

.audience-option input[type="radio"] {
    margin-right: 0.4rem;
}

.preset-chip {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    margin-right: 0.35rem;
    margin-bottom: 0.5rem;
}

.preset-chip:hover, .preset-chip.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

.preview-container {
    background: #f8fafc;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}

.preview-email-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.badge-sent {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.2rem 0.6rem;
    border-radius: var(--radius-full);
}
</style>

<div class="container mt-24 mb-16">
    <!-- Header Row -->
    <div class="flex justify-between items-end mb-8 flex-wrap gap-4">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Email Campaigns</div>
            <h1 class="mb-0">Marketing & Promotional Studio</h1>
        </div>
        <div class="sender-badge">
            <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            Sender: <?php echo htmlspecialchars(MARKETING_FROM_EMAIL); ?>
        </div>
    </div>

    <!-- Main Studio Grid -->
    <div class="campaign-studio-grid">
        
        <!-- Left Column: Campaign Composer -->
        <div class="glass-panel" style="padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 20px; height: 20px; color: var(--primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                Compose Promotional Campaign
            </h2>

            <!-- Template Presets -->
            <div class="mb-6">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Quick Presets</label>
                <div>
                    <button type="button" class="preset-chip" onclick="loadPreset('welcome')">🎓 Welcome Back / Semester Start</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('moveout')">📦 Move-Out / Textbook Sale</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('trending')">🔥 Weekly Hot Deals Digest</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('announcement')">📢 Platform Announcement</button>
                </div>
            </div>

            <form method="POST" id="campaignForm">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="template_preset" id="templatePreset" value="custom">

                <!-- Audience Target -->
                <div class="mb-6">
                    <label class="form-label" style="font-weight: 700;">1. Target Audience</label>
                    <div class="audience-pill-grid">
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="all" checked onchange="updateAudienceBadge()">
                            <strong>All Active</strong>
                            <div class="small text-muted"><?php echo $stats['all']; ?> students</div>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="sellers" onchange="updateAudienceBadge()">
                            <strong>Active Sellers</strong>
                            <div class="small text-muted"><?php echo $stats['sellers']; ?> users</div>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="buyers" onchange="updateAudienceBadge()">
                            <strong>Past Buyers</strong>
                            <div class="small text-muted"><?php echo $stats['buyers']; ?> users</div>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="inactive" onchange="updateAudienceBadge()">
                            <strong>Dormant (>30d)</strong>
                            <div class="small text-muted"><?php echo $stats['inactive']; ?> users</div>
                        </label>
                    </div>
                </div>

                <!-- Subject Line -->
                <div class="form-group mb-4">
                    <label for="subject" class="form-label" style="font-weight: 700;">2. Email Subject Line</label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Don't carry it home! Sell your textbooks & dorm gear" required oninput="syncPreview()">
                </div>

                <!-- Headline -->
                <div class="form-group mb-4">
                    <label for="headline" class="form-label" style="font-weight: 700;">3. Main Headline</label>
                    <input type="text" id="headline" name="headline" class="form-control" placeholder="e.g. Semester Move-Out & Textbook Clearance" required oninput="syncPreview()">
                </div>

                <!-- Body Content -->
                <div class="form-group mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <label for="bodyContent" class="form-label mb-0" style="font-weight: 700;">4. Message Body</label>
                        <span class="small text-muted">Tags: <code>{{username}}</code>, <code>{{campus_name}}</code></span>
                    </div>
                    <textarea id="bodyContent" name="body_content" class="form-control" rows="6" placeholder="Hi {{username}}, getting ready for the end of the term?..." required oninput="syncPreview()"></textarea>
                </div>

                <!-- Call to Action -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="ctaText" class="form-label" style="font-weight: 700;">CTA Button Text</label>
                        <input type="text" id="ctaText" name="cta_text" class="form-control" value="Browse Student Deals" oninput="syncPreview()">
                    </div>
                    <div>
                        <label for="ctaUrl" class="form-label" style="font-weight: 700;">Button Destination URL</label>
                        <input type="text" id="ctaUrl" name="cta_url" class="form-control" value="<?php echo BASE_URL . 'pages/browse.php'; ?>" oninput="syncPreview()">
                    </div>
                </div>

                <!-- Dispatch Actions -->
                <div style="border-top: 1px solid var(--border-light); padding-top: 1.5rem;" class="flex justify-between items-center flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <input type="email" name="test_email" class="form-control form-control-sm" style="width: 210px;" value="<?php echo htmlspecialchars($adminEmail); ?>" placeholder="Admin test email">
                        <button type="submit" name="action" value="send_test" class="btn btn-secondary btn-sm">
                            ✉ Send Test
                        </button>
                    </div>

                    <button type="submit" name="action" value="launch_campaign" class="btn btn-primary" onclick="return confirm('Ready to send this campaign blast to the selected audience?');">
                        🚀 Launch Campus Blast
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Live Email Preview -->
        <div>
            <div class="preview-container">
                <div class="flex justify-between items-center mb-3">
                    <span class="small uppercase font-bold text-muted" style="letter-spacing: 0.05em;">Live Email Preview</span>
                    <span class="badge" style="background: var(--bg-surface); font-size: 0.75rem;">From: marketing@campusmarketplace.site</span>
                </div>

                <div class="preview-email-card">
                    <!-- Brand Header -->
                    <div style="background: linear-gradient(135deg, #1a7f64 0%, #0d5440 100%); color: #fff; padding: 18px 24px; text-align: center;">
                        <div style="font-size: 18px; font-weight: 800;"><?php echo APP_NAME; ?></div>
                        <div style="font-size: 11px; opacity: 0.9;">Campus Student Marketplace</div>
                    </div>

                    <!-- Email Body -->
                    <div style="padding: 24px;">
                        <h3 id="prevHeadline" style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; line-height: 1.3;">
                            Semester Move-Out & Textbook Clearance
                        </h3>
                        <div id="prevBody" style="font-size: 13.5px; line-height: 1.6; color: #334155; min-height: 100px; white-space: pre-wrap;">
Hi Alex, getting ready for the end of the term? Don't leave your textbooks or dorm furniture behind! 

List your items on CampusMarket today to find buyers right on your campus.
                        </div>
                        <div style="text-align: center; margin: 24px 0 12px 0;">
                            <a id="prevBtn" href="#" style="display: inline-block; background: var(--primary); color: #ffffff; padding: 10px 22px; font-size: 13.5px; font-weight: 700; text-decoration: none; border-radius: 6px;">
                                Browse Student Deals →
                            </a>
                        </div>
                    </div>

                    <!-- Email Footer -->
                    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 20px; text-align: center; font-size: 10.5px; color: #64748b; line-height: 1.5;">
                        <div>CampusMarket · Buy & Sell Securely Across Campus</div>
                        <div>Sent by marketing@campusmarketplace.site · <span style="text-decoration: underline;">Unsubscribe</span></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Past Campaigns History -->
    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light); padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Past Broadcast Campaigns</h2>

        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Subject</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Audience</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Recipients</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Delivered</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Status</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pastCampaigns as $c): ?>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td class="p-3 font-bold text-main">
                            <?php echo htmlspecialchars($c['subject']); ?>
                            <div class="small text-muted font-normal"><?php echo htmlspecialchars(mb_substr($c['preview_text'] ?? '', 0, 60)); ?>...</div>
                        </td>
                        <td class="p-3">
                            <span class="badge" style="background: var(--bg-main); text-transform: capitalize; border: 1px solid var(--border-light); font-size: 0.75rem;">
                                <?php echo htmlspecialchars($c['audience_type']); ?>
                            </span>
                        </td>
                        <td class="p-3 font-semibold"><?php echo (int)$c['total_recipients']; ?></td>
                        <td class="p-3 text-success font-bold"><?php echo (int)$c['successful_sends']; ?></td>
                        <td class="p-3">
                            <span class="badge-sent"><?php echo strtoupper(htmlspecialchars($c['status'])); ?></span>
                        </td>
                        <td class="p-3 text-right text-muted small" style="font-family: monospace;">
                            <?php echo $c['created_at'] ? date('M d, Y • H:i', strtotime($c['created_at'])) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pastCampaigns)): ?>
                    <tr>
                        <td colspan="6" class="text-center p-8 text-muted">
                            No marketing campaigns sent yet. Compose and launch your first blast above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const presets = {
    welcome: {
        preset: 'welcome',
        subject: '🎒 Welcome to the New Term! Textbooks, Dorm Gear & Campus Deals',
        headline: 'Find Everything You Need for the New Semester',
        body: `Hi {{username}},

Welcome to the new academic term! Skip the high retail prices and find course textbooks, calculators, dorm furniture, and electronics from fellow students right on campus.

Have items from last term you no longer need? List them in seconds and make cash before the weekend!`,
        ctaText: 'Explore Campus Listings',
        ctaUrl: '<?php echo BASE_URL . 'pages/browse.php'; ?>'
    },
    moveout: {
        preset: 'moveout',
        subject: '📦 Moving Out? Turn Your Used Textbooks & Furniture into Cash',
        headline: 'Sell Your Dorm Essentials Before Leaving Campus',
        body: `Hi {{username}},

Packing up for break or graduation? Don't haul heavy textbooks, lamps, or desk appliances home with you!

List them on CampusMarket today to find student buyers on campus ready for fast handoffs.`,
        ctaText: 'Create a Quick Listing',
        ctaUrl: '<?php echo BASE_URL . 'pages/create_listing.php'; ?>'
    },
    trending: {
        preset: 'trending',
        subject: '🔥 Hot Student Deals This Week on CampusMarket',
        headline: 'This Week\'s Top Campus Marketplace Picks',
        body: `Hi {{username}},

Students in your campus community just posted fresh listings at great discounts — from textbooks and tech gadgets to room decor and study supplies.

Check out what is trending today before items are claimed!`,
        ctaText: 'Browse Trending Deals',
        ctaUrl: '<?php echo BASE_URL . 'pages/browse.php'; ?>'
    },
    announcement: {
        preset: 'announcement',
        subject: '📢 Important Update: New Features on CampusMarket',
        headline: 'CampusMarket Just Got Better!',
        body: `Hi {{username}},

We have introduced new updates to make buying and selling even safer and faster across your campus, including verified transaction tracking and multi-currency pricing.

Log in to your account and explore what is new!`,
        ctaText: 'Open CampusMarket',
        ctaUrl: '<?php echo BASE_URL; ?>'
    }
};

function loadPreset(key) {
    const data = presets[key];
    if (!data) return;

    document.getElementById('templatePreset').value = data.preset;
    document.getElementById('subject').value = data.subject;
    document.getElementById('headline').value = data.headline;
    document.getElementById('bodyContent').value = data.body;
    document.getElementById('ctaText').value = data.ctaText;
    document.getElementById('ctaUrl').value = data.ctaUrl;

    document.querySelectorAll('.preset-chip').forEach(el => el.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }

    syncPreview();
}

function syncPreview() {
    const headline = document.getElementById('headline').value || 'Main Campaign Headline';
    const body = document.getElementById('bodyContent').value || 'Your message body will appear here...';
    const cta = document.getElementById('ctaText').value || 'Explore Marketplace';

    document.getElementById('prevHeadline').innerText = headline.replace('{{username}}', 'Alex');
    document.getElementById('prevBody').innerText = body.replace('{{username}}', 'Alex').replace('{{campus_name}}', 'Campus');
    document.getElementById('prevBtn').innerText = cta + ' →';
}

function updateAudienceBadge() {
    // Optional dynamic UI badge updates
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
