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
                'code'      => 'Unknown',
                'name'      => 'Unknown',
                'domain'    => 'unknown',
                'color'     => '#64748b',
                'bg'        => 'rgba(100, 116, 139, 0.12)',
                'is_campus' => false,
            ];
        }
        $domain = substr($email, $atPos + 1);
        $registry = universityRegistry();

        foreach ($registry as $regDomain => $info) {
            if ($domain === $regDomain || str_ends_with($domain, '.' . $regDomain)) {
                return array_merge(['domain' => $regDomain, 'is_campus' => true], $info);
            }
        }

        // Check if it's an unlisted academic domain (.edu.tr or .edu)
        if (str_ends_with($domain, '.edu.tr') || str_ends_with($domain, '.edu')) {
            $baseName = preg_replace('/\.(edu\.tr|edu)$/i', '', $domain);
            $subParts = explode('.', $baseName);
            $instSlug = end($subParts);
            $cleanCode = strtoupper($instSlug);
            return [
                'code'      => $cleanCode,
                'name'      => $cleanCode . ' Campus',
                'domain'    => $domain,
                'color'     => '#0284c7',
                'bg'        => 'rgba(2, 132, 199, 0.12)',
                'is_campus' => true,
            ];
        }

        // Personal or general email domain (e.g. gmail.com, outlook.com, icloud.com, yahoo.com)
        $cleanProvider = ucfirst(explode('.', $domain)[0] ?? 'Personal');
        return [
            'code'      => $cleanProvider,
            'name'      => $cleanProvider . ' (' . $domain . ')',
            'domain'    => $domain,
            'color'     => '#64748b',
            'bg'        => 'rgba(100, 116, 139, 0.12)',
            'is_campus' => false,
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
 * List of known disposable/temporary email provider domains to reject.
 */
if (!function_exists('isDisposableEmailDomain')) {
    function isDisposableEmailDomain(string $domain): bool {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }

        static $blocked = [
            '10minutemail.com', '10minutemail.net', '10minutemail.co.uk', '10minmail.com',
            'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmailo.com', 'tempr.email',
            'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamail.biz',
            'guerrillamail.de', 'guerrillamailblock.com', 'sharklasers.com', 'grr.la', 'pokemail.net',
            'mailinator.com', 'mailinator.net', 'mailinater.com', 'suremail.info', 'spamherelots.com',
            'dispostable.com', 'yopmail.com', 'yopmail.net', 'yopmail.fr', 'cool.fr.nf', 'jetable.fr.nf',
            'throwawaymail.com', 'trashmail.com', 'trashmail.net', 'trashmail.me', 'trashmail.org',
            'mytemp.email', 'mytempmail.com', 'getairmail.com', 'airmail.ne.tz',
            'mohmal.com', 'mohmal.im', 'mohmal.in', 'crazymailing.com', 'crazymailing.org',
            'burnermail.io', 'fakemailgenerator.com', 'emailfake.com', 'generator.email',
            'emailondeck.com', 'tempail.com', 'inboxkitten.com', 'dropmail.me', 'dropmail.info',
            'mintemail.com', 'nada.ltd', 'getnada.com', 'inboxbear.com', 'maildrop.cc', 'mailnesia.com',
            'discard.email', 'spambox.us', 'disposablemail.com', 'anonbox.net', 'boximail.com',
            'chacuo.net', 'disposable.com', 'easytrashmail.com', 'harakirimail.com', 'incognitomail.org',
            'jetable.org', 'kasmail.com', 'mailcatch.com', 'mailnull.com', 'mytrashmail.com',
            'nobulk.com', 'noclickemail.com', 'pookmail.com', 'safetymail.info', 'sneakemail.com',
            'sofort-mail.de', 'spamfree24.org', 'spamgourmet.com', 'spaml.de', 'superrito.com',
            'armyspy.com', 'cuvox.de', 'dayrep.com', 'fleckens.hu', 'gustr.com', 'jourrapide.com',
            'rhyta.com', 'teleworm.us', 'tinypm.com', 'byom.de', 'trashymail.com', 'mailforspam.com',
            'bouncr.com', 'deadaddress.com', 'e4ward.com', 'gishpuppy.com', 'meltmail.com',
            'spambog.com', 'spambob.com', 'spamevader.com', 'trbvm.com', 'zillamail.com',
            'fastmail.fm', 'owlpic.com', 'mytempemail.com', 'tempinbox.com', 'yomail.info'
        ];

        if (in_array($domain, $blocked, true)) {
            return true;
        }

        foreach ($blocked as $b) {
            if (str_ends_with($domain, '.' . $b)) {
                return true;
            }
        }

        // Catch common burner keywords in custom domains
        if (str_contains($domain, 'tempmail') || str_contains($domain, 'disposable') || str_contains($domain, 'throwaway') || str_contains($domain, 'fake-email') || str_contains($domain, 'fakemail') || str_contains($domain, 'trashmail') || str_contains($domain, '10minute')) {
            return true;
        }

        return false;
    }
}

