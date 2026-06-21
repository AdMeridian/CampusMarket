<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/report_moderation.php';
requireLogin();

$pageTitle = __('report.page_title');

$contextProductId = (int)($_GET['product_id'] ?? $_POST['context_product_id'] ?? 0);
$contextUserId = (int)($_GET['user_id'] ?? $_POST['context_user_id'] ?? 0);
$currentUserId = currentUserId();

$contextProduct = null;
$contextUser = null;
$message = '';

if ($contextProductId > 0) {
    $stmt = $pdo->prepare('SELECT id, title, user_id, status FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $contextProductId]);
    $contextProduct = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$contextProduct) {
        $contextProductId = 0;
    }
}

if ($contextUserId > 0) {
    if ($contextUserId === $currentUserId) {
        $contextUserId = 0;
    } else {
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $contextUserId]);
        $contextUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$contextUser) {
            $contextUserId = 0;
        }
    }
}

$defaultLink = '';
if ($contextProductId > 0) {
    $defaultLink = rtrim(BASE_URL, '/') . '/pages/product.php?id=' . $contextProductId;
} elseif ($contextUserId > 0) {
    $defaultLink = rtrim(BASE_URL, '/') . '/pages/profile.php?id=' . $contextUserId;
}

$defaultIssueType = '';
if ($contextProductId > 0) {
    $defaultIssueType = 'scam';
} elseif ($contextUserId > 0) {
    $defaultIssueType = 'harassment';
}

