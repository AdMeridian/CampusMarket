<?php
// admin/reports.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/admin_audit.php';
require_once __DIR__ . '/../includes/report_moderation.php';

$pageTitle = __('admin.moderation_queue');
$currentAdminId = currentUserId();

$tab = sanitize($_GET['tab'] ?? 'pending');
$allowedTabs = ['pending', 'reviewed', 'dismissed'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'pending';
}

$view = sanitize($_GET['view'] ?? 'grouped');
if (!in_array($view, ['grouped', 'flat'], true)) {
    $view = 'grouped';
}
if ($tab !== 'pending') {
    $view = 'flat';
}

$typeFilter = sanitize($_GET['type'] ?? '');
if ($typeFilter !== '' && !in_array($typeFilter, reportIssueTypes(), true)) {
    $typeFilter = '';
}

function reportApplyModerationAction(
    PDO $pdo,
    array $report,
    string $action,
    int $currentAdminId,
    bool $resolveSiblings
): bool {
    $reportId = (int)$report['id'];
    $targetUserId = reportTargetUserId($report);
    $productId = (int)($report['product_id'] ?? 0);

    if ($action === 'dismiss') {
        reportSetStatus($pdo, $reportId, 'dismissed', 'dismissed', $currentAdminId);
        reportNotifyReporter($pdo, $report, __('admin.report_reporter_dismissed_title'), __('admin.report_reporter_dismissed_body'));
        if ($resolveSiblings) {
            reportResolveSiblings($pdo, $report, 'dismissed', 'dismissed', $reportId, $currentAdminId);
        }
        logAdminAction($pdo, 'dismiss_report', 'report', $reportId);
        return true;
    }

    if ($action === 'resolve') {
        reportSetStatus($pdo, $reportId, 'reviewed', 'resolved', $currentAdminId);
        reportNotifyReporter($pdo, $report, __('admin.report_reporter_resolved_title'), __('admin.report_reporter_resolved_body'));
        if ($resolveSiblings) {
            reportResolveSiblings($pdo, $report, 'reviewed', 'resolved', $reportId, $currentAdminId);
        }
        logAdminAction($pdo, 'resolve_report', 'report', $reportId);
        return true;
    }

    if ($action === 'flag' && $productId > 0) {
        reportFlagProduct($pdo, $productId);
        reportSetStatus($pdo, $reportId, 'reviewed', 'flagged', $currentAdminId);
        reportNotifyReporter($pdo, $report, __('admin.report_reporter_flagged_title'), __('admin.report_reporter_flagged_body'));
        if ($targetUserId > 0) {
            reportNotifyTargetUser(
                $pdo,
                $targetUserId,
                __('admin.report_seller_flagged_title'),
                __('admin.report_seller_flagged_body', ['title' => $report['product_title'] ?? __('admin.report_listing_fallback')])
            );
        }
        if ($resolveSiblings) {
            reportResolveSiblings($pdo, $report, 'reviewed', 'flagged', $reportId, $currentAdminId);
        }
        logAdminAction($pdo, 'flag_report', 'report', $reportId, ['product_id' => $productId]);
        return true;
    }

    if ($action === 'warn_user' && $targetUserId > 0) {
        if ($targetUserId === $currentAdminId) {
            return false;
        }
        reportWarnUser($pdo, $targetUserId, __('admin.report_warn_user_body'));
        reportSetStatus($pdo, $reportId, 'reviewed', 'warned', $currentAdminId);
        reportNotifyReporter($pdo, $report, __('admin.report_reporter_resolved_title'), __('admin.report_reporter_warned_body'));
        if ($resolveSiblings) {
            reportResolveSiblings($pdo, $report, 'reviewed', 'warned', $reportId, $currentAdminId);
        }
        logAdminAction($pdo, 'warn_user_report', 'report', $reportId, ['user_id' => $targetUserId]);
        return true;
    }

    if ($action === 'suspend_user' && $targetUserId > 0) {
        if ($targetUserId === $currentAdminId) {
            return false;
        }
        if (!reportSuspendUser($pdo, $targetUserId)) {
            return false;
        }
        reportSetStatus($pdo, $reportId, 'reviewed', 'suspended', $currentAdminId);
        reportNotifyReporter($pdo, $report, __('admin.report_reporter_resolved_title'), __('admin.report_reporter_suspended_body'));
        if ($resolveSiblings) {
            reportResolveSiblings($pdo, $report, 'reviewed', 'suspended', $reportId, $currentAdminId);
        }
        logAdminAction($pdo, 'suspend_user_report', 'report', $reportId, ['user_id' => $targetUserId]);
        return true;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $action = sanitize($_POST['action']);
    $returnTab = sanitize($_POST['return_tab'] ?? 'pending');
    $returnView = sanitize($_POST['return_view'] ?? 'grouped');
    $returnType = sanitize($_POST['return_type'] ?? '');
    if (!in_array($returnTab, $allowedTabs, true)) {
        $returnTab = 'pending';
    }
    if (!in_array($returnView, ['grouped', 'flat'], true)) {
        $returnView = 'grouped';
    }

    $resolveSiblings = !empty($_POST['resolve_siblings']);
    $reportIds = [];

    if (!empty($_POST['report_ids']) && is_array($_POST['report_ids'])) {
        foreach ($_POST['report_ids'] as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $reportIds[] = $id;
            }
        }
    } elseif (isset($_POST['report_id'])) {
        $reportIds[] = (int)$_POST['report_id'];
    }

    $reportIds = array_values(array_unique($reportIds));
    if (empty($reportIds)) {
        setFlash('error', __('admin.report_not_found'));
        redirect(BASE_URL . 'admin/reports.php?tab=' . urlencode($returnTab));
    }

    $successCount = 0;
    $selfTarget = false;
    $suspendFailed = false;
    $warnedUsers = [];
    $suspendedUsers = [];

    foreach ($reportIds as $reportId) {
        $report = $reportId > 0 ? reportFetchForAdmin($pdo, $reportId) : null;
        if (!$report || ($report['status'] ?? '') !== 'pending') {
            continue;
        }
        $targetUserId = reportTargetUserId($report) ?? 0;
        if (in_array($action, ['warn_user', 'suspend_user'], true) && $targetUserId === $currentAdminId) {
            $selfTarget = true;
            continue;
        }

        if ($action === 'warn_user' && $targetUserId > 0 && isset($warnedUsers[$targetUserId])) {
            reportSetStatus($pdo, $reportId, 'reviewed', 'warned', $currentAdminId);
            reportNotifyReporter($pdo, $report, __('admin.report_reporter_resolved_title'), __('admin.report_reporter_warned_body'));
            $successCount++;
            continue;
        }
        if ($action === 'suspend_user' && $targetUserId > 0 && isset($suspendedUsers[$targetUserId])) {
            reportSetStatus($pdo, $reportId, 'reviewed', 'suspended', $currentAdminId);
            reportNotifyReporter($pdo, $report, __('admin.report_reporter_resolved_title'), __('admin.report_reporter_suspended_body'));
            $successCount++;
            continue;
        }

        if (reportApplyModerationAction($pdo, $report, $action, $currentAdminId, $resolveSiblings && $reportId === $reportIds[0])) {
            $successCount++;
            if ($action === 'warn_user' && $targetUserId > 0) {
                $warnedUsers[$targetUserId] = true;
            }
            if ($action === 'suspend_user' && $targetUserId > 0) {
                $suspendedUsers[$targetUserId] = true;
            }
        } elseif ($action === 'suspend_user') {
            $suspendFailed = true;
        }
    }

    if ($successCount > 0) {
        $flashKey = match ($action) {
            'dismiss' => 'admin.flash_report_dismissed',
            'resolve' => 'admin.flash_report_resolved',
            'flag' => 'admin.flash_report_flagged',
            'warn_user' => 'admin.flash_report_warned',
            'suspend_user' => 'admin.flash_report_suspended',
            default => 'admin.flash_report_resolved',
        };
        setFlash('success', __($flashKey));
    } elseif ($selfTarget) {
        setFlash('error', __('admin.report_cannot_target_self'));
    } elseif ($suspendFailed) {
        setFlash('error', __('admin.report_suspend_failed'));
    } else {
        setFlash('error', __('admin.report_invalid_action'));
    }

    $redirect = BASE_URL . 'admin/reports.php?tab=' . urlencode($returnTab === 'pending' ? 'pending' : $returnTab);
    if ($returnTab === 'pending') {
        $redirect .= '&view=' . urlencode($returnView);
    }
    if ($returnType !== '') {
        $redirect .= '&type=' . urlencode($returnType);
    }
    redirect($redirect);
}

