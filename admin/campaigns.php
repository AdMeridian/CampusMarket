<?php
// admin/campaigns.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/admin_audit.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = "Campaigns & Broadcast Studio";
$currentAdmin = currentUser() ?: [];
$adminEmail = $currentAdmin['email'] ?? ($_SESSION['email'] ?? '');

if (empty($adminEmail) && currentUserId()) {
    $uStmt = $pdo->prepare("SELECT email, username FROM users WHERE id = :id LIMIT 1");
    $uStmt->execute([':id' => currentUserId()]);
    $row = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $adminEmail = $row['email'] ?? '';
        if (empty($currentAdmin['username'])) {
            $currentAdmin['username'] = $row['username'] ?? 'Admin';
        }
    }
}

// Auto-migrate tables and columns for popup broadcasts
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id BIGSERIAL PRIMARY KEY,
            admin_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            subject VARCHAR(255) NOT NULL,
            headline VARCHAR(255) NULL,
            preview_text VARCHAR(255) NULL,
            audience_type VARCHAR(50) NOT NULL DEFAULT 'all',
            template_preset VARCHAR(50) NOT NULL DEFAULT 'custom',
            body_html TEXT NOT NULL,
            cta_text VARCHAR(100) NULL DEFAULT 'Explore Deals',
            cta_url TEXT NULL,
            image_url TEXT NULL,
            popup_theme VARCHAR(50) NOT NULL DEFAULT 'announcement',
            is_popup BOOLEAN NOT NULL DEFAULT FALSE,
            channel_email BOOLEAN NOT NULL DEFAULT TRUE,
            channel_bell BOOLEAN NOT NULL DEFAULT FALSE,
            expires_at TIMESTAMPTZ NULL,
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

    // Add columns if table already existed without them
    $colsToAdd = [
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS headline VARCHAR(255) NULL;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS cta_text VARCHAR(100) NULL DEFAULT 'Explore Deals';",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS cta_url TEXT NULL;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS image_url TEXT NULL;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS popup_theme VARCHAR(50) NOT NULL DEFAULT 'announcement';",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS is_popup BOOLEAN NOT NULL DEFAULT FALSE;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS channel_email BOOLEAN NOT NULL DEFAULT TRUE;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS channel_bell BOOLEAN NOT NULL DEFAULT FALSE;",
        "ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ NULL;"
    ];
    foreach ($colsToAdd as $colSql) {
        try { $pdo->exec($colSql); } catch (Exception $e) {}
    }
} catch (Exception $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                subject VARCHAR(255) NOT NULL,
                headline VARCHAR(255) NULL,
                preview_text VARCHAR(255) NULL,
                audience_type VARCHAR(50) NOT NULL DEFAULT 'all',
                template_preset VARCHAR(50) NOT NULL DEFAULT 'custom',
                body_html TEXT NOT NULL,
                cta_text VARCHAR(100) NULL DEFAULT 'Explore Deals',
                cta_url TEXT NULL,
                image_url TEXT NULL,
                popup_theme VARCHAR(50) NOT NULL DEFAULT 'announcement',
                is_popup TINYINT(1) NOT NULL DEFAULT 0,
                channel_email TINYINT(1) NOT NULL DEFAULT 1,
                channel_bell TINYINT(1) NOT NULL DEFAULT 0,
                expires_at TIMESTAMP NULL,
                total_recipients INT NOT NULL DEFAULT 0,
                successful_sends INT NOT NULL DEFAULT 0,
                failed_sends INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL
            ) ENGINE=InnoDB;
        ");
    } catch (Exception $e2) {}
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

    $subject      = trim($_POST['subject'] ?? '');
    $headline     = trim($_POST['headline'] ?? '');
    $bodyContent  = trim($_POST['body_content'] ?? '');
    $ctaText      = trim($_POST['cta_text'] ?? 'Explore Deals');
    $ctaUrl       = trim($_POST['cta_url'] ?? (BASE_URL . 'pages/browse.php'));
    $audience     = trim($_POST['audience_type'] ?? 'all');
    $preset       = trim($_POST['template_preset'] ?? 'custom');
    $popupTheme   = trim($_POST['popup_theme'] ?? 'announcement');
    $expiryDays   = (int)($_POST['expiry_days'] ?? 7);

    $channelEmail = !empty($_POST['channel_email']);
    $channelPopup = !empty($_POST['channel_popup']);
    $channelBell  = !empty($_POST['channel_bell']);

    // Process showcase image upload
    $imageUrl = trim($_POST['existing_image_url'] ?? '');
    if (!empty($_FILES['showcase_image']['name']) && $_FILES['showcase_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadImage($_FILES['showcase_image'], 'campaigns');
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['path'];
        } else {
            setFlash('error', 'Image upload failed: ' . ($uploadResult['error'] ?? 'Unknown error'));
            redirect('campaigns.php');
        }
    }

    if (empty($subject) || empty($headline) || empty($bodyContent)) {
        setFlash('error', 'Subject line, headline, and message body are required.');
        redirect('campaigns.php');
    }

    if (!$channelEmail && !$channelPopup && !$channelBell) {
        setFlash('error', 'Please select at least one delivery channel (Email, In-App Popup, or Bell Tray).');
        redirect('campaigns.php');
    }

    // Calculate expiration timestamp
    $expiresAt = null;
    if ($expiryDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    }

    if ($action === 'send_test') {
        $testRecipient = trim($_POST['test_email'] ?? $adminEmail);
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

        $testResults = [];

        if ($channelEmail) {
            if (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Invalid test email destination.');
                redirect('campaigns.php');
            }
            $html = buildMarketingEmailHtml($personalizedHeadline, nl2br($personalizedBody), $ctaUrl, $ctaText, generateUnsubscribeUrl($testRecipient));
            $res = sendMarketingEmail($testRecipient, "[TEST] " . $subject, $html);
            if ($res['ok']) {
                $testResults[] = "Test email sent to {$testRecipient}";
            } else {
                $testResults[] = "Email failed: " . ($res['error'] ?? 'Unknown mailer error');
            }
        }

        if ($channelBell && currentUserId()) {
            createNotification(
                $pdo,
                currentUserId(),
                'system',
                "📢 [TEST] " . $subject,
                $personalizedHeadline . " — " . mb_substr(strip_tags($personalizedBody), 0, 120),
                $ctaUrl
            );
            $testResults[] = "Test bell alert delivered to your notifications";
        }

        if ($channelPopup) {
            $testResults[] = "In-App Popup preview verified (check right live preview pane)";
        }

        setFlash('success', implode('. ', $testResults));
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
            $recipients = $pdo->query("
                SELECT u.id, u.username, u.email FROM users u
                WHERE u.account_status = 'active'
                  AND LOWER(u.email) NOT IN (SELECT LOWER(email) FROM email_unsubscribes)
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $totalRecipients = count($recipients);
        if ($totalRecipients === 0 && ($channelEmail || $channelBell)) {
            setFlash('error', 'No active recipients found for the selected audience segment.');
            redirect('campaigns.php');
        }

        // Insert campaign record
        $campStmt = $pdo->prepare("
            INSERT INTO email_campaigns (
                admin_id, subject, headline, preview_text, audience_type, template_preset,
                body_html, cta_text, cta_url, image_url, popup_theme, is_popup,
                channel_email, channel_bell, expires_at, total_recipients, status, created_at
            ) VALUES (
                :admin_id, :subject, :headline, :preview_text, :audience_type, :template_preset,
                :body_html, :cta_text, :cta_url, :image_url, :popup_theme, :is_popup,
                :channel_email, :channel_bell, :expires_at, :total_recipients, 'sending', NOW()
            )
        ");
        $campStmt->execute([
            ':admin_id'         => currentUserId(),
            ':subject'          => $subject,
            ':headline'         => $headline,
            ':preview_text'     => mb_substr(strip_tags($bodyContent), 0, 120),
            ':audience_type'    => $audience,
            ':template_preset'  => $preset,
            ':body_html'        => $bodyContent,
            ':cta_text'         => $ctaText,
            ':cta_url'          => $ctaUrl,
            ':image_url'        => $imageUrl ?: null,
            ':popup_theme'      => $popupTheme,
            ':is_popup'         => $channelPopup ? 1 : 0,
            ':channel_email'    => $channelEmail ? 1 : 0,
            ':channel_bell'     => $channelBell ? 1 : 0,
            ':expires_at'       => $expiresAt,
            ':total_recipients' => $totalRecipients,
        ]);
        $campaignId = (int)$pdo->lastInsertId();

        // Dispatch loop for direct channels
        $successEmailCount = 0;
        $failEmailCount = 0;
        $bellCount = 0;

        foreach ($recipients as $r) {
            $uid    = (int)$r['id'];
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

            // Channel 1: Email
            if ($channelEmail) {
                $html = buildMarketingEmailHtml(
                    $personalizedHeadline,
                    nl2br($personalizedBody),
                    $ctaUrl,
                    $ctaText,
                    generateUnsubscribeUrl($uEmail)
                );

                $res = sendMarketingEmail($uEmail, $subject, $html);
                if ($res['ok']) {
                    $successEmailCount++;
                } else {
                    $failEmailCount++;
                }
            }

            // Channel 2: Bell Notification
            if ($channelBell) {
                createNotification(
                    $pdo,
                    $uid,
                    'system',
                    "📢 " . $subject,
                    $personalizedHeadline . " — " . mb_substr(strip_tags($personalizedBody), 0, 120),
                    $ctaUrl
                );
                $bellCount++;
            }
        }

        // Update campaign record status
        $upStmt = $pdo->prepare("
            UPDATE email_campaigns
            SET successful_sends = :succ,
                failed_sends = :fail,
                status = 'sent',
                sent_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':succ'   => ($channelEmail ? $successEmailCount : ($channelBell ? $bellCount : 1)),
            ':fail'   => $failEmailCount,
            ':id'     => $campaignId,
        ]);

        logAdminAction($pdo, 'launch_campaign_broadcast', 'campaign', $campaignId, [
            'audience'       => $audience,
            'recipients'     => $totalRecipients,
            'channel_email'  => $channelEmail,
            'channel_popup'  => $channelPopup,
            'channel_bell'   => $channelBell,
            'popup_theme'    => $popupTheme,
            'has_image'      => !empty($imageUrl),
            'email_sent'     => $successEmailCount,
            'bell_sent'      => $bellCount,
        ]);

        $channelsUsed = [];
        if ($channelEmail) $channelsUsed[] = "{$successEmailCount} emails delivered";
        if ($channelPopup) $channelsUsed[] = "In-App Popup live for active students";
        if ($channelBell)  $channelsUsed[] = "{$bellCount} bell notifications posted";

        setFlash('success', "🚀 Broadcast published successfully! (" . implode(' · ', $channelsUsed) . ")");
        redirect('campaigns.php');
    }
}

