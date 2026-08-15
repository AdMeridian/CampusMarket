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
 * University domains (registration dropdown — convenience only)
 *
 * Any *@*.edu.tr address is accepted at signup. This list is for
 * quick selection in the UI; use "Other" for subdomains like std.*.
 * ───────────────────────────────────────────────────────────── */
if (!function_exists('allowedUniversityDomains')) {
    function allowedUniversityDomains(): array {
        return [
            'neu.edu.tr'         => 'Near East University',
            'ciu.edu.tr'         => 'Cyprus International University',
            'baucyprus.edu.tr'   => 'Bahçeşehir Cyprus University',
            'eul.edu.tr'         => 'European University of Lefke',
            'emu.edu.tr'         => 'Eastern Mediterranean University',
            'gau.edu.tr'         => 'Girne American University',
            'metu.edu.tr'        => 'Middle East Technical University NCC',
            'kyrenia.edu.tr'     => 'University of Kyrenia',
        ];
    }
}

/** Select option value for a custom *.edu.tr domain on the registration form. */
if (!function_exists('universityDomainCustomOption')) {
    function universityDomainCustomOption(): string {
        return '__custom_edu_tr__';
    }
}

/**
 * True when the domain is a valid university academic address (e.g. .edu.tr, .ac.uk, .edu, .edu.de).
 */
if (!function_exists('isEduTrEmailDomain')) {
    function isEduTrEmailDomain(string $domain): bool {
        $domain = strtolower(trim($domain));
        if ($domain === '' || $domain === 'edu.tr' || $domain === 'edu' || $domain === 'ac.uk') {
            return false;
        }

        return str_ends_with($domain, '.edu.tr')
            || str_ends_with($domain, '.ac.uk')
            || str_ends_with($domain, '.edu')
            || str_ends_with($domain, '.edu.de')
            || str_ends_with($domain, '.edu.ca')
            || str_ends_with($domain, '.edu.au')
            || str_ends_with($domain, '.tum.de');
    }
}

/**
 * True if the email is well-formed AND uses an allowed campus domain worldwide.
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

        $hiddenDomains = ['campusmarketplace.site'];
        if (in_array($domain, $hiddenDomains, true)) {
            return true;
        }

        return isEduTrEmailDomain($domain);
    }
}

/**
 * Legacy hook — student-number rules are not enforced; staff use the same .edu.tr rule.
 */
if (!function_exists('universityEmailRequiresStudentNumber')) {
    function universityEmailRequiresStudentNumber(string $domain): bool {
        return false;
    }
}

/**
 * Build a full university email from the local part and domain.
 */
if (!function_exists('buildUniversityEmail')) {
    function buildUniversityEmail(string $local, string $domain): string {
        return strtolower(trim($local)) . '@' . strtolower(trim($domain));
    }
}

/**
 * Returns a validation error message, or null when the local part looks valid.
 */
if (!function_exists('validateUniversityStudentEmail')) {
    function validateUniversityStudentEmail(string $email): ?string {
        $email = strtolower(trim($email));
        $atIdx = strrpos($email, '@');
        if ($atIdx === false) {
            return null;
        }
        $local  = substr($email, 0, $atIdx);
        $domain = substr($email, $atIdx + 1);
        if (!isEduTrEmailDomain($domain)) {
            return null;
        }
        if ($local === '' || strlen($local) > 64) {
            return function_exists('__')
                ? __('auth.register_error_email_local_empty')
                : 'Enter the part before the @ in your university email.';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._+-]*$/i', $local)) {
            return function_exists('__')
                ? __('auth.register_error_email_local_format')
                : 'University email can only use letters, numbers, dots, hyphens, and underscores before the @.';
        }
        return null;
    }
}

/**
 * Extract student_id from a university email, or null when not applicable.
 * Prefers the numeric portion when the local part mixes letters and digits.
 */
