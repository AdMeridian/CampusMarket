<?php
/**
 * Shared helpers for admin report moderation.
 */

if (!function_exists('reportIssueTypes')) {
    function reportIssueTypes(): array {
        return ['scam', 'inappropriate', 'harassment', 'technical', 'other'];
    }
}

if (!function_exists('reportsStructuredColumnsSupported')) {
    function reportsStructuredColumnsSupported(PDO $pdo): bool {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }
        try {
            $stmt = $pdo->query("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'reports' AND column_name = 'issue_type'
                LIMIT 1
            ");
            $supported = (bool)($stmt && $stmt->fetchColumn());
        } catch (Throwable $e) {
            $supported = false;
        }
        return $supported;
    }
}

if (!function_exists('reportsAccountStatusSupported')) {
    function reportsAccountStatusSupported(PDO $pdo): bool {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }
        try {
            $stmt = $pdo->query("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'account_status'
                LIMIT 1
            ");
            $supported = (bool)($stmt && $stmt->fetchColumn());
        } catch (Throwable $e) {
            $supported = false;
        }
        return $supported;
    }
}

if (!function_exists('reportNormalizeIssueType')) {
    function reportNormalizeIssueType(string $issueType): string {
        $issueType = strtolower(trim($issueType));
        return in_array($issueType, reportIssueTypes(), true) ? $issueType : 'other';
    }
}

if (!function_exists('reportBuildReason')) {
    function reportBuildReason(string $issueType, string $description, string $link = ''): string {
        $reason = '[' . strtoupper(reportNormalizeIssueType($issueType)) . '] ' . trim($description);
        if ($link !== '') {
            $reason .= "\n\n" . __('report.reference_link_label') . ': ' . $link;
        }
        return $reason;
    }
}

if (!function_exists('reportDisplayText')) {
    function reportDisplayText(array $report): string {
        if (!empty($report['description'])) {
            return (string)$report['description'];
        }
        return (string)($report['reason'] ?? '');
    }
}

if (!function_exists('reportIssueTypeLabel')) {
    function reportIssueTypeLabel(array $report): string {
        $type = $report['issue_type'] ?? '';
        if ($type === '' && !empty($report['reason']) && preg_match('/^\[([A-Z_]+)\]/', (string)$report['reason'], $m)) {
            $type = strtolower($m[1]);
        }
        $type = reportNormalizeIssueType((string)$type);
        $key = 'report.type_' . $type;
        $label = __($key);
        return $label !== $key ? $label : ucfirst($type);
    }
}

if (!function_exists('reportGroupKey')) {
    function reportGroupKey(array $report): string {
        if (!empty($report['product_id'])) {
            return 'product:' . (int)$report['product_id'];
        }
        if (!empty($report['reported_user_id'])) {
            return 'user:' . (int)$report['reported_user_id'];
        }
        return 'general:' . (int)($report['id'] ?? 0);
    }
}

if (!function_exists('reportGroupReports')) {
    /** @param array<int, array> $reports */
    function reportGroupReports(array $reports): array {
        $groups = [];
        foreach ($reports as $report) {
            $key = reportGroupKey($report);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'reports' => [],
                    'sample' => $report,
                ];
            }
            $groups[$key]['reports'][] = $report;
        }
        usort($groups, static function (array $a, array $b): int {
            return count($b['reports']) <=> count($a['reports']);
        });
        return array_values($groups);
    }
}