// Fetch past campaigns
$pastCampaigns = [];
try {
    $pastCampaigns = $pdo->query("
        SELECT * FROM email_campaigns
        ORDER BY created_at DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.campaign-studio-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 1.75rem;
    align-items: start;
}

@media (max-width: 1024px) {
    .campaign-studio-grid {
        grid-template-columns: 1fr;
    }
}

.channel-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--border-light);
    background: var(--bg-surface);
    cursor: pointer;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 180px;
}

.channel-card:has(input:checked) {
    border-color: var(--primary);
    background: rgba(26, 127, 100, 0.05);
}

.channel-card input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: var(--primary);
}

.audience-pill-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.65rem;
}

.audience-option {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 0.65rem 0.85rem;
    text-align: center;
    cursor: pointer;
    background: var(--bg-surface);
    transition: all 0.15s ease;
}

.audience-option:has(input:checked) {
    border-color: var(--primary);
    background: rgba(26, 127, 100, 0.08);
    font-weight: 700;
}

.audience-option input[type="radio"] {
    display: none;
}

.preset-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-full);
    padding: 0.35rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-main);
    cursor: pointer;
    margin: 0 0.4rem 0.5rem 0;
    transition: all 0.15s;
}

.preset-chip:hover, .preset-chip.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

/* Studio Preview Tabs */
.preview-tabs-bar {
    display: flex;
    gap: 0.4rem;
    background: var(--bg-surface);
    padding: 0.35rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    margin-bottom: 1rem;
}