/**
 * Checks if domain has valid format, is not a disposable provider,
 * and possesses active mail routing (MX or A DNS records).
 */
if (!function_exists('isLegitimateEmailDomain')) {
    function isLegitimateEmailDomain(string $domain, ?string &$reason = null): bool {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !str_contains($domain, '.')) {
            $reason = 'Domain name is missing or invalid.';
            return false;
        }

        // Disallow reserved or local development domains
        $reserved = ['localhost', 'test', 'example', 'invalid', 'local', 'internal', 'lan'];
        $parts = explode('.', $domain);
        $tld = end($parts);
        if (in_array($tld, $reserved, true) || in_array($domain, $reserved, true)) {
            $reason = 'Testing or local domains cannot be used.';
            return false;
        }

        // TLD must be valid characters and at least 2 chars
        if (!preg_match('/^[a-z]{2,24}$/i', $tld)) {
            $reason = 'Email top-level domain is invalid.';
            return false;
        }

        // Check against disposable / burner domain list
        if (isDisposableEmailDomain($domain)) {
            $reason = 'Disposable and temporary email addresses are not allowed. Please use a permanent email.';
            return false;
        }

        // University domain checks for .edu.tr / .edu domains
        $isVerifiedEdu = false;
        if (str_ends_with($domain, '.edu.tr') || str_ends_with($domain, '.edu')) {
            $baseName = preg_replace('/\.(edu\.tr|edu)$/i', '', $domain);
            $subParts = explode('.', $baseName);
            $instSlug = end($subParts);
            $dummyEduSlugs = ['university', 'campus', 'college', 'school', 'student', 'test', 'demo', 'example', 'faculty', 'uni', 'mail'];
            if (in_array($instSlug, $dummyEduSlugs, true)) {
                $reason = 'The specified university domain is not a recognized or genuine academic institution.';
                return false;
            }

            // Real DNS check for the university (checks subdomain or root institution)
            if (function_exists('checkdnsrr')) {
                $rootDomain = $instSlug . (str_ends_with($domain, '.edu.tr') ? '.edu.tr' : '.edu');
                $hasMx = @checkdnsrr($domain, 'MX') || @checkdnsrr($rootDomain, 'MX');
                $hasA  = @checkdnsrr($domain, 'A') || @checkdnsrr($rootDomain, 'A');
                if (!$hasMx && !$hasA) {
                    $reason = 'This university domain does not exist or cannot receive email.';
                    return false;
                }
                $isVerifiedEdu = true;
            }
        }

        // Real DNS verification check (MX record or fallback A/AAAA record)
        if (!$isVerifiedEdu && function_exists('checkdnsrr')) {
            $hasMx = @checkdnsrr($domain, 'MX');
            if (!$hasMx) {
                $hasA = @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA');
                if (!$hasA) {
                    $reason = 'The domain of this email does not exist or cannot receive mail.';
                    return false;
                }
            }
        }

        return true;
    }
}

