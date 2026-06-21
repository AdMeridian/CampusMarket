<?php
/**
 * Optional Supabase safe-mode configuration helpers.
 * This does not replace the primary MySQL/PDO app database.
 */

function supabaseUrl(): string {
    $url = getenv('SUPABASE_URL') ?: (defined('SUPABASE_URL') ? trim((string) SUPABASE_URL) : '');
    return rtrim(trim($url), './');
}

function supabaseAnonKey(): string {
    return getenv('SUPABASE_ANON_KEY') ?: (defined('SUPABASE_ANON_KEY') ? trim((string) SUPABASE_ANON_KEY) : '');
}

function isSupabaseConfigured(): bool {
    return supabaseUrl() !== '' && supabaseAnonKey() !== '';
}

function supabaseAuthRequest(string $method, string $path, ?array $payload = null): array {
    if (!isSupabaseConfigured()) {
        return ['ok' => false, 'status' => 500, 'error' => 'Supabase is not configured'];
    }

    $url = rtrim(supabaseUrl(), '/') . '/auth/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = [
        'apikey: ' . supabaseAnonKey(),
        'Content-Type: application/json',
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $decoded = json_decode((string) $body, true);
    $isOk = $status >= 200 && $status < 300;

    if ($isOk) {
        return ['ok' => true, 'status' => $status, 'data' => is_array($decoded) ? $decoded : []];
    }

    return [
        'ok' => false,
        'status' => $status,
        'error' => $curlErr !== '' ? $curlErr : (is_array($decoded) ? ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? $decoded['code'] ?? 'Supabase auth request failed') : (string) $body),
        'data' => is_array($decoded) ? $decoded : [],
    ];
}

function supabaseServiceRoleKey(): string {
    return getenv('SUPABASE_SERVICE_ROLE_KEY') ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? trim((string) SUPABASE_SERVICE_ROLE_KEY) : '');
}

function supabaseAdminRequest(string $method, string $path, ?array $payload = null): array {
    $serviceRoleKey = supabaseServiceRoleKey();
    if (supabaseUrl() === '' || $serviceRoleKey === '') {
        return ['ok' => false, 'status' => 500, 'error' => 'Supabase admin is not configured'];
    }

    $url = rtrim(supabaseUrl(), '/') . '/auth/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = [
        'apikey: ' . $serviceRoleKey,
        'Authorization: Bearer ' . $serviceRoleKey,
        'Content-Type: application/json',
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $decoded = json_decode((string) $body, true);
    $isOk = $status >= 200 && $status < 300;

    if ($isOk) {
        return ['ok' => true, 'status' => $status, 'data' => is_array($decoded) ? $decoded : []];
    }

    return [
        'ok' => false,
        'status' => $status,
        'error' => $curlErr !== '' ? $curlErr : (is_array($decoded) ? ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? $decoded['code'] ?? 'Supabase auth request failed') : (string) $body),
        'data' => is_array($decoded) ? $decoded : [],
    ];
}

function supabaseSignupRedirectUrl(): string {
    $isSecureRequest = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
    $originHost = $_SERVER['HTTP_HOST'] ?? '';
    $originScheme = $isSecureRequest ? 'https' : 'http';
    return $originHost !== ''
        ? ($originScheme . '://' . $originHost . '/pages/verify_email.php?source=supabase')
        : (BASE_URL . 'pages/verify_email.php?source=supabase');
}

/**
 * Ask Supabase to resend the signup confirmation email.
 */
function resendSignupVerificationEmail(string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    $result = supabaseAuthRequest('POST', 'resend', [
        'email' => $email,
        'type' => 'signup',
        'options' => [
            'emailRedirectTo' => supabaseSignupRedirectUrl(),
        ],
    ]);

    if ($result['ok']) {
        return ['ok' => true, 'message' => 'Verification email sent. Check your inbox and spam folder.'];
    }

    $rawErr = strtolower(trim((string) ($result['error'] ?? 'unknown')));
    $status = (int) ($result['status'] ?? 0);

    if ($status === 429 || str_contains($rawErr, 'rate limit')) {
        return ['ok' => false, 'error' => 'Too many requests. Please wait a minute and try again.'];
    }

    // Supabase may return errors for unknown emails; keep the response generic.
    return ['ok' => true, 'message' => 'If an account exists for this email, a new verification link has been sent.'];
}

/**
 * Extract marketplace bucket object path from a public Supabase Storage URL.
 */
function supabaseStorageObjectPathFromUrl(string $path): ?string {
    if (!preg_match('#/storage/v1/object/(?:public/)?marketplace/(.+)$#i', $path, $matches)) {
        return null;
    }
    return rawurldecode($matches[1]);
}

/**
 * Delete an object from the marketplace Supabase Storage bucket (service role).
 */
function deleteSupabaseStorageObject(string $imagePath): bool {
    $objectPath = supabaseStorageObjectPathFromUrl($imagePath);
    if ($objectPath === null) {
        return false;
    }

    $serviceRoleKey = supabaseServiceRoleKey();
    if ($serviceRoleKey === '' || supabaseUrl() === '') {
        return false;
    }

    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    $url = rtrim(supabaseUrl(), '/') . '/storage/v1/object/marketplace/' . $encodedPath;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'apikey: ' . $serviceRoleKey,
        ],
    ]);
    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $httpCode >= 200 && $httpCode < 300;
}