function reportExtractProductId(string $link): ?int {
    if (preg_match('/product\.php\?id=(\d+)/i', $link, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

function reportExtractUserId(string $link): ?int {
    if (preg_match('/profile\.php\?id=(\d+)/i', $link, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $rate = rateLimitAllow($pdo, 'report:user:' . $currentUserId, 5, 3600);
    if (!$rate['allowed']) {
        $minutes = (int)ceil(($rate['retry_after'] ?? 3600) / 60);
        $message = '<div class="alert alert-danger report-alert report-alert--error">' . __('report.error_rate_limit', ['minutes' => $minutes]) . '</div>';
    } else {
        $issueType = sanitize($_POST['issue_type'] ?? '');
        $link = trim(sanitize($_POST['link'] ?? ''));
        $description = trim(sanitize($_POST['description'] ?? ''));
        $postedProductId = (int)($_POST['context_product_id'] ?? 0);
        $postedUserId = (int)($_POST['context_user_id'] ?? 0);

        $allowedTypes = ['scam', 'inappropriate', 'harassment', 'technical', 'other'];
        if (!in_array($issueType, $allowedTypes, true)) {
            $issueType = 'other';
        }

        if ($description === '') {
            $message = '<div class="alert alert-danger report-alert report-alert--error">' . __('report.error_description_required') . '</div>';
        } else {
            $productId = $postedProductId > 0 ? $postedProductId : $contextProductId;
            $reportedUserId = $postedUserId > 0 ? $postedUserId : $contextUserId;

            if ($link !== '') {
                $parsedProductId = reportExtractProductId($link);
                $parsedUserId = reportExtractUserId($link);
                if ($parsedProductId) {
                    $productId = $parsedProductId;
                }
                if ($parsedUserId) {
                    $reportedUserId = $parsedUserId;
                }
            }

            if ($reportedUserId === $currentUserId) {
                $reportedUserId = 0;
            }

            if ($productId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $productId]);
                if (!$stmt->fetchColumn()) {
                    $productId = 0;
                }
            } else {
                $productId = null;
            }

            if ($reportedUserId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $reportedUserId]);
                if (!$stmt->fetchColumn()) {
                    $reportedUserId = 0;
                }
            } else {
                $reportedUserId = null;
            }

            try {
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                $recentClause = $driver === 'pgsql'
                    ? "created_at > NOW() - INTERVAL '24 hours'"
                    : 'created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)';

                $dupSql = "
                    SELECT id FROM reports
                    WHERE reporter_id = :rid AND status = 'pending'
                      AND {$recentClause}
                ";
                $dupParams = [':rid' => $currentUserId];

                if ($productId) {
                    $dupSql .= ' AND product_id = :pid';
                    $dupParams[':pid'] = $productId;
                } elseif ($reportedUserId) {
                    $dupSql .= ' AND reported_user_id = :uid';
                    $dupParams[':uid'] = $reportedUserId;
                } else {
                    $dupSql .= ' AND product_id IS NULL AND reported_user_id IS NULL AND reason = :reason';
                    $dupParams[':reason'] = '[' . strtoupper($issueType) . '] ' . $description;
                }

                $dupSql .= ' LIMIT 1';
                $isDuplicate = false;
                try {
                    $dupStmt = $pdo->prepare($dupSql);
                    $dupStmt->execute($dupParams);
                    $isDuplicate = (bool)$dupStmt->fetchColumn();
                } catch (PDOException $dupEx) {
                    error_log('[report] duplicate check skipped: ' . $dupEx->getMessage());
                }

                if ($isDuplicate) {
                    $message = '<div class="alert alert-danger report-alert report-alert--error">' . __('report.error_duplicate') . '</div>';
                } else {
                    $reportId = reportInsert(
                        $pdo,
                        $currentUserId,
                        $productId ?: null,
                        $reportedUserId ?: null,
                        $issueType,
                        $description,
                        $link
                    );

                    $supportEmail = defined('SUPPORT_EMAIL')
                        ? SUPPORT_EMAIL
                        : (getenv('SUPPORT_EMAIL') ?: 'support@campusmarketplace.site');
                    $subject = __('report.email_subject', ['type' => ucfirst($issueType)]);
                    $username = $_SESSION['username'] ?? 'User';

                    $html = '<h2>' . htmlspecialchars(__('report.email_heading')) . '</h2>';
                    $html .= '<p><strong>' . htmlspecialchars(__('report.email_from')) . ':</strong> '
                        . htmlspecialchars($username) . ' (ID: ' . (int)$currentUserId . ')</p>';
                    $html .= '<p><strong>' . htmlspecialchars(__('report.email_type')) . ':</strong> '
                        . htmlspecialchars($issueType) . '</p>';
                    if ($link !== '') {
                        $html .= '<p><strong>' . htmlspecialchars(__('report.email_link')) . ':</strong> '
                            . htmlspecialchars($link) . '</p>';
                    }
                    if ($productId) {
                        $html .= '<p><strong>Product ID:</strong> ' . (int)$productId . '</p>';
                    }
                    if ($reportedUserId) {
                        $html .= '<p><strong>Reported user ID:</strong> ' . (int)$reportedUserId . '</p>';
                    }
                    $html .= '<p><strong>' . htmlspecialchars(__('report.email_description')) . ':</strong><br>'
                        . nl2br(htmlspecialchars($description)) . '</p>';
                    $html .= '<hr><p>Report ID: ' . $reportId . '</p>';

                    $emailResult = sendEmail($supportEmail, $subject, $html);
                    if (empty($emailResult['ok'])) {
                        error_log('[report] report submitted but email notification failed: ' . json_encode($emailResult));
                    }

                    reportEmailUser(
                        $pdo,
                        $currentUserId,
                        __('report.received_email_subject'),
                        __('report.received_email_title'),
                        __('report.received_email_body'),
                        rtrim(BASE_URL, '/') . '/pages/my_reports.php',
                        __('report.my_reports_cta')
                    );

                    setFlash('success', __('report.success'));
                    redirect(BASE_URL . 'pages/report.php' . ($contextProductId ? '?product_id=' . $contextProductId : ($contextUserId ? '?user_id=' . $contextUserId : '')));
                }
            } catch (Throwable $e) {
                error_log('[report] submit failed: ' . $e->getMessage());
                $message = '<div class="alert alert-danger report-alert report-alert--error">' . __('report.error_submit') . '</div>';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.report-alert {
    padding: 1rem;
    border-radius: var(--radius-md);
    margin-bottom: 1.5rem;
}
.report-alert--success {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.report-alert--error {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.report-context-banner {
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 0.85rem 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.92rem;
    color: var(--text-muted);
}
.report-context-banner strong {
    color: var(--text-main);
}
</style>

<div class="container py-4">
    <div class="card p-5" style="max-width: 600px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;"><?= __('report.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('report.subtitle') ?></p>

        <?= $message ?>

        <?php if ($contextProduct): ?>
            <div class="report-context-banner">
                <?= __('report.context_listing', ['title' => sanitize($contextProduct['title'])]) ?>
            </div>
        <?php elseif ($contextUser): ?>
            <div class="report-context-banner">
                <?= __('report.context_user', ['username' => sanitize($contextUser['username'])]) ?>
            </div>
        <?php endif; ?>

        <form action="report.php<?php
            echo $contextProductId ? '?product_id=' . $contextProductId : ($contextUserId ? '?user_id=' . $contextUserId : '');
        ?>" method="POST">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="context_product_id" value="<?= (int)$contextProductId ?>">
            <input type="hidden" name="context_user_id" value="<?= (int)$contextUserId ?>">

            <div class="mb-3">
                <label for="issue_type" class="form-label" style="font-weight: 600; color: var(--text-main);"><?= __('report.issue_type') ?></label>
                <select name="issue_type" id="issue_type" class="form-control premium-input" required style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%; background: var(--bg-surface); color: var(--text-main);">
                    <option value="scam" <?= $defaultIssueType === 'scam' ? 'selected' : '' ?>><?= __('report.type_scam') ?></option>
                    <option value="inappropriate" <?= $defaultIssueType === 'inappropriate' ? 'selected' : '' ?>><?= __('report.type_inappropriate') ?></option>
                    <option value="harassment" <?= $defaultIssueType === 'harassment' ? 'selected' : '' ?>><?= __('report.type_harassment') ?></option>
                    <option value="technical" <?= $defaultIssueType === 'technical' ? 'selected' : '' ?>><?= __('report.type_technical') ?></option>
                    <option value="other" <?= $defaultIssueType === 'other' ? 'selected' : '' ?>><?= __('report.type_other') ?></option>
                </select>
            </div>

            <?php if (!$contextProductId && !$contextUserId): ?>
            <div class="mb-3">
                <label for="link" class="form-label" style="font-weight: 600; color: var(--text-main);"><?= __('report.link_label') ?></label>
                <input type="url" name="link" id="link" class="form-control premium-input" placeholder="<?= __('report.link_placeholder') ?>" value="<?= sanitize($defaultLink) ?>" style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%;">
            </div>
            <?php else: ?>
            <input type="hidden" name="link" value="<?= sanitize($defaultLink) ?>">
            <?php endif; ?>

            <div class="mb-4">
                <label for="description" class="form-label" style="font-weight: 600; color: var(--text-main);"><?= __('report.description') ?></label>
                <textarea name="description" id="description" rows="5" class="form-control premium-input" required placeholder="<?= __('report.description_placeholder') ?>" style="border-radius: var(--radius-md); padding: 0.75rem; border: 1px solid var(--border-light); width: 100%;"><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.75rem; font-weight: 600;"><?= __('report.submit') ?></button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