/**
 * Comprehensive email validator for registration:
 * Allows any university or personal email, while strictly enforcing
 * protection against burner, disposable, and non-existent fake domains.
 */
if (!function_exists('validateSignupEmail')) {
    function validateSignupEmail(string $email, ?string &$error = null): bool {
        $email = strtolower(trim($email));
        if ($email === '') {
            $error = function_exists('__') ? __('auth.register_error_email_required') : 'Email address is required.';
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $error = function_exists('__') ? __('auth.register_error_email_invalid') : 'Please enter a valid email address.';
            return false;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            $error = function_exists('__') ? __('auth.register_error_email_invalid') : 'Please enter a valid email address.';
            return false;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        // Block obvious dummy / troll patterns
        $blockedLocals = ['test', 'asdf', 'fake', 'dummy', 'spam', 'root', 'noreply', 'no-reply'];
        if (in_array($local, $blockedLocals, true) && !isEduTrEmailDomain($domain)) {
            $error = 'Please enter a genuine personal or university email address.';
            return false;
        }

        $domainReason = null;
        if (!isLegitimateEmailDomain($domain, $domainReason)) {
            $error = $domainReason ?: (function_exists('__') ? __('auth.register_error_email_invalid') : 'Please enter a valid email address.');
            return false;
        }

        // If it is a .edu.tr student address, apply student formatting rules
        $studentError = validateUniversityStudentEmail($email);
        if ($studentError !== null) {
            $error = $studentError;
            return false;
        }

        return true;
    }
}

/**
 * Alias for backward compatibility across the codebase.
 */
if (!function_exists('isAllowedUniversityEmail')) {
    function isAllowedUniversityEmail(string $email): bool {
        $err = null;
        return validateSignupEmail($email, $err);
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

/* ─────────────────────────────────────────────────────────────
 * Student Email OTP Verification Helpers
 * ───────────────────────────────────────────────────────────── */

if (!function_exists('ensureStudentVerificationsTableExists')) {
    function ensureStudentVerificationsTableExists(PDO $pdo): void {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_email_verifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    student_email VARCHAR(120) NOT NULL,
                    otp_code VARCHAR(6) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    verified_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (student_email)
                ) ENGINE=InnoDB;
            ");
        } catch (Throwable $e) {
            // PostgreSQL fallback
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS public.student_email_verifications (
                        id BIGSERIAL PRIMARY KEY,
                        user_id BIGINT NOT NULL,
                        student_email VARCHAR(120) NOT NULL,
                        otp_code VARCHAR(6) NOT NULL,
                        expires_at TIMESTAMPTZ NOT NULL,
                        attempts INT NOT NULL DEFAULT 0,
                        verified_at TIMESTAMPTZ NULL,
                        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                    );
                ");
            } catch (Throwable $pe) {}
        }
    }
}

if (!function_exists('createAndSendStudentEmailOtp')) {
    function createAndSendStudentEmailOtp(PDO $pdo, int $userId, string $studentEmail, string $username): array {
        $studentEmail = strtolower(trim($studentEmail));
        if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid student email address.'];
        }

        // Must be a university domain (.edu, .edu.tr, .ac.uk, etc.)
        $atIdx = strrpos($studentEmail, '@');
        $domain = $atIdx !== false ? substr($studentEmail, $atIdx + 1) : '';
        if (function_exists('isDisposableEmailDomain') && isDisposableEmailDomain($domain)) {
            return ['ok' => false, 'error' => 'Disposable or temporary email addresses cannot be used.'];
        }

        $isUni = false;
        if (function_exists('isEduTrEmailDomain') && isEduTrEmailDomain($domain)) $isUni = true;
        if (str_ends_with($domain, '.edu') || str_ends_with($domain, '.ac.uk')) $isUni = true;
        $registry = function_exists('universityRegistry') ? universityRegistry() : [];
        if (array_key_exists($domain, $registry)) $isUni = true;

        if (!$isUni) {
            return ['ok' => false, 'error' => 'Please provide an official university student email address (e.g. @ciu.edu.tr, @neu.edu.tr).'];
        }

        ensureStudentVerificationsTableExists($pdo);

        // Generate cryptographically secure 6-digit OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $ins = $pdo->prepare('
            INSERT INTO student_email_verifications (user_id, student_email, otp_code, expires_at, attempts)
            VALUES (:uid, :email, :otp, :exp, 0)
        ');
        $ins->execute([
            ':uid'   => $userId,
            ':email' => $studentEmail,
            ':otp'   => $otp,
            ':exp'   => $expiresAt
        ]);

        if (function_exists('sendStudentVerificationOtpEmail')) {
            $mailRes = sendStudentVerificationOtpEmail($studentEmail, $username, $otp);
            if (!$mailRes['ok']) {
                error_log('[createAndSendStudentEmailOtp] Mailer warning: ' . ($mailRes['error'] ?? 'unknown'));
            }
        }

        return ['ok' => true, 'email' => $studentEmail];
    }
}

