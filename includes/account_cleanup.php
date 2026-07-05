<?php
/**
 * Purge unverified user accounts after a configurable grace period.
 */

require_once __DIR__ . '/../config/supabase.php';

if (!function_exists('unverifiedAccountTtlDays')) {
    function unverifiedAccountTtlDays(): int {
        $days = (int) (getenv('UNVERIFIED_ACCOUNT_TTL_DAYS') ?: 3);
        return max(1, min(30, $days));
    }
}

if (!function_exists('isAuthorizedCronRequest')) {
    function isAuthorizedCronRequest(): bool {
        $secret = trim((string) (getenv('CRON_SECRET') ?: ''));
        if ($secret === '') {
            return false;
        }

        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return hash_equals($secret, trim($matches[1]));
        }

        return false;
    }
}

if (!function_exists('supabaseAuthEmailToUuidMap')) {
    /**
     * @return array<string, string> lowercase email => Supabase auth user UUID
     */
    function supabaseAuthEmailToUuidMap(int $maxPages = 10): array {
        if (supabaseUrl() === '' || supabaseServiceRoleKey() === '') {
            return [];
        }

        $map = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $response = supabaseAdminRequest('GET', 'admin/users?per_page=1000&page=' . $page);
            if (!$response['ok']) {
                break;
            }

            $users = $response['data']['users'] ?? [];
            if (!is_array($users) || $users === []) {
                break;
            }

            foreach ($users as $authUser) {
                $email = strtolower(trim((string) ($authUser['email'] ?? '')));
                $uuid = trim((string) ($authUser['id'] ?? ''));
                if ($email !== '' && $uuid !== '') {
                    $map[$email] = $uuid;
                }
            }

            if (count($users) < 1000) {
                break;
            }
        }

        return $map;
    }
}

if (!function_exists('deleteSupabaseAuthUserByEmail')) {
    function deleteSupabaseAuthUserByEmail(string $email, ?array $emailToUuidMap = null): bool {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $map = $emailToUuidMap ?? supabaseAuthEmailToUuidMap();
        $uuid = $map[$email] ?? null;
        if ($uuid === null || $uuid === '') {
            return true;
        }

        $response = supabaseAdminRequest('DELETE', 'admin/users/' . rawurlencode($uuid));
        return $response['ok'];
    }
}

if (!function_exists('purgeStaleUnverifiedAccounts')) {
    /**
     * @return array{deleted:int, auth_errors:int, db_errors:int, ttl_days:int, candidates:int}
     */
    function purgeStaleUnverifiedAccounts(PDO $pdo, int $batchLimit = 50): array {
        $batchLimit = max(1, min(200, $batchLimit));
        $ttlDays = unverifiedAccountTtlDays();
        $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $cutoffSql = $isPostgres
            ? "NOW() - INTERVAL '{$ttlDays} days'"
            : "NOW() - INTERVAL {$ttlDays} DAY";

        $select = $pdo->query("
            SELECT id, email
            FROM users
            WHERE is_verified = false
              AND role = 'user'
              AND created_at < {$cutoffSql}
            ORDER BY created_at ASC
            LIMIT {$batchLimit}
        ");
        $candidates = $select ? $select->fetchAll(PDO::FETCH_ASSOC) : [];

        $deleted = 0;
        $authErrors = 0;
        $dbErrors = 0;

        $authMap = supabaseAuthEmailToUuidMap();
        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND is_verified = false AND role = \'user\'');

        foreach ($candidates as $row) {
            $userId = (int) ($row['id'] ?? 0);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($userId <= 0 || $email === '') {
                continue;
            }

            if (supabaseUrl() !== '' && supabaseServiceRoleKey() !== '') {
                if (!deleteSupabaseAuthUserByEmail($email, $authMap)) {
                    $authErrors++;
                    error_log('[account_cleanup] Supabase delete failed for ' . $email);
                }
            }

            try {
                $deleteStmt->execute([':id' => $userId]);
                if ($deleteStmt->rowCount() > 0) {
                    $deleted++;
                }
            } catch (Throwable $e) {
                $dbErrors++;
                error_log('[account_cleanup] DB delete failed for user ' . $userId . ': ' . $e->getMessage());
            }
        }

        if ($deleted > 0 || $authErrors > 0 || $dbErrors > 0) {
            error_log(sprintf(
                '[account_cleanup] Purged %d unverified account(s) older than %d day(s); auth_errors=%d db_errors=%d',
                $deleted,
                $ttlDays,
                $authErrors,
                $dbErrors
            ));
        }

        return [
            'deleted' => $deleted,
            'auth_errors' => $authErrors,
            'db_errors' => $dbErrors,
            'ttl_days' => $ttlDays,
            'candidates' => count($candidates),
        ];
    }
}
