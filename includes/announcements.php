<?php
/**
 * includes/announcements.php
 *
 * Core helpers for platform-wide announcements.
 */

if (!function_exists('ensureAnnouncementsTable')) {
    function ensureAnnouncementsTable(PDO $pdo): void {
        static $ensured = false;
        if ($ensured) return;

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        try {
            if ($driver === 'mysql') {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS announcements (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(150) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(20) NOT NULL DEFAULT 'info',
                        cta_text VARCHAR(60) NULL,
                        cta_url VARCHAR(255) NULL,
                        target_role VARCHAR(20) NOT NULL DEFAULT 'all',
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        starts_at DATETIME NULL,
                        ends_at DATETIME NULL,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX (is_active, target_role),
                        INDEX (starts_at, ends_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } else {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS public.announcements (
                        id BIGSERIAL PRIMARY KEY,
                        title VARCHAR(150) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(20) NOT NULL DEFAULT 'info',
                        cta_text VARCHAR(60) NULL,
                        cta_url VARCHAR(255) NULL,
                        target_role VARCHAR(20) NOT NULL DEFAULT 'all',
                        is_active BOOLEAN NOT NULL DEFAULT TRUE,
                        starts_at TIMESTAMPTZ NULL,
                        ends_at TIMESTAMPTZ NULL,
                        created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                    );
                    CREATE INDEX IF NOT EXISTS idx_announcements_active ON public.announcements(is_active, target_role);
                    CREATE INDEX IF NOT EXISTS idx_announcements_dates ON public.announcements(starts_at, ends_at);
                    ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;
                    DO $$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_policies 
                            WHERE tablename = 'announcements' AND policyname = 'announcements_read_active'
                        ) THEN
                            CREATE POLICY announcements_read_active
                                ON public.announcements
                                FOR SELECT
                                TO anon, authenticated
                                USING (
                                    is_active = TRUE
                                    AND (starts_at IS NULL OR starts_at <= NOW())
                                    AND (ends_at IS NULL OR ends_at >= NOW())
                                );
                        END IF;
                    END
                    $$;
                ");
            }
        } catch (Throwable $e) {
            error_log('[ensureAnnouncementsTable] Error creating table: ' . $e->getMessage());
        }
        $ensured = true;
    }
}

if (!function_exists('getActiveAnnouncements')) {
    function getActiveAnnouncements(PDO $pdo, ?string $userRole = null): array {
        ensureAnnouncementsTable($pdo);

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $nowSql = $driver === 'mysql' ? 'NOW()' : 'CURRENT_TIMESTAMP';

            $sql = "
                SELECT id, title, message, type, cta_text, cta_url, target_role, starts_at, ends_at, updated_at
                FROM announcements
                WHERE is_active = " . ($driver === 'mysql' ? '1' : 'TRUE') . "
                  AND (starts_at IS NULL OR starts_at <= {$nowSql})
                  AND (ends_at IS NULL OR ends_at >= {$nowSql})
            ";

            if ($userRole === 'admin') {
                $sql .= " AND target_role IN ('all', 'admin', 'user')";
            } elseif ($userRole === 'user') {
                $sql .= " AND target_role IN ('all', 'user')";
            } else {
                $sql .= " AND target_role IN ('all', 'guest')";
            }

            $sql .= " ORDER BY id DESC LIMIT 5";

            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[getActiveAnnouncements] ' . $e->getMessage());
            return [];
        }
    }
}