.preview-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--radius-md);
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.15s;
}

.preview-tab-btn.active {
    background: var(--primary);
    color: #ffffff;
    box-shadow: var(--shadow-sm);
}

/* Solid Popup Card Preview (NO glassmorphism) */
.preview-popup-backdrop {
    background: rgba(15, 23, 42, 0.65);
    border-radius: var(--radius-lg);
    padding: 2rem 1.25rem;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 480px;
}

.preview-popup-card {
    background: var(--bg-surface);
    color: var(--text-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-xl);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4);
    max-width: 380px;
    width: 100%;
    overflow: hidden;
    position: relative;
    animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.preview-popup-hero {
    width: 100%;
    height: 160px;
    object-fit: cover;
    display: block;
    background: #0f172a;
}

.preview-popup-content {
    padding: 1.4rem;
}

.popup-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.55rem;
    border-radius: var(--radius-full);
    margin-bottom: 0.65rem;
}

.popup-badge-announcement { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
.popup-badge-deals { background: rgba(245, 158, 11, 0.15); color: #d97706; }
.popup-badge-campus { background: rgba(16, 185, 129, 0.15); color: #059669; }
.popup-badge-perk { background: rgba(139, 92, 246, 0.15); color: #7c3aed; }

/* Email preview card */
.preview-email-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}

/* Bell tray preview card */
.preview-bell-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1rem;
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    box-shadow: var(--shadow-sm);
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
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Broadcast Studio</div>
            <h1 class="mb-0" style="font-family: 'Outfit', sans-serif; font-weight: 800;">Campaigns & Broadcast Studio</h1>
        </div>
        <div class="sender-badge" style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--bg-surface); border: 1px solid var(--border-light); padding: 0.45rem 0.85rem; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">
            <svg style="width: 15px; height: 15px; color: var(--primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            Sender: <?php echo htmlspecialchars(MARKETING_FROM_EMAIL); ?>
        </div>
    </div>

    <!-- Main Studio Grid -->
    <div class="campaign-studio-grid">
        
        <!-- Left Column: Broadcast Composer -->
        <div class="glass-panel" style="padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 20px; height: 20px; color: var(--primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                Compose Multi-Channel Broadcast
            </h2>

            <!-- Quick Template Presets -->
            <div class="mb-6">
                <label class="form-label" style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.04em;">Quick Presets</label>
                <div>
                    <button type="button" class="preset-chip" onclick="loadPreset('welcome')">🎓 Welcome & Semester Start</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('moveout')">📦 Move-Out & Textbook Sale</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('trending')">🔥 Weekly Hot Deals</button>
                    <button type="button" class="preset-chip" onclick="loadPreset('announcement')">📢 Platform Announcement</button>
                </div>
            </div>

            <form method="POST" id="campaignForm" enctype="multipart/form-data">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="template_preset" id="templatePreset" value="custom">

                <!-- 1. Delivery Channels Selection -->
                <div class="mb-6">
                    <label class="form-label" style="font-weight: 700;">1. Delivery Channels</label>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <label class="channel-card">
                            <input type="checkbox" name="channel_popup" id="channelPopupCheck" value="1" checked onchange="toggleChannelControls()">
                            <div>
                                <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">🪟 In-App Popup Modal</strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; line-height: 1.3;">Solid card popup for active students</span>
                            </div>
                        </label>
                        <label class="channel-card">
                            <input type="checkbox" name="channel_email" id="channelEmailCheck" value="1" checked onchange="toggleChannelControls()">
                            <div>
                                <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">✉️ Email Blast</strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; line-height: 1.3;">Formatted inbox campaign</span>
                            </div>
                        </label>
                        <label class="channel-card">
                            <input type="checkbox" name="channel_bell" id="channelBellCheck" value="1" onchange="toggleChannelControls()">
                            <div>
                                <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">🔔 Notification Bell</strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; line-height: 1.3;">Saved in bell notification tray</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. Target Audience -->
                <div class="mb-6">
                    <label class="form-label" style="font-weight: 700;">2. Target Audience</label>
                    <div class="audience-pill-grid">
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="all" checked onchange="syncPreview()">
                            <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">All Students</strong>
                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo $stats['all']; ?> active</span>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="sellers" onchange="syncPreview()">
                            <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">Active Sellers</strong>
                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo $stats['sellers']; ?> users</span>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="buyers" onchange="syncPreview()">
                            <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">Past Buyers</strong>
                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo $stats['buyers']; ?> users</span>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="audience_type" value="inactive" onchange="syncPreview()">
                            <strong style="display: block; font-size: 0.88rem; color: var(--text-main);">Dormant (>30d)</strong>
                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo $stats['inactive']; ?> users</span>
                        </label>
                    </div>
                </div>

                <!-- 3. Subject / Title -->
                <div class="form-group mb-4">
                    <label for="subject" class="form-label" style="font-weight: 700;">3. Subject Line / Main Title</label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. 🎓 Semester Move-Out & Textbook Clearance" required oninput="syncPreview()">
                </div>

                <!-- 4. Headline -->
                <div class="form-group mb-4">
                    <label for="headline" class="form-label" style="font-weight: 700;">4. Subtitle / Headline</label>
                    <input type="text" id="headline" name="headline" class="form-control" placeholder="e.g. Find Course Books, Dorm Gear & Student Deals" required oninput="syncPreview()">
                </div>

                <!-- 5. Message Body -->
                <div class="form-group mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <label for="bodyContent" class="form-label mb-0" style="font-weight: 700;">5. Message Body</label>
                        <span class="small text-muted">Tags: <code>{{username}}</code>, <code>{{campus_name}}</code></span>
                    </div>
                    <textarea id="bodyContent" name="body_content" class="form-control" rows="5" placeholder="Hi {{username}}, getting ready for the new semester?..." required oninput="syncPreview()"></textarea>
                </div>

                <!-- 6. Call to Action -->
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label for="ctaText" class="form-label" style="font-weight: 700;">CTA Button Text</label>
                        <input type="text" id="ctaText" name="cta_text" class="form-control" value="Explore Deals" oninput="syncPreview()">
                    </div>
                    <div>
                        <label for="ctaUrl" class="form-label" style="font-weight: 700;">Button URL</label>
                        <input type="text" id="ctaUrl" name="cta_url" class="form-control" value="<?php echo BASE_URL . 'pages/browse.php'; ?>" oninput="syncPreview()">
                    </div>
                </div>

                <!-- 7. Popup Specific Options (Hero Image, Theme, Expiry) -->
                <div id="popupOptionsBox" class="mb-6 p-4" style="background: var(--bg-surface); border: 1.5px solid var(--border-light); border-radius: var(--radius-lg);">
                    <div class="flex items-center gap-2 mb-3">
                        <span style="font-size: 1.1rem;">🪟</span>
                        <strong style="font-size: 0.95rem; color: var(--text-main);">In-App Popup Settings & Showcase Image</strong>
                    </div>

                    <!-- Image / Banner Upload -->
                    <div class="form-group mb-4">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">
                            Showcase Hero Banner (Optional)
                        </label>
                        <input type="file" id="showcaseImage" name="showcase_image" accept="image/png, image/jpeg, image/webp" class="form-control form-control-sm" onchange="handleImageSelected(this)">
                        <input type="hidden" name="existing_image_url" id="existingImageUrl" value="">
                        <div class="small text-muted mt-1">
                            Recommended: 16:9 or 2:1 aspect ratio (JPG, PNG, WebP). If omitted, a themed badge header will be used.
                        </div>
                    </div>

                    <!-- Popup Theme & Expiry -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="popupTheme" class="form-label" style="font-weight: 600; font-size: 0.85rem;">Popup Theme Badge</label>
                            <select id="popupTheme" name="popup_theme" class="form-control form-control-sm" onchange="syncPreview()">
                                <option value="announcement">📢 Platform Announcement</option>
                                <option value="deals">🔥 Hot Deals & Flash Sale</option>
                                <option value="campus">🎓 Campus & Semester</option>
                                <option value="perk">🎁 Special Perk / Giveaway</option>
                            </select>
                        </div>
                        <div>
                            <label for="expiryDays" class="form-label" style="font-weight: 600; font-size: 0.85rem;">Broadcast Active For</label>
                            <select id="expiryDays" name="expiry_days" class="form-control form-control-sm">
                                <option value="2">48 Hours</option>
                                <option value="7" selected>7 Days</option>
                                <option value="14">14 Days</option>
                                <option value="30">30 Days</option>
                                <option value="0">No Expiry (Until Deleted)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dispatch Actions -->
                <div style="border-top: 1px solid var(--border-light); padding-top: 1.5rem;" class="flex justify-between items-center flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <input type="email" name="test_email" class="form-control form-control-sm" style="width: 200px;" value="<?php echo htmlspecialchars($adminEmail); ?>" placeholder="Admin test email">
                        <button type="submit" name="action" value="send_test" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-md);">
                            ✉ Send Test
                        </button>
                    </div>

                    <button type="submit" name="action" value="launch_campaign" class="btn btn-primary" style="border-radius: var(--radius-md); font-weight: 700; padding: 0.65rem 1.4rem;" onclick="return confirm('Ready to launch this broadcast to your campus audience?');">
                        🚀 Launch Broadcast
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Multi-View Live Preview Studio -->
        <div>
            <!-- View Selector Tabs -->
            <div class="preview-tabs-bar">
                <button type="button" class="preview-tab-btn active" id="tabBtnPopup" onclick="switchPreviewTab('popup')">
                    <span>🪟 In-App Popup</span>
                </button>
                <button type="button" class="preview-tab-btn" id="tabBtnEmail" onclick="switchPreviewTab('email')">
                    <span>✉️ Email Client</span>
                </button>
                <button type="button" class="preview-tab-btn" id="tabBtnBell" onclick="switchPreviewTab('bell')">
                    <span>🔔 Bell Alert</span>
                </button>
            </div>

            <!-- View 1: Solid In-App Popup Preview (NO glassmorphism) -->
            <div id="previewPanePopup">
                <div class="preview-popup-backdrop">
                    <div class="preview-popup-card">
                        <!-- Hero Image Preview -->
                        <img id="prevPopupImage" src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=80" alt="Showcase Banner" class="preview-popup-hero">

                        <!-- Popup Body -->
                        <div class="preview-popup-content">
                            <div class="flex justify-between items-center mb-2">
                                <span id="prevPopupBadge" class="popup-badge popup-badge-announcement">
                                    📢 Platform Announcement
                                </span>
                                <span style="font-size: 1.2rem; color: var(--text-muted); line-height: 1; cursor: pointer;">&times;</span>
                            </div>

                            <h3 id="prevPopupHeadline" style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; line-height: 1.3;">
                                Find Everything You Need for the New Semester
                            </h3>

                            <p id="prevPopupBody" style="font-size: 0.85rem; line-height: 1.55; color: var(--text-muted); margin: 0 0 1.25rem 0; white-space: pre-wrap;">
Hi Alex, welcome to the new term! Skip the high retail prices and find course textbooks and dorm gear right on campus.
                            </p>

                            <div style="display: flex; gap: 0.5rem;">
                                <a id="prevPopupBtn" href="#" class="btn btn-primary" style="flex: 1; justify-content: center; border-radius: var(--radius-md); font-weight: 700; font-size: 0.85rem; text-decoration: none; padding: 0.6rem 0.75rem;">
                                    Explore Deals →
                                </a>
                                <button type="button" class="btn btn-secondary" style="border-radius: var(--radius-md); font-size: 0.82rem; padding: 0.6rem 0.85rem;">
                                    Maybe later
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center small text-muted mt-2">
                    Simulating solid card popup dialog displayed over campus marketplace pages.
                </div>
            </div>

            <!-- View 2: Email Client Preview -->
            <div id="previewPaneEmail" style="display: none;">
                <div class="preview-email-card">
                    <div style="background: linear-gradient(135deg, #1a7f64 0%, #0d5440 100%); color: #fff; padding: 18px 24px; text-align: center;">
                        <div style="font-size: 18px; font-weight: 800;"><?php echo APP_NAME; ?></div>
                        <div style="font-size: 11px; opacity: 0.9;">Campus Student Marketplace</div>
                    </div>
                    <div style="padding: 24px;">
                        <h3 id="prevEmailHeadline" style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; line-height: 1.3;">
                            Semester Move-Out & Textbook Clearance
                        </h3>
                        <div id="prevEmailBody" style="font-size: 13.5px; line-height: 1.6; color: #334155; min-height: 100px; white-space: pre-wrap;">
Hi Alex, getting ready for the end of the term? Don't leave your textbooks or dorm furniture behind! 

List your items on CampusMarket today to find buyers right on your campus.
                        </div>
                        <div style="text-align: center; margin: 24px 0 12px 0;">
                            <a id="prevEmailBtn" href="#" style="display: inline-block; background: var(--primary); color: #ffffff; padding: 10px 22px; font-size: 13.5px; font-weight: 700; text-decoration: none; border-radius: 6px;">
                                Explore Deals →
                            </a>
                        </div>
                    </div>
                    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 20px; text-align: center; font-size: 10.5px; color: #64748b; line-height: 1.5;">
                        <div>CampusMarket · Buy & Sell Securely Across Campus</div>
                        <div>Sent by marketing@campusmarketplace.site · <span style="text-decoration: underline;">Unsubscribe</span></div>
                    </div>
                </div>
            </div>

            <!-- View 3: Bell Notification Preview -->
            <div id="previewPaneBell" style="display: none;">
                <div class="preview-bell-card">
                    <div style="width: 38px; height: 38px; border-radius: var(--radius-full); background: rgba(26, 127, 100, 0.12); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        📢
                    </div>
                    <div style="flex: 1;">
                        <strong id="prevBellSubject" style="display: block; font-size: 0.9rem; color: var(--text-main); margin-bottom: 2px;">
                            Semester Move-Out & Deals
                        </strong>
                        <p id="prevBellSnippet" style="margin: 0 0 0.35rem 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                            Find course textbooks, dorm gear and student deals right on campus.
                        </p>
                        <span style="font-size: 0.72rem; color: var(--text-muted);">Just now</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Past Campaigns History -->
    <div class="glass-panel table-responsive mt-12" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light); padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Past Broadcast Campaigns</h2>

        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: var(--bg-surface);">
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Subject / Headline</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Channels</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Audience</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Recipients</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Status</th>
                    <th class="p-3 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pastCampaigns as $c): ?>
                    <tr style="border-bottom: 1px solid var(--border-light);">
                        <td class="p-3 font-bold text-main">
                            <?php echo htmlspecialchars($c['subject']); ?>
                            <div class="small text-muted font-normal"><?php echo htmlspecialchars(mb_substr($c['preview_text'] ?? ($c['headline'] ?? ''), 0, 60)); ?>...</div>
                        </td>
                        <td class="p-3">
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                <?php if (!empty($c['is_popup'])): ?>
                                    <span style="font-size: 0.72rem; font-weight: 700; background: rgba(59, 130, 246, 0.12); color: #2563eb; padding: 0.15rem 0.45rem; border-radius: 4px;">🪟 Popup</span>
                                <?php endif; ?>
                                <?php if (!empty($c['channel_email'])): ?>
                                    <span style="font-size: 0.72rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #059669; padding: 0.15rem 0.45rem; border-radius: 4px;">✉️ Email</span>
                                <?php endif; ?>
                                <?php if (!empty($c['channel_bell'])): ?>
                                    <span style="font-size: 0.72rem; font-weight: 700; background: rgba(245, 158, 11, 0.12); color: #d97706; padding: 0.15rem 0.45rem; border-radius: 4px;">🔔 Bell</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="badge" style="background: var(--bg-surface); text-transform: capitalize; border: 1px solid var(--border-light); font-size: 0.75rem;">
                                <?php echo htmlspecialchars($c['audience_type'] ?? 'all'); ?>
                            </span>
                        </td>
                        <td class="p-3 font-semibold"><?php echo (int)($c['total_recipients'] ?? 0); ?></td>
                        <td class="p-3">
                            <span class="badge-sent"><?php echo strtoupper(htmlspecialchars($c['status'] ?? 'sent')); ?></span>
                        </td>
                        <td class="p-3 text-right text-muted small" style="font-family: monospace;">
                            <?php echo !empty($c['created_at']) ? date('M d, Y • H:i', strtotime($c['created_at'])) : '—'; ?>
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
        subject: '🎒 Welcome to the New Term! Textbooks, Dorm Gear & Deals',
        headline: 'Find Everything You Need for the New Semester',
        body: `Hi {{username}},

Welcome to the new academic term! Skip high retail prices and find course textbooks, calculators, dorm furniture, and electronics from fellow students right on campus.

Have items from last term you no longer need? List them in seconds and make cash before the weekend!`,
        ctaText: 'Explore Campus Listings',
        ctaUrl: '<?php echo BASE_URL . 'pages/browse.php'; ?>',
        theme: 'campus',
        image: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=80'
    },
    moveout: {
        preset: 'moveout',
        subject: '📦 Moving Out? Turn Your Used Textbooks into Cash',
        headline: 'Sell Your Dorm Essentials Before Leaving Campus',
        body: `Hi {{username}},

Packing up for break or graduation? Don't haul heavy textbooks, lamps, or desk appliances home with you!

List them on CampusMarket today to find student buyers on campus ready for fast handoffs.`,
        ctaText: 'Create a Quick Listing',
        ctaUrl: '<?php echo BASE_URL . 'pages/create_listing.php'; ?>',
        theme: 'deals',
        image: 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&auto=format&fit=crop&q=80'
    },
    trending: {
        preset: 'trending',
        subject: '🔥 Hot Student Deals This Week on CampusMarket',
        headline: 'This Week\'s Top Campus Marketplace Picks',
        body: `Hi {{username}},

Students in your campus community just posted fresh listings at great discounts — from textbooks and tech gadgets to room decor and study supplies.

Check out what is trending today before items are claimed!`,
        ctaText: 'Browse Trending Deals',
        ctaUrl: '<?php echo BASE_URL . 'pages/browse.php'; ?>',
        theme: 'deals',
        image: 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=800&auto=format&fit=crop&q=80'
    },
    announcement: {
        preset: 'announcement',
        subject: '📢 Important Update: New Features on CampusMarket',
        headline: 'CampusMarket Just Got Better!',
        body: `Hi {{username}},

We have introduced new updates to make buying and selling even safer and faster across your campus, including verified transaction tracking and multi-currency pricing.

Log in to your account and explore what is new!`,
        ctaText: 'Open CampusMarket',
        ctaUrl: '<?php echo BASE_URL; ?>',
        theme: 'announcement',
        image: ''
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
    if (data.theme) document.getElementById('popupTheme').value = data.theme;

    if (data.image) {
        document.getElementById('existingImageUrl').value = data.image;
        document.getElementById('prevPopupImage').src = data.image;
        document.getElementById('prevPopupImage').style.display = 'block';
    } else {
        document.getElementById('existingImageUrl').value = '';
        document.getElementById('prevPopupImage').style.display = 'none';
    }

    document.querySelectorAll('.preset-chip').forEach(el => el.classList.remove('active'));
    if (event && event.target) {
        event.target.classList.add('active');
    }

    syncPreview();
}

