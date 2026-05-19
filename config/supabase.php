<?php
/**
 * Optional Supabase safe-mode configuration helpers.
 * This does not replace the primary MySQL/PDO app database.
 */

function supabaseUrl(): string {
    return getenv('SUPABASE_URL') ?: (defined('SUPABASE_URL') ? trim((string) SUPABASE_URL) : '');
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
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

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