if (!function_exists('verifyStudentEmailOtp')) {
    function verifyStudentEmailOtp(PDO $pdo, int $userId, string $studentEmail, string $otpCode): array {
        $studentEmail = strtolower(trim($studentEmail));
        $otpCode = trim($otpCode);

        ensureStudentVerificationsTableExists($pdo);

        $stmt = $pdo->prepare('
            SELECT id, otp_code, expires_at, attempts, verified_at
            FROM student_email_verifications
            WHERE user_id = :uid AND LOWER(student_email) = :email
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([':uid' => $userId, ':email' => $studentEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'error' => 'No active verification code found for this student email. Please request a new code.'];
        }

        if (!empty($row['verified_at'])) {
            return ['ok' => true, 'already_verified' => true];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'Verification code has expired. Please request a new one.'];
        }

        if ((int)$row['attempts'] >= 5) {
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new verification code.'];
        }

        if ($row['otp_code'] !== $otpCode) {
            $upd = $pdo->prepare('UPDATE student_email_verifications SET attempts = attempts + 1 WHERE id = :id');
            $upd->execute([':id' => $row['id']]);
            $remaining = 4 - (int)$row['attempts'];
            return ['ok' => false, 'error' => 'Incorrect 6-digit code.' . ($remaining > 0 ? " ({$remaining} attempts remaining)" : '')];
        }

        // Code correct! Mark verified
        $upd = $pdo->prepare('UPDATE student_email_verifications SET verified_at = CURRENT_TIMESTAMP WHERE id = :id');
        $upd->execute([':id' => $row['id']]);

        // Extract student ID from email
        $studentId = function_exists('studentIdFromUniversityEmail') ? studentIdFromUniversityEmail($studentEmail) : null;
        if (!$studentId) {
            $local = explode('@', $studentEmail)[0];
            $digits = preg_replace('/\D/', '', $local);
            $studentId = $digits !== '' ? substr($digits, 0, 20) : substr($local, 0, 20);
        }

        // Try to match university ID
        $universityId = null;
        try {
            $domain = substr($studentEmail, strrpos($studentEmail, '@') + 1);
            $uniStmt = $pdo->prepare('SELECT id FROM universities WHERE LOWER(domain_pattern) = :d OR :d LIKE CONCAT("%", LOWER(domain_pattern)) LIMIT 1');
            $uniStmt->execute([':d' => $domain]);
            $universityId = $uniStmt->fetchColumn() ?: null;
        } catch (Throwable $e) {}

        // Update user
        $userUpdates = ['is_verified = TRUE'];
        $userParams = [':uid' => $userId];
        if (!empty($studentId)) {
            $userUpdates[] = 'student_id = :sid';
            $userParams[':sid'] = $studentId;
        }
        if (!empty($universityId)) {
            $userUpdates[] = 'university_id = :unid';
            $userParams[':unid'] = $universityId;
        }

        $userSql = 'UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = :uid';
        $updUser = $pdo->prepare($userSql);
        $updUser->execute($userParams);

        return [
            'ok' => true,
            'student_id' => $studentId,
            'university_id' => $universityId
        ];
    }
}