function handleImageSelected(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('prevPopupImage');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function switchPreviewTab(tab) {
    const tabs = ['popup', 'email', 'bell'];
    tabs.forEach(t => {
        const pane = document.getElementById('previewPane' + t.charAt(0).toUpperCase() + t.slice(1));
        const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (t === tab) {
            if (pane) pane.style.display = 'block';
            if (btn) btn.classList.add('active');
        } else {
            if (pane) pane.style.display = 'none';
            if (btn) btn.classList.remove('active');
        }
    });
}

function toggleChannelControls() {
    const isPopup = document.getElementById('channelPopupCheck').checked;
    const popupBox = document.getElementById('popupOptionsBox');
    if (popupBox) {
        popupBox.style.opacity = isPopup ? '1' : '0.5';
    }
    if (isPopup) {
        switchPreviewTab('popup');
    } else if (document.getElementById('channelEmailCheck').checked) {
        switchPreviewTab('email');
    } else {
        switchPreviewTab('bell');
    }
}

function syncPreview() {
    const subject  = document.getElementById('subject').value || '📢 Platform Broadcast Title';
    const headline = document.getElementById('headline').value || 'Main Campaign Headline';
    const body     = document.getElementById('bodyContent').value || 'Your message body will appear here...';
    const cta      = document.getElementById('ctaText').value || 'Explore Deals';
    const theme    = document.getElementById('popupTheme').value || 'announcement';

    const personalizedHead = headline.replace('{{username}}', 'Alex');
    const personalizedBody = body.replace('{{username}}', 'Alex').replace('{{campus_name}}', 'Campus');

    // 1. Popup View Sync
    document.getElementById('prevPopupHeadline').innerText = personalizedHead;
    document.getElementById('prevPopupBody').innerText = personalizedBody;
    document.getElementById('prevPopupBtn').innerText = cta + ' →';

    const badge = document.getElementById('prevPopupBadge');
    badge.className = 'popup-badge popup-badge-' + theme;
    const themeLabels = {
        announcement: '📢 Platform Announcement',
        deals: '🔥 Hot Deals & Flash Sale',
        campus: '🎓 Campus & Semester',
        perk: '🎁 Special Perk / Giveaway'
    };
    badge.innerText = themeLabels[theme] || '📢 Announcement';

    // 2. Email View Sync
    document.getElementById('prevEmailHeadline').innerText = personalizedHead;
    document.getElementById('prevEmailBody').innerText = personalizedBody;
    document.getElementById('prevEmailBtn').innerText = cta + ' →';

    // 3. Bell View Sync
    document.getElementById('prevBellSubject').innerText = subject;
    document.getElementById('prevBellSnippet').innerText = personalizedHead + ' — ' + personalizedBody.substring(0, 90) + '...';
}

document.addEventListener('DOMContentLoaded', function() {
    syncPreview();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
