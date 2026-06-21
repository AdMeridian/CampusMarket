<?php
/**
 * Simple DB-backed rate limiting (IP + optional suffix).
 */

if (!function_exists('rateLimitAllow')) {
    /**
     * @return array{allowed:bool,remaining:int,retry_after?:int}
     */
    function rateLimitAllow(PDO $pdo, string $bucketKey, int $maxHits, int $windowSeconds = 3600): array {
        $bucketKey = substr(preg_replace('/[^a-zA-Z0-9:_-]/', '', $bucketKey), 0, 120);
        if ($bucketKey === '') {
            return ['allowed' => true, 'remaining' => $maxHits];
        }

        try {
            $tableCheck = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'rate_limit_buckets' LIMIT 1");
            if (!$tableCheck || !$tableCheck->fetchColumn()) {
                return ['allowed' => true, 'remaining' => $maxHits];
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT hit_count, window_start FROM rate_limit_buckets WHERE bucket_key = ? FOR UPDATE');
            $stmt->execute([$bucketKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $now = time();
            if (!$row) {
                $ins = $pdo->prepare('INSERT INTO rate_limit_buckets (bucket_key, hit_count, window_start) VALUES (?, 1, NOW())');
                $ins->execute([$bucketKey]);
                $pdo->commit();
                return ['allowed' => true, 'remaining' => max(0, $maxHits - 1)];
            }

            $windowStart = strtotime((string) $row['window_start']);
            $hitCount = (int) $row['hit_count'];
            if ($windowStart === false || ($now - $windowStart) >= $windowSeconds) {
                $upd = $pdo->prepare('UPDATE rate_limit_buckets SET hit_count = 1, window_start = NOW() WHERE bucket_key = ?');
                $upd->execute([$bucketKey]);
                $pdo->commit();
                return ['allowed' => true, 'remaining' => max(0, $maxHits - 1)];
            }

            if ($hitCount >= $maxHits) {
                $retryAfter = max(1, $windowSeconds - ($now - $windowStart));
                $pdo->commit();
                return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
            }

            $upd = $pdo->prepare('UPDATE rate_limit_buckets SET hit_count = hit_count + 1 WHERE bucket_key = ?');
            $upd->execute([$bucketKey]);
            $pdo->commit();
            return ['allowed' => true, 'remaining' => max(0, $maxHits - $hitCount - 1)];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[rate_limit] ' . $e->getMessage());
            return ['allowed' => true, 'remaining' => $maxHits];
        }
    }
}

if (!function_exists('clientIpAddress')) {
    function clientIpAddress(): string {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return substr($ip, 0, 45);
    }
}