$reports = reportsListForTab($pdo, $tab, $typeFilter !== '' ? $typeFilter : null);
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$groupedReports = $tab === 'pending' && $view === 'grouped' ? reportGroupReports($reports) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.report-tabs, .report-filters {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.report-tab, .report-filter {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    background: var(--bg-surface);
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.88rem;
}
.report-tab.active, .report-filter.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.report-tab .count { margin-left: 0.35rem; opacity: 0.85; }
.admin-reports-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    justify-content: flex-end;
}
.report-type-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}
.report-group-card {
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    margin-bottom: 1rem;
    overflow: hidden;
    background: var(--bg-surface);
}
.report-group-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(248, 250, 252, 0.8);
    border-bottom: 1px solid var(--border-light);
}
.report-group-count {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary);
    white-space: nowrap;
}
.report-group-body { padding: 0.75rem 1.25rem 1rem; }
.report-group-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-light);
}
.report-group-item:last-child { border-bottom: none; }
.report-sibling-toggle {
    font-size: 0.82rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.5rem;
}
</style>

<div class="container mt-24 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Moderation</div>
            <h1 class="mb-0"><?= __('admin.moderation_queue') ?></h1>
        </div>
        <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);">
            <?= __('admin.report_tab_count', ['count' => count($reports), 'tab' => __('admin.report_tab_' . $tab)]) ?>
        </div>
    </div>

    <nav class="report-tabs" aria-label="Report status tabs">
        <?php foreach ($allowedTabs as $tabKey): ?>
            <a href="reports.php?tab=<?= urlencode($tabKey) ?><?= $tabKey === 'pending' ? '&view=' . urlencode($view) : '' ?><?= $typeFilter !== '' ? '&type=' . urlencode($typeFilter) : '' ?>" class="report-tab <?= $tab === $tabKey ? 'active' : '' ?>">
                <?= __('admin.report_tab_' . $tabKey) ?>
                <?php if ($tabKey === 'pending' && $pendingCount > 0): ?>
                    <span class="count">(<?= (int)$pendingCount ?>)</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'pending'): ?>
    <div class="report-filters">
        <a href="reports.php?tab=pending&view=<?= urlencode($view) ?>" class="report-filter <?= $typeFilter === '' ? 'active' : '' ?>"><?= __('admin.report_filter_all_types') ?></a>
        <?php foreach (reportIssueTypes() as $issueType): ?>
            <a href="reports.php?tab=pending&view=<?= urlencode($view) ?>&type=<?= urlencode($issueType) ?>" class="report-filter <?= $typeFilter === $issueType ? 'active' : '' ?>">
                <?= __('report.type_' . $issueType) ?>
            </a>
        <?php endforeach; ?>
        <span style="flex:1"></span>
        <a href="reports.php?tab=pending&view=grouped<?= $typeFilter !== '' ? '&type=' . urlencode($typeFilter) : '' ?>" class="report-filter <?= $view === 'grouped' ? 'active' : '' ?>"><?= __('admin.report_view_grouped') ?></a>
        <a href="reports.php?tab=pending&view=flat<?= $typeFilter !== '' ? '&type=' . urlencode($typeFilter) : '' ?>" class="report-filter <?= $view === 'flat' ? 'active' : '' ?>"><?= __('admin.report_view_flat') ?></a>
    </div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="glass-panel p-16 text-center text-muted" style="border-radius: var(--radius-lg);">
            <h3 style="color: var(--success); font-weight: 600;"><?= __('admin.report_queue_clear_title') ?></h3>
            <p><?= __('admin.report_queue_clear_' . $tab) ?></p>
        </div>
    <?php elseif ($view === 'grouped' && $tab === 'pending'): ?>
        <?php foreach ($groupedReports as $group):
            $sample = $group['sample'];
            $groupReports = $group['reports'];
            $targetUserId = reportTargetUserId($sample);
            $hasProduct = !empty($sample['product_id']);
            $hasUserTarget = !empty($sample['reported_user_id']) || !empty($sample['seller_id']);
            $reportIds = array_map(static fn($r) => (int)$r['id'], $groupReports);
        ?>
        <div class="report-group-card">
            <div class="report-group-header">
                <div>
                    <?php if ($hasProduct): ?>
                        <div class="font-bold text-main"><?= sanitize($sample['product_title'] ?? __('admin.report_listing_fallback')) ?></div>
                        <a href="../pages/product.php?id=<?= (int)$sample['product_id'] ?>" target="_blank" class="small text-primary"><?= __('admin.report_view_listing') ?> ↗</a>
                    <?php elseif (!empty($sample['reported_user_id'])): ?>
                        <div class="font-bold text-main">@<?= sanitize($sample['reported_username'] ?? __('admin.report_user_fallback')) ?></div>
                        <a href="../pages/profile.php?id=<?= (int)$sample['reported_user_id'] ?>" target="_blank" class="small text-primary"><?= __('admin.report_view_profile') ?> ↗</a>
                    <?php else: ?>
                        <div class="font-bold text-main"><?= __('admin.report_general_issue') ?></div>
                    <?php endif; ?>
                    <span class="report-type-badge"><?= sanitize(reportIssueTypeLabel($sample)) ?></span>
                </div>
                <div class="report-group-count"><?= __('admin.report_group_count', ['count' => count($groupReports)]) ?></div>
            </div>
            <div class="report-group-body">
                <?php foreach ($groupReports as $r): ?>
                <div class="report-group-item">
                    <div class="text-muted small mb-1">@<?= sanitize($r['reporter_name'] ?? '—') ?> · <?= timeAgo($r['created_at']) ?></div>
                    <div style="font-size: 0.9rem; white-space: pre-wrap;"><?= sanitize(reportDisplayText($r)) ?></div>
                </div>
                <?php endforeach; ?>

                <form method="POST" class="admin-reports-actions m-0" style="margin-top: 1rem !important;">
                    <?php echo csrfTokenField(); ?>
                    <?php foreach ($reportIds as $rid): ?>
                        <input type="hidden" name="report_ids[]" value="<?= $rid ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="return_tab" value="<?= sanitize($tab) ?>">
                    <input type="hidden" name="return_view" value="grouped">
                    <input type="hidden" name="return_type" value="<?= sanitize($typeFilter) ?>">
                    <?php if (count($groupReports) > 1): ?>
                    <label class="report-sibling-toggle">
                        <input type="checkbox" name="resolve_siblings" value="1" checked>
                        <?= __('admin.report_resolve_all_in_group') ?>
                    </label>
                    <?php endif; ?>
                    <div class="admin-reports-actions" style="width: 100%;">
                        <button type="submit" name="action" value="dismiss" class="btn btn-secondary btn-sm"><?= __('admin.report_action_dismiss') ?></button>
                        <?php if ($hasProduct): ?>
                            <button type="submit" name="action" value="flag" class="btn btn-danger btn-sm" onclick="return confirm('<?= htmlspecialchars(__('admin.report_confirm_flag'), ENT_QUOTES) ?>')"><?= __('admin.report_action_flag') ?></button>
                        <?php else: ?>
                            <button type="submit" name="action" value="resolve" class="btn btn-success btn-sm"><?= __('admin.report_action_resolve') ?></button>
                        <?php endif; ?>
                        <?php if ($hasUserTarget && $targetUserId > 0 && (int)$targetUserId !== $currentAdminId): ?>
                            <button type="submit" name="action" value="warn_user" class="btn btn-warning btn-sm" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;"><?= __('admin.report_action_warn') ?></button>
                            <?php if (reportsAccountStatusSupported($pdo)): ?>
                            <button type="submit" name="action" value="suspend_user" class="btn btn-danger btn-sm" onclick="return confirm('<?= htmlspecialchars(__('admin.report_confirm_suspend'), ENT_QUOTES) ?>')"><?= __('admin.report_action_suspend') ?></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_target') ?></th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_type') ?></th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_reason') ?></th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_reporter') ?></th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_submitted') ?></th>
                    <?php if ($tab === 'pending'): ?>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_actions') ?></th>
                    <?php elseif ($tab !== 'pending'): ?>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);"><?= __('admin.report_col_resolution') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r):
                    $targetUserId = reportTargetUserId($r);
                    $hasProduct = !empty($r['product_id']);
                    $hasUserTarget = !empty($r['reported_user_id']) || !empty($r['seller_id']);
                ?>
                    <tr>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if ($hasProduct): ?>
                                <div class="font-bold text-main"><?php echo sanitize($r['product_title'] ?? __('admin.report_listing_fallback')); ?></div>
                                <a href="../pages/product.php?id=<?php echo (int)$r['product_id']; ?>" target="_blank" class="small text-primary inline-block mt-1" style="text-decoration: none; font-weight: 600;"><?= __('admin.report_view_listing') ?> ↗</a>
                            <?php elseif (!empty($r['reported_user_id'])): ?>
                                <div class="font-bold text-main">@<?php echo sanitize($r['reported_username'] ?? __('admin.report_user_fallback')); ?></div>
                                <a href="../pages/profile.php?id=<?php echo (int)$r['reported_user_id']; ?>" target="_blank" class="small text-primary inline-block mt-1" style="text-decoration: none; font-weight: 600;"><?= __('admin.report_view_profile') ?> ↗</a>
                            <?php else: ?>
                                <div class="font-bold text-main"><?= __('admin.report_general_issue') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="report-type-badge"><?= sanitize(reportIssueTypeLabel($r)) ?></span>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div style="background: rgba(239,68,68,0.05); border-left: 3px solid #ef4444; padding: 0.5rem 0.75rem; border-radius: 4px; font-size: 0.9rem; color: #7f1d1d; white-space: pre-wrap;"><?php echo sanitize(reportDisplayText($r)); ?></div>
                        </td>
                        <td class="p-4 font-medium" style="border-bottom: 1px solid var(--border-light);">
                            @<?php echo sanitize($r['reporter_name'] ?? '—'); ?>
                        </td>
                        <td class="p-4 text-muted small" style="border-bottom: 1px solid var(--border-light);">
                            <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.75rem;"><?php echo timeAgo($r['created_at']); ?></span>
                        </td>
                        <?php if ($tab === 'pending'): ?>
                        <td class="p-4 text-right" style="border-bottom: 1px solid var(--border-light);">
                            <form method="POST" class="admin-reports-actions m-0">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="return_tab" value="<?= sanitize($tab) ?>">
                                <input type="hidden" name="return_view" value="flat">
                                <input type="hidden" name="return_type" value="<?= sanitize($typeFilter) ?>">
                                <button type="submit" name="action" value="dismiss" class="btn btn-secondary btn-sm"><?= __('admin.report_action_dismiss') ?></button>
                                <?php if ($hasProduct): ?>
                                    <button type="submit" name="action" value="flag" class="btn btn-danger btn-sm" onclick="return confirm('<?= htmlspecialchars(__('admin.report_confirm_flag'), ENT_QUOTES) ?>')"><?= __('admin.report_action_flag') ?></button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="resolve" class="btn btn-success btn-sm"><?= __('admin.report_action_resolve') ?></button>
                                <?php endif; ?>
                                <?php if ($hasUserTarget && $targetUserId > 0 && (int)$targetUserId !== $currentAdminId): ?>
                                    <button type="submit" name="action" value="warn_user" class="btn btn-warning btn-sm" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;"><?= __('admin.report_action_warn') ?></button>
                                    <?php if (reportsAccountStatusSupported($pdo)): ?>
                                    <button type="submit" name="action" value="suspend_user" class="btn btn-danger btn-sm" onclick="return confirm('<?= htmlspecialchars(__('admin.report_confirm_suspend'), ENT_QUOTES) ?>')"><?= __('admin.report_action_suspend') ?></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                        </td>
                        <?php else: ?>
                        <td class="p-4 text-muted small" style="border-bottom: 1px solid var(--border-light);">
                            <?= sanitize($r['resolution'] ?? '—') ?>
                            <?php if (!empty($r['resolved_at'])): ?>
                                <div class="mt-1"><?= timeAgo($r['resolved_at']) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