if (!function_exists('reportFetchForAdmin')) {
    function reportFetchForAdmin(PDO $pdo, int $reportId): ?array {
        try {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       p.user_id AS seller_id,
                       su.username AS seller_name,
                       ru.username AS reported_username,
                       u.username AS reporter_name
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users su ON p.user_id = su.id
                LEFT JOIN users ru ON r.reported_user_id = ru.id
                LEFT JOIN users u ON r.reporter_id = u.id
                WHERE r.id = :id
                LIMIT 1
            ");
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       p.user_id AS seller_id,
                       su.username AS seller_name,
                       NULL AS reported_username,
                       u.username AS reporter_name
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users su ON p.user_id = su.id
                LEFT JOIN users u ON r.reporter_id = u.id
                WHERE r.id = :id
                LIMIT 1
            ");
        }
        $stmt->execute([':id' => $reportId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('reportTargetUserId')) {
    function reportTargetUserId(array $report): ?int {
        if (!empty($report['reported_user_id'])) {
            return (int)$report['reported_user_id'];
        }
        if (!empty($report['seller_id'])) {
            return (int)$report['seller_id'];
        }
        return null;
    }
}

if (!function_exists('reportSetStatus')) {
    function reportSetStatus(PDO $pdo, int $reportId, string $status, ?string $resolution = null, ?int $adminId = null): void {
        $allowed = ['pending', 'reviewed', 'dismissed'];
        if (!in_array($status, $allowed, true)) {
            return;
        }

        if (reportsStructuredColumnsSupported($pdo) && ($resolution !== null || $status !== 'pending')) {
            $stmt = $pdo->prepare("
                UPDATE reports
                SET status = :status,
                    resolution = CASE WHEN :status = 'pending' THEN NULL ELSE COALESCE(:resolution, resolution) END,
                    resolved_at = CASE WHEN :status = 'pending' THEN NULL ELSE COALESCE(resolved_at, NOW()) END,
                    resolved_by_admin_id = CASE WHEN :status = 'pending' THEN NULL ELSE COALESCE(resolved_by_admin_id, :admin_id) END
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':resolution' => $resolution,
                ':admin_id' => $adminId,
                ':id' => $reportId,
            ]);
            return;
        }

        $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $reportId]);
    }
}

