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
            'final.edu.tr'       => 'Final International University',
            'kstu.edu.tr'        => 'Cyprus Health & Social Sciences University',
            'arucad.edu.tr'      => 'Arkın University of Creative Arts & Design',
        ];
    }
}

if (!function_exists('universityRegistry')) {
    function universityRegistry(): array {
        return [
            'ciu.edu.tr' => [
                'code'  => 'CIU',
                'name'  => 'Cyprus International University',
                'color' => '#0284c7',
                'bg'    => 'rgba(2, 132, 199, 0.12)',
            ],
            'emu.edu.tr' => [
                'code'  => 'EMU',
                'name'  => 'Eastern Mediterranean University',
                'color' => '#d97706',
                'bg'    => 'rgba(217, 119, 6, 0.12)',
            ],
            'neu.edu.tr' => [
                'code'  => 'NEU',
                'name'  => 'Near East University',
                'color' => '#059669',
                'bg'    => 'rgba(5, 150, 105, 0.12)',
            ],
            'metu.edu.tr' => [
                'code'  => 'METU NCC',
                'name'  => 'Middle East Technical University NCC',
                'color' => '#dc2626',
                'bg'    => 'rgba(220, 38, 38, 0.12)',
            ],
            'gau.edu.tr' => [
                'code'  => 'GAU',
                'name'  => 'Girne American University',
                'color' => '#7c3aed',
                'bg'    => 'rgba(124, 58, 237, 0.12)',
            ],
            'eul.edu.tr' => [
                'code'  => 'EUL',
                'name'  => 'European University of Lefke',
                'color' => '#0891b2',
                'bg'    => 'rgba(8, 145, 178, 0.12)',
            ],
            'baucyprus.edu.tr' => [
                'code'  => 'BAU',
                'name'  => 'Bahçeşehir Cyprus University',
                'color' => '#db2777',
                'bg'    => 'rgba(219, 39, 119, 0.12)',
            ],
            'kyrenia.edu.tr' => [
                'code'  => 'UoK',
                'name'  => 'University of Kyrenia',
                'color' => '#2563eb',
                'bg'    => 'rgba(37, 99, 235, 0.12)',
            ],
            'final.edu.tr' => [
                'code'  => 'FIU',
                'name'  => 'Final International University',
                'color' => '#0d9488',
                'bg'    => 'rgba(13, 148, 136, 0.12)',
            ],
            'kstu.edu.tr' => [
                'code'  => 'KSTU',
                'name'  => 'Cyprus Health and Social Sciences University',
                'color' => '#ea580c',
                'bg'    => 'rgba(234, 88, 12, 0.12)',
            ],
            'arucad.edu.tr' => [
                'code'  => 'ARUCAD',
                'name'  => 'Arkın University of Creative Arts & Design',
                'color' => '#4f46e5',
                'bg'    => 'rgba(79, 70, 229, 0.12)',
            ],
            'campusmarketplace.site' => [
                'code'  => 'Staff / Demo',
                'name'  => 'CampusMarket Internal',
                'color' => '#475569',
                'bg'    => 'rgba(71, 85, 105, 0.12)',
            ],
        ];
    }
}

if (!function_exists('getUniversityInfoFromEmail')) {
    function getUniversityInfoFromEmail(string $email): array {
        $email = strtolower(trim($email));
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return [
                'code'   => 'Unknown',
                'name'   => 'Unknown Campus',
                'domain' => 'unknown',
                'color'  => '#64748b',
                'bg'     => 'rgba(100, 116, 139, 0.12)',
            ];
        }
        $domain = substr($email, $atPos + 1);
        $registry = universityRegistry();

        foreach ($registry as $regDomain => $info) {
            if ($domain === $regDomain || str_ends_with($domain, '.' . $regDomain)) {
                return array_merge(['domain' => $regDomain], $info);
            }
        }

        $cleanName = strtoupper(explode('.', $domain)[0] ?? 'CAMPUS');
        return [
            'code'   => $cleanName,
            'name'   => $cleanName . ' Campus',
            'domain' => $domain,
            'color'  => '#64748b',
            'bg'     => 'rgba(100, 116, 139, 0.12)',
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
 * True when the domain is a Turkish university address (e.g. ciu.edu.tr, staff.neu.edu.tr).
 */
if (!function_exists('isEduTrEmailDomain')) {
    function isEduTrEmailDomain(string $domain): bool {
        $domain = strtolower(trim($domain));
        if ($domain === '' || $domain === 'edu.tr') {
            return false;
        }

        return str_ends_with($domain, '.edu.tr');
    }
}

/**
 * List of known disposable/temporary email provider domains to reject.
 */
if (!function_exists('isDisposableEmailDomain')) {
    function isDisposableEmailDomain(string $domain): bool {
        $domain = strtolower(trim($domain));
        static $blocked = [
            '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org',
            'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com',
            'mailinator.com', 'dispostable.com', 'yopmail.com', 'yopmail.net',
            'throwawaymail.com', 'trashmail.com', 'mytemp.email', 'getairmail.com',
            'mohmal.com', 'crazymailing.com', 'burnermail.io', 'fakemailgenerator.com',
            'emailondeck.com', 'tempail.com', 'inboxkitten.com', 'dropmail.me',
            'generator.email', 'mintemail.com', 'trashmail.net', 'trashmail.me'
        ];
        if (in_array($domain, $blocked, true)) {
            return true;
        }
        foreach ($blocked as $b) {
            if (str_ends_with($domain, '.' . $b)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * True if the email is well-formed AND is either a university email,
 * an allowed personal email (e.g. gmail.com, outlook.com, icloud.com),
 * and NOT a disposable/burner domain.
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

        // Reject disposable burner emails immediately
        if (isDisposableEmailDomain($domain)) {
            return false;
        }

        // Allow any university domain (*.edu.tr, *.edu, *.ac.uk, etc.)
        if (isEduTrEmailDomain($domain) || str_ends_with($domain, '.edu') || str_ends_with($domain, '.ac.uk')) {
            return true;
        }

        // Allowed major consumer & campus domains
        $allowedDomains = [
            'gmail.com',
            'googlemail.com',
            'outlook.com',
            'hotmail.com',
            'live.com',
            'icloud.com',
            'yahoo.com',
            'proton.me',
            'protonmail.com',
            'campusmarketplace.site',
        ];

        if (in_array($domain, $allowedDomains, true)) {
            return true;
        }

        // Allow any domain that is registered in universityRegistry()
        $registry = universityRegistry();
        if (array_key_exists($domain, $registry)) {
            return true;
        }

        return false;
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

