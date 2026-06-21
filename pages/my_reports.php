<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/report_moderation.php';
requireLogin();

$pageTitle = __('report.my_reports_title');
$userId = currentUserId();
$reports = reportsListForUser($pdo, $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.my-reports-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.my-reports-status {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}
.my-reports-status--pending { background: #fef3c7; color: #92400e; }
.my-reports-status--reviewed { background: #dcfce7; color: #166534; }
.my-reports-status--dismissed { background: #f1f5f9; color: #475569; }
.my-reports-meta {
    color: var(--text-muted);
    font-size: 0.88rem;
    margin-top: 0.35rem;
}
</style>

<div class="container py-4">
    <div style="max-width: 760px; margin: 0 auto;">
        <div class="flex justify-between items-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 class="mb-1" style="font-weight: 800;"><?= __('report.my_reports_title') ?></h1>
                <p class="text-muted mb-0"><?= __('report.my_reports_subtitle') ?></p>
            </div>
            <a href="<?= BASE_URL ?>pages/report.php" class="btn btn-primary"><?= __('report.title') ?></a>
        </div>

        <?php if (empty($reports)): ?>
            <div class="my-reports-card text-center text-muted">
                <p class="mb-3"><?= __('report.my_reports_empty') ?></p>
                <a href="<?= BASE_URL ?>pages/report.php" class="btn btn-secondary"><?= __('report.submit') ?></a>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r):
                $status = (string)($r['status'] ?? 'pending');
                $statusClass = in_array($status, ['pending', 'reviewed', 'dismissed'], true) ? $status : 'pending';
            ?>
            <article class="my-reports-card">
                <div class="flex justify-between items-start gap-3" style="flex-wrap: wrap;">
                    <div>
                        <span class="report-type-badge"><?= sanitize(reportIssueTypeLabel($r)) ?></span>
                        <span class="my-reports-status my-reports-status--<?= sanitize($statusClass) ?>">
                            <?= __('report.status_' . $statusClass) ?>
                        </span>
                        <?php if (!empty($r['resolution']) && $status !== 'pending'): ?>
                            <span class="text-muted small">· <?= sanitize((string)$r['resolution']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted small"><?= timeAgo($r['created_at']) ?></span>
                </div>

                <p style="margin: 0.85rem 0 0; white-space: pre-wrap;"><?= sanitize(reportDisplayText($r)) ?></p>

                <div class="my-reports-meta">
                    <?php if (!empty($r['product_id'])): ?>
                        <?= __('report.my_reports_target_listing', ['title' => sanitize($r['product_title'] ?? __('admin.report_listing_fallback'))]) ?>
                        · <a href="<?= BASE_URL ?>pages/product.php?id=<?= (int)$r['product_id'] ?>"><?= __('admin.report_view_listing') ?></a>
                    <?php elseif (!empty($r['reported_user_id'])): ?>
                        <?= __('report.my_reports_target_user', ['username' => sanitize($r['reported_username'] ?? __('admin.report_user_fallback'))]) ?>
                        · <a href="<?= BASE_URL ?>pages/profile.php?id=<?= (int)$r['reported_user_id'] ?>"><?= __('admin.report_view_profile') ?></a>
                    <?php else: ?>
                        <?= __('report.my_reports_target_general') ?>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
