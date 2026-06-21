<?php
/**
 * Persistent audit trail for admin mutations.
 */

if (!function_exists('logAdminAction')) {
    function logAdminAction(
        PDO $pdo,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $details = []
    ): void {
        if (!isAdmin() || !currentUserId()) {
            return;
        }

        try {
            $tableCheck = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'admin_audit_log' LIMIT 1");
            if (!$tableCheck || !$tableCheck->fetchColumn()) {
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address)
                VALUES (:admin_id, :action, :target_type, :target_id, :details, :ip)
            ");
            $stmt->execute([
                ':admin_id' => currentUserId(),
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[admin_audit] ' . $e->getMessage());
        }
    }
}