if (!function_exists('studentIdFromUniversityEmail')) {
    function studentIdFromUniversityEmail(string $email): ?string {
        $email = strtolower(trim($email));
        $atIdx = strrpos($email, '@');
        if ($atIdx === false) {
            return null;
        }
        $local  = substr($email, 0, $atIdx);
        $domain = substr($email, $atIdx + 1);
        if (!universityEmailRequiresStudentNumber($domain) || $local === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $local)) {
            return substr($local, 0, 20);
        }
        $digits = preg_replace('/\D/', '', $local);
        if ($digits !== '') {
            return substr($digits, 0, 20);
        }
        return strlen($local) <= 20 ? $local : substr($local, 0, 20);
    }
}

/**
 * Human-readable hint for error messages.
 */
if (!function_exists('allowedDomainsList')) {
    function allowedDomainsList(): string {
        return function_exists('__')
            ? __('auth.register_allowed_email_hint')
            : 'university email';
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

/* ─────────────────────────────────────────────────────────────
 * Multi-Country & University Helpers
 * ───────────────────────────────────────────────────────────── */

if (!function_exists('getUserUniversityAndCountry')) {
    function getUserUniversityAndCountry(PDO $pdo, int $userId): ?array {
        try {
            $stmt = $pdo->prepare('
                SELECT 
                    u.id AS user_id,
                    un.id AS university_id,
                    un.name AS university_name,
                    un.slug AS university_slug,
                    un.domain_pattern,
                    un.city AS university_city,
                    c.code AS country_code,
                    c.name AS country_name,
                    c.default_currency,
                    c.currency_symbol,
                    c.symbol_position
                FROM users u
                JOIN universities un ON un.id = u.university_id
                JOIN countries c ON c.code = un.country_code
                WHERE u.id = :uid LIMIT 1
            ');
            $stmt->execute([':uid' => $userId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('lookupUniversityByEmail')) {
    function lookupUniversityByEmail(PDO $pdo, string $email): ?array {
        try {
            $stmt = $pdo->prepare('SELECT * FROM get_university_by_email(:email)');
            $stmt->execute([':email' => $email]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('getAvailableCountries')) {
    function getAvailableCountries(PDO $pdo): array {
        try {
            $stmt = $pdo->query('SELECT code, name, default_currency, currency_symbol, symbol_position FROM countries WHERE is_active = TRUE ORDER BY name ASC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('getAvailableUniversities')) {
    function getAvailableUniversities(PDO $pdo, ?string $countryCode = null): array {
        try {
            if ($countryCode) {
                $stmt = $pdo->prepare('SELECT id, name, slug, domain_pattern, city, country_code FROM universities WHERE is_active = TRUE AND country_code = :cc ORDER BY name ASC');
                $stmt->execute([':cc' => strtoupper($countryCode)]);
            } else {
                $stmt = $pdo->query('SELECT id, name, slug, domain_pattern, city, country_code FROM universities WHERE is_active = TRUE ORDER BY name ASC');
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('formatProductPriceWithCurrency')) {
    function formatProductPriceWithCurrency(float $price, string $currencyCode = 'USD'): string {
        $currencies = defined('PRODUCT_CURRENCIES') ? PRODUCT_CURRENCIES : [
            'TRY' => ['symbol' => '₺', 'position' => 'after'],
            'USD' => ['symbol' => '$', 'position' => 'before'],
            'EUR' => ['symbol' => '€', 'position' => 'before'],
            'GBP' => ['symbol' => '£', 'position' => 'before'],
            'CAD' => ['symbol' => '$', 'position' => 'before'],
        ];

        $info = $currencies[strtoupper($currencyCode)] ?? ['symbol' => '$', 'position' => 'before'];
        $formattedPrice = number_format($price, 2);

        return $info['position'] === 'after'
            ? $formattedPrice . ' ' . $info['symbol']
            : $info['symbol'] . $formattedPrice;
    }
}
