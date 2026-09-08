<?php
// admin/system_logs.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/../includes/admin_audit.php';

$pageTitle = 'Platform System Error Logs';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'clear_all') {
        try {
            $pdo->exec("TRUNCATE TABLE system_logs");
            setFlash('success', 'All system error logs have been cleared.');
            logAdminAction($pdo, 'clear_system_logs', 'system', null);
        } catch (Throwable $e) {
            setFlash('error', 'Failed to clear logs: ' . $e->getMessage());
        }
        redirect(BASE_URL . 'admin/system_logs.php');
    } elseif ($action === 'delete') {
        $logId = (int)($_POST['id'] ?? 0);
        if ($logId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id = ?");
                $stmt->execute([$logId]);
                setFlash('success', 'Error log entry #' . $logId . ' deleted.');
            } catch (Throwable $e) {
                setFlash('error', 'Failed to delete log entry: ' . $e->getMessage());
            }
        }
        redirect(BASE_URL . 'admin/system_logs.php');
    }
}

// Filter parameters
$selectedCategory = trim((string)($_GET['category'] ?? ''));
$searchQuery = trim((string)($_GET['q'] ?? ''));

// Handle Export action (before HTML output)
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
    $scope = strtolower(trim((string)($_GET['scope'] ?? 'filtered')));

    $exportWhereClauses = [];
    $exportParams = [];

    if ($scope === 'filtered') {
        if ($selectedCategory !== '') {
            $exportWhereClauses[] = "category = :category";
            $exportParams[':category'] = $selectedCategory;
        }
        if ($searchQuery !== '') {
            $exportWhereClauses[] = "(message ILIKE :q OR raw_trace ILIKE :q OR user_email ILIKE :q OR url ILIKE :q)";
            $exportParams[':q'] = '%' . $searchQuery . '%';
        }
    }

    $exportWhereSql = !empty($exportWhereClauses) ? 'WHERE ' . implode(' AND ', $exportWhereClauses) : '';
    $exportStmt = $pdo->prepare("
        SELECT l.id, l.created_at, l.category, l.user_id, u.username, l.user_email, l.ip_address, l.request_method, l.url, l.message, l.raw_trace
        FROM system_logs l
        LEFT JOIN users u ON u.id = l.user_id
        {$exportWhereSql}
        ORDER BY l.created_at DESC
    ");
    $exportStmt->execute($exportParams);
    $exportLogs = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $timestamp = date('Y-m-d_His');
    $safeCategorySuffix = ($scope === 'filtered' && $selectedCategory !== '') ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $selectedCategory) : '';
    $filenameBase = "campusmarket_error_logs{$safeCategorySuffix}_{$timestamp}";

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.json"');
        echo json_encode($exportLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($format === 'txt' || $format === 'log') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.log"');
        echo "================================================================================" . PHP_EOL;
        echo "CampusMarket Platform System Error Logs Export" . PHP_EOL;
        echo "Generated: " . date('Y-m-d H:i:s T') . PHP_EOL;
        echo "Total Entries: " . count($exportLogs) . PHP_EOL;
        echo "Scope: " . ($scope === 'filtered' ? 'Filtered (' . ($selectedCategory ?: 'All categories') . ')' : 'All Logs') . PHP_EOL;
        echo "================================================================================" . PHP_EOL . PHP_EOL;
        foreach ($exportLogs as $row) {
            echo "--------------------------------------------------------------------------------" . PHP_EOL;
            echo "LOG #" . $row['id'] . " | DATE: " . $row['created_at'] . " | CATEGORY: " . strtoupper($row['category'] ?? 'SYSTEM') . PHP_EOL;
            echo "USER: " . (!empty($row['username']) ? "@" . $row['username'] : "Guest") . " (ID: " . ($row['user_id'] ?? 'N/A') . ", Email: " . ($row['user_email'] ?? 'N/A') . ")" . PHP_EOL;
            echo "REQUEST: " . ($row['request_method'] ?? 'GET') . " " . ($row['url'] ?? 'N/A') . " (IP: " . ($row['ip_address'] ?? 'N/A') . ")" . PHP_EOL;
            echo "MESSAGE: " . $row['message'] . PHP_EOL;
            if (!empty($row['raw_trace'])) {
                echo "STACK TRACE:" . PHP_EOL . $row['raw_trace'] . PHP_EOL;
            }
            echo PHP_EOL;
        }
        exit;
    } else {
        // Default: CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Timestamp', 'Category', 'User ID', 'Username', 'User Email', 'IP Address', 'Request Method', 'URL', 'Error Message', 'Stack Trace']);
        foreach ($exportLogs as $row) {
            fputcsv($out, [
                $row['id'],
                $row['created_at'],
                $row['category'],
                $row['user_id'] ?? '',
                $row['username'] ?? '',
                $row['user_email'] ?? '',
                $row['ip_address'] ?? '',
                $row['request_method'] ?? 'GET',
                $row['url'] ?? '',
                $row['message'] ?? '',
                $row['raw_trace'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}

// Build query
$whereClauses = [];
$params = [];

if ($selectedCategory !== '') {
    $whereClauses[] = "category = :category";
    $params[':category'] = $selectedCategory;
}

if ($searchQuery !== '') {
    $whereClauses[] = "(message ILIKE :q OR raw_trace ILIKE :q OR user_email ILIKE :q OR url ILIKE :q)";
    $params[':q'] = '%' . $searchQuery . '%';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Fetch categories for filter dropdown
$catStmt = $pdo->query("SELECT DISTINCT category FROM system_logs ORDER BY category ASC");
$allCategories = $catStmt ? $catStmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Fetch logs
$sql = "
    SELECT l.*, u.username
    FROM system_logs l
    LEFT JOIN users u ON u.id = l.user_id
    {$whereSql}
    ORDER BY l.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total
$countSql = "SELECT COUNT(*) FROM system_logs {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalLogsCount = (int)$countStmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-wrap {
    max-width: var(--container-max);
    margin: calc(70px + 1.5rem) auto 5rem;
    padding: 0 1.5rem;
}
.log-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.log-badge-create_listing { background: rgba(239,68,68,0.15); color: #dc2626; }
.log-badge-payment { background: rgba(245,158,11,0.15); color: #d97706; }
.log-badge-database { background: rgba(139,92,246,0.15); color: #7c3aed; }
.log-badge-auth { background: rgba(59,130,246,0.15); color: #2563eb; }
.log-badge-system { background: rgba(107,114,128,0.15); color: #4b5563; }

.trace-box {
    background: var(--bg-main);
    color: var(--text-main);
    font-family: monospace;
    font-size: 0.78rem;
    padding: 0.75rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-light);
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 250px;
    overflow-y: auto;
}

.export-menu-item:hover {
    background: var(--bg-main);
    color: var(--primary) !important;
}
</style>

<div class="admin-wrap">
    <div class="admin-title-row">
        <div>
            <div class="admin-breadcrumb">
                <a href="index.php">Dashboard</a> &rsaquo; <span>System Error Logs</span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; color: var(--text-main); margin-top: 0.35rem;">
                Platform System Error Logs
            </h1>
        </div>
        <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
            <?php if ($totalLogsCount > 0): ?>
                <?php
                    $exportQueryBase = 'action=export';
                    if ($selectedCategory !== '') $exportQueryBase .= '&category=' . urlencode($selectedCategory);
                    if ($searchQuery !== '') $exportQueryBase .= '&q=' . urlencode($searchQuery);
                ?>
                <div class="export-dropdown" style="position: relative; display: inline-block;">
                    <button type="button" id="exportLogsBtn" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg); display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Export Logs</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px; opacity: 0.7;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div id="exportMenu" class="glass-panel" style="display: none; position: absolute; right: 0; top: calc(100% + 6px); min-width: 220px; z-index: 100; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 0.4rem; border: 1px solid var(--border-light); background: var(--bg-surface);">
                        <div style="padding: 0.4rem 0.6rem; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                            Save File (<?= $totalLogsCount ?> logs)
                        </div>
                        <a href="system_logs.php?<?= $exportQueryBase ?>&format=csv" class="export-menu-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.65rem; border-radius: var(--radius-md); text-decoration: none; color: var(--text-main); font-size: 0.85rem; font-weight: 600; transition: background 0.15s;">
                            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 800; font-size: 0.72rem; padding: 0.15rem 0.4rem; border-radius: 4px;">CSV</span>
                            <span>Spreadsheet (.csv)</span>
                        </a>
                        <a href="system_logs.php?<?= $exportQueryBase ?>&format=json" class="export-menu-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.65rem; border-radius: var(--radius-md); text-decoration: none; color: var(--text-main); font-size: 0.85rem; font-weight: 600; transition: background 0.15s;">
                            <span style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-weight: 800; font-size: 0.72rem; padding: 0.15rem 0.4rem; border-radius: 4px;">JSON</span>
                            <span>Raw Data (.json)</span>
                        </a>
                        <a href="system_logs.php?<?= $exportQueryBase ?>&format=log" class="export-menu-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.65rem; border-radius: var(--radius-md); text-decoration: none; color: var(--text-main); font-size: 0.85rem; font-weight: 600; transition: background 0.15s;">
                            <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; font-weight: 800; font-size: 0.72rem; padding: 0.15rem 0.4rem; border-radius: 4px;">LOG</span>
                            <span>Formatted Text (.log)</span>
                        </a>
                        <?php if ($selectedCategory !== '' || $searchQuery !== ''): ?>
                            <div style="border-top: 1px solid var(--border-light); margin: 0.35rem 0; padding-top: 0.35rem;">
                                <a href="system_logs.php?action=export&scope=all&format=csv" class="export-menu-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.65rem; border-radius: var(--radius-md); text-decoration: none; color: var(--text-muted); font-size: 0.8rem;">
                                    <span>Download All (Unfiltered)</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" onsubmit="return confirm('Permanently delete all system error logs? Tip: You can export a backup first.');" style="margin: 0;">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" name="action" value="clear_all" class="btn btn-danger btn-sm" style="border-radius: var(--radius-lg);">
                        Clear All Logs (<?= $totalLogsCount ?>)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="glass-panel mb-6 p-4" style="border-radius: var(--radius-lg);">
        <form method="GET" action="system_logs.php" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin: 0;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search error messages, user email, URL..." 
                       class="w-full bg-surface border-light py-2 px-3 text-main"
                       style="border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.9rem;">
            </div>
            <div>
                <select name="category" class="bg-surface border-light py-2 px-3 text-main" style="border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 0.9rem;">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cat)), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="border-radius: var(--radius-md); padding: 0.55rem 1.25rem;">
                Filter
            </button>
            <?php if ($selectedCategory !== '' || $searchQuery !== ''): ?>
                <a href="system_logs.php" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-md); text-decoration: none;">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($logs)): ?>
        <div class="glass-panel text-center p-8" style="border-radius: var(--radius-lg); color: var(--text-muted);">
            <svg style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 style="color: var(--text-main); font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;">No Error Logs Found</h3>
            <p style="font-size: 0.9rem; margin: 0;">No platform errors matched your filter parameters.</p>
        </div>
    <?php else: ?>
        <div class="glass-panel" style="border-radius: var(--radius-lg); overflow: hidden;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-light); background: var(--bg-surface); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 0.85rem 1rem;">ID / Date</th>
                            <th style="padding: 0.85rem 1rem;">Category</th>
                            <th style="padding: 0.85rem 1rem;">User / Account</th>
                            <th style="padding: 0.85rem 1rem;">Error Summary</th>
                            <th style="padding: 0.85rem 1rem;">URL</th>
                            <th style="padding: 0.85rem 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php 
                                $cat = (string)$log['category'];
                                $badgeClass = 'log-badge-' . preg_replace('/[^a-z0-9_]/', '', strtolower($cat));
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-light); vertical-align: top;">
                                <td style="padding: 0.85rem 1rem; white-space: nowrap;">
                                    <strong style="color: var(--text-main);">#<?= (int)$log['id'] ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                        <?= timeAgo($log['created_at']) ?>
                                    </div>
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    <span class="log-badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $cat), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    <?php if (!empty($log['user_email']) || !empty($log['username'])): ?>
                                        <strong style="color: var(--text-main); font-size: 0.85rem;">@<?= htmlspecialchars($log['username'] ?? 'User') ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($log['user_email'] ?? ('ID #' . $log['user_id'])) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem;">Guest / Unauthenticated</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.85rem 1rem; max-width: 320px;">
                                    <div style="font-weight: 600; color: var(--text-main); line-height: 1.35; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!empty($log['raw_trace'])): ?>
                                        <details style="margin-top: 0.35rem;">
                                            <summary style="cursor: pointer; font-size: 0.76rem; color: var(--primary); font-weight: 600;">
                                                View Raw Technical Trace
                                            </summary>
                                            <div class="trace-box" style="margin-top: 0.4rem;">
                                                <?= htmlspecialchars($log['raw_trace'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.85rem 1rem; max-width: 180px; font-family: monospace; font-size: 0.78rem; word-break: break-all; color: var(--text-muted);">
                                    <span style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($log['request_method'] ?? 'GET') ?></span>
                                    <?= htmlspecialchars($log['url'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td style="padding: 0.85rem 1rem; text-align: right; white-space: nowrap;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this log entry?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="id" value="<?= (int)$log['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.getElementById('exportLogsBtn');
    const exportMenu = document.getElementById('exportMenu');
    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            exportMenu.style.display = (exportMenu.style.display === 'none' || !exportMenu.style.display) ? 'block' : 'none';
        });
        document.addEventListener('click', function(e) {
            if (!exportMenu.contains(e.target) && e.target !== exportBtn) {
                exportMenu.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

