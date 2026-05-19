<?php
/**
 * includes/functions_member2.php
 *
 * Member 2 — Authentication & User Management helpers.
 * General helpers (sanitize, setFlash, requireLogin, uploadImage, avatarUrl…)
 * come from Member 1's includes/functions.php.
 *
 * Each function wrapped in function_exists() so this file is safe to load
 * even if Member 1 adds an identically-named helper to functions.php.
 */

/* ─────────────────────────────────────────────────────────────
 * University-email allowlist
 *
 * Key   = exact lowercase domain (after the @)
 * Value = friendly label for UI/error messages
 *
 * Add new domains here as the team approves more institutions.
 * No code changes needed elsewhere.
 * ───────────────────────────────────────────────────────────── */
if (!function_exists('allowedUniversityDomains')) {
    function allowedUniversityDomains(): array {
        return [
            'std.neu.edu.tr' => 'Near East University (Student)',
            // 'staff.neu.edu.tr'   => 'Near East University (Staff)',
            // 'students.aul.edu.lb' => 'Another University',
        ];
    }
}

/**
 * True if the email is well-formed AND its domain is on the allowlist.
 */
if (!function_exists('isAllowedUniversityEmail')) {
    function isAllowedUniversityEmail(string $email): bool {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $atIdx = strrpos($email, '@');
        if ($atIdx === false) {
            return false;
        }
        $domain = substr($email, $atIdx + 1);
        
        // Check public university domains
        if (array_key_exists($domain, allowedUniversityDomains())) {
            return true;
        }
        
        // Silently allow admin/hidden domains without listing them in public UI
        $hiddenDomains = ['campusmarketplace.site'];
        if (in_array($domain, $hiddenDomains)) {
            return true;
        }
        
        return false;
    }
}

/**
 * Comma-separated list of allowed @domain tags, e.g. "@std.neu.edu.tr".
 * Used in placeholders and error messages so they stay in sync with the dict.
 */
if (!function_exists('allowedDomainsList')) {
    function allowedDomainsList(): string {
        $domains = array_keys(allowedUniversityDomains());
        return implode(', ', array_map(fn($d) => '@' . $d, $domains));
    }
}

/**
 * Generate a cryptographically random verification token.
 * 64 hex chars = 32 bytes of entropy. Fits the schema's VARCHAR(128).
 */
if (!function_exists('generateVerificationToken')) {
    function generateVerificationToken(): string {
        return bin2hex(random_bytes(32));
    }
}

/* ─────────────────────────────────────────────────────────────
 * Profile helpers (existing)
 * ───────────────────────────────────────────────────────────── */

if (!function_exists('sellerRatingSummary')) {
    function sellerRatingSummary(PDO $pdo, int $userId): array {
        $stmt = $pdo->prepare('
            SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count
            FROM ratings
            WHERE seller_id = :uid
        ');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'avg'   => $row && $row['avg_rating'] !== null ? (float) $row['avg_rating'] : 0.0,
            'count' => $row ? (int) $row['rating_count'] : 0,
        ];
    }
}

if (!function_exists('renderStars')) {
    function renderStars(float $avg): string {
        $full = (int) floor($avg);
        $half = ($avg - $full) >= 0.5;
        $html = '';
        for ($i = 0; $i < $full; $i++)              $html .= '★';
        if ($half)                                  $html .= '⯨';
        for ($i = $full + ($half ? 1 : 0); $i < 5; $i++) $html .= '☆';
        return $html;
    }
}

if (!function_exists('formatJoinDate')) {
    function formatJoinDate(string $timestamp): string {
        $ts = strtotime($timestamp);
        return $ts ? date('M Y', $ts) : 'Unknown';
    }
}