if (!function_exists('reportSiblingPendingIds')) {
    /** @return int[] */
    function reportSiblingPendingIds(PDO $pdo, array $report, int $excludeReportId = 0): array {
        $productId = !empty($report['product_id']) ? (int)$report['product_id'] : 0;
        $reportedUserId = !empty($report['reported_user_id']) ? (int)$report['reported_user_id'] : 0;

        if ($productId <= 0 && $reportedUserId <= 0) {
            return [];
        }

        if ($productId > 0) {
            $stmt = $pdo->prepare("
                SELECT id FROM reports
                WHERE status = 'pending' AND product_id = :pid AND id <> :exclude
            ");
            $stmt->execute([':pid' => $productId, ':exclude' => $excludeReportId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM reports
                WHERE status = 'pending' AND reported_user_id = :uid AND id <> :exclude
            ");
            $stmt->execute([':uid' => $reportedUserId, ':exclude' => $excludeReportId]);
        }

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('reportResolveSiblings')) {
    function reportResolveSiblings(PDO $pdo, array $report, string $status, string $resolution, int $primaryReportId, ?int $adminId = null): void {
        foreach (reportSiblingPendingIds($pdo, $report, $primaryReportId) as $siblingId) {
            $sibling = reportFetchForAdmin($pdo, $siblingId);
            if (!$sibling || ($sibling['status'] ?? '') !== 'pending') {
                continue;
            }
            reportSetStatus($pdo, $siblingId, $status, $resolution, $adminId);
            reportNotifyReporter(
                $pdo,
                $sibling,
                __('admin.report_reporter_resolved_title'),
                __('admin.report_sibling_resolved_body', ['id' => $primaryReportId])
            );
        }
    }
}

if (!function_exists('reportFetchUserContact')) {
    function reportFetchUserContact(PDO $pdo, int $userId): ?array {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, username, email, is_verified FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('reportEmailUser')) {
    function reportEmailUser(PDO $pdo, int $userId, string $subject, string $headline, string $body, string $ctaUrl, string $ctaText): void {
        if ($userId <= 0) {
            return;
        }
        try {
            require_once __DIR__ . '/mailer.php';
            $user = reportFetchUserContact($pdo, $userId);
            if (!$user || empty($user['email']) || !(bool)($user['is_verified'] ?? false)) {
                return;
            }
            $result = sendMarketplaceAlertEmail(
                (string)$user['email'],
                (string)($user['username'] ?? 'User'),
                $subject,
                $headline,
                $body,
                $ctaUrl,
                $ctaText
            );
            if (empty($result['ok'])) {
                error_log('[report] email failed for user ' . $userId . ': ' . json_encode($result));
            }
        } catch (Throwable $e) {
            error_log('[report] reportEmailUser failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('reportNotifyReporter')) {
    function reportNotifyReporter(PDO $pdo, array $report, string $title, string $body, bool $sendEmail = true): void {
        $reporterId = (int)($report['reporter_id'] ?? 0);
        if ($reporterId <= 0) {
            return;
        }
        createNotification($pdo, $reporterId, 'system', $title, $body, (int)$report['id']);
        if ($sendEmail) {
            $myReportsUrl = rtrim(BASE_URL, '/') . '/pages/my_reports.php';
            reportEmailUser(
                $pdo,
                $reporterId,
                $title . ' — CampusMarket',
                $title,
                $body,
                $myReportsUrl,
                __('report.my_reports_cta')
            );
        }
    }
}

if (!function_exists('reportNotifyTargetUser')) {
    function reportNotifyTargetUser(PDO $pdo, int $userId, string $title, string $body, bool $sendEmail = true): void {
        if ($userId <= 0) {
            return;
        }
        createNotification($pdo, $userId, 'system', $title, $body);
        if ($sendEmail) {
            $profileUrl = rtrim(BASE_URL, '/') . '/pages/profile.php';
            reportEmailUser(
                $pdo,
                $userId,
                $title . ' — CampusMarket',
                $title,
                $body,
                $profileUrl,
                __('nav.my_profile')
            );
        }
    }
}

if (!function_exists('reportFlagProduct')) {
    function reportFlagProduct(PDO $pdo, int $productId): void {
        if ($productId <= 0) {
            return;
        }
        $pdo->prepare("UPDATE products SET status = 'flagged' WHERE id = ?")->execute([$productId]);
    }
}

if (!function_exists('reportWarnUser')) {
    function reportWarnUser(PDO $pdo, int $userId, string $body): void {
        if ($userId <= 0) {
            return;
        }
        $title = __('admin.report_warn_user_title');
        createNotification($pdo, $userId, 'system', $title, $body);
        reportEmailUser(
            $pdo,
            $userId,
            $title . ' — CampusMarket',
            $title,
            $body,
            rtrim(BASE_URL, '/') . '/pages/profile.php',
            __('nav.my_profile')
        );
    }
}

if (!function_exists('reportSuspendUser')) {
    function reportSuspendUser(PDO $pdo, int $userId): bool {
        if ($userId <= 0 || !reportsAccountStatusSupported($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || ($user['role'] ?? '') === 'admin') {
            return false;
        }

        if (isUserSuspended($pdo, $userId)) {
            return true;
        }

        $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ?")->execute([$userId]);
        $title = __('admin.report_suspend_user_title');
        $body = __('admin.report_suspend_user_body');
        createNotification($pdo, $userId, 'system', $title, $body);
        reportEmailUser(
            $pdo,
            $userId,
            $title . ' — CampusMarket',
            $title,
            $body,
            rtrim(BASE_URL, '/') . '/pages/login.php',
            __('nav.login')
        );
        return true;
    }
}

if (!function_exists('reportUnsuspendUser')) {
    function reportUnsuspendUser(PDO $pdo, int $userId): bool {
        if ($userId <= 0 || !reportsAccountStatusSupported($pdo)) {
            return false;
        }
        $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$userId]);
        return true;
    }
}

if (!function_exists('isUserSuspended')) {
    function isUserSuspended(PDO $pdo, int $userId): bool {
        if ($userId <= 0 || !reportsAccountStatusSupported($pdo)) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT account_status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() === 'suspended';
    }
}

if (!function_exists('enforceActiveAccount')) {
    function enforceActiveAccount(PDO $pdo): void {
        if (!isLoggedIn() || isAdmin()) {
            return;
        }
        $uid = currentUserId();
        if ($uid && isUserSuspended($pdo, $uid)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            setcookie('campusmarket_sess_stateless', '', ['expires' => time() - 3600, 'path' => '/']);
            session_destroy();
            session_name(SESSION_NAME);
            session_start();
            setFlash('error', __('auth.error_suspended'));
            redirect(BASE_URL . 'pages/login.php');
        }
    }
}

if (!function_exists('reportsListForTab')) {
    function reportsListForTab(PDO $pdo, string $tab, ?string $issueTypeFilter = null): array {
        $status = in_array($tab, ['pending', 'reviewed', 'dismissed'], true) ? $tab : 'pending';
        $params = [':status' => $status];
        $typeClause = '';
        if ($issueTypeFilter && in_array($issueTypeFilter, reportIssueTypes(), true) && reportsStructuredColumnsSupported($pdo)) {
            $typeClause = ' AND r.issue_type = :issue_type';
            $params[':issue_type'] = $issueTypeFilter;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       p.user_id AS seller_id,
                       su.username AS seller_name,
                       ru.username AS reported_username,
                       u.username AS reporter_name
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users su ON p.user_id = su.id
                LEFT JOIN users ru ON r.reported_user_id = ru.id
                LEFT JOIN users u ON r.reporter_id = u.id
                WHERE r.status = :status{$typeClause}
                ORDER BY r.created_at DESC
            ");
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       p.user_id AS seller_id,
                       su.username AS seller_name,
                       NULL AS reported_username,
                       u.username AS reporter_name
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users su ON p.user_id = su.id
                LEFT JOIN users u ON r.reporter_id = u.id
                WHERE r.status = :status{$typeClause}
                ORDER BY r.created_at DESC
            ");
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reportsListForUser')) {
    function reportsListForUser(PDO $pdo, int $userId): array {
        if ($userId <= 0) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       ru.username AS reported_username
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users ru ON r.reported_user_id = ru.id
                WHERE r.reporter_id = :uid
                ORDER BY r.created_at DESC
                LIMIT 100
            ");
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                SELECT r.*,
                       p.title AS product_title,
                       NULL AS reported_username
                FROM reports r
                LEFT JOIN products p ON r.product_id = p.id
                WHERE r.reporter_id = :uid
                ORDER BY r.created_at DESC
                LIMIT 100
            ");
        }
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reportInsert')) {
    function reportInsert(
        PDO $pdo,
        int $reporterId,
        ?int $productId,
        ?int $reportedUserId,
        string $issueType,
        string $description,
        string $link = ''
    ): int {
        $issueType = reportNormalizeIssueType($issueType);
        $description = trim($description);
        $link = trim($link);
        $reason = reportBuildReason($issueType, $description, $link);

        if (reportsStructuredColumnsSupported($pdo)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reports (
                        reporter_id, product_id, reported_user_id,
                        issue_type, description, reference_link, reason, status
                    ) VALUES (
                        :rid, :pid, :uid,
                        :issue_type, :description, :link, :reason, 'pending'
                    )
                ");
                $stmt->execute([
                    ':rid' => $reporterId,
                    ':pid' => $productId,
                    ':uid' => $reportedUserId,
                    ':issue_type' => $issueType,
                    ':description' => $description,
                    ':link' => $link !== '' ? $link : null,
                    ':reason' => $reason,
                ]);
                return (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                error_log('[report] structured insert failed, falling back: ' . $e->getMessage());
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO reports (reporter_id, product_id, reported_user_id, reason, status)
                VALUES (:rid, :pid, :uid, :reason, 'pending')
            ");
            $stmt->execute([
                ':rid' => $reporterId,
                ':pid' => $productId,
                ':uid' => $reportedUserId,
                ':reason' => $reason,
            ]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("
                INSERT INTO reports (reporter_id, product_id, reason, status)
                VALUES (:rid, :pid, :reason, 'pending')
            ");
            $stmt->execute([
                ':rid' => $reporterId,
                ':pid' => $productId,
                ':reason' => $reason,
            ]);
        }

        return (int)$pdo->lastInsertId();
    }
}
