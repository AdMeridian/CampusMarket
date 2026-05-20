<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$secret = $_GET['secret'] ?? '';
if ($secret !== 'myantigravitydebug') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

function maskKey(?string $key): string {
    if (empty($key)) return 'empty';
    $len = strlen($key);
    if ($len <= 12) return 'too_short_len_' . $len;
    return substr($key, 0, 6) . '...' . substr($key, -6) . ' (len: ' . $len . ')';
}

$supabaseUrl = getenv('SUPABASE_URL') ?: (defined('SUPABASE_URL') ? SUPABASE_URL : '');
$supabaseAnonKey = getenv('SUPABASE_ANON_KEY') ?: (defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '');
$databaseUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('POSTGRES_PRISMA_URL') ?: '';

$parsedDbUrl = [];
if (!empty($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if ($parsed) {
        $parsedDbUrl = [
            'scheme' => $parsed['scheme'] ?? '',
            'host' => $parsed['host'] ?? '',
            'port' => $parsed['port'] ?? '',
            'path' => $parsed['path'] ?? '',
            'user' => isset($parsed['user']) ? 'present' : 'absent',
            'pass' => isset($parsed['pass']) ? 'present' : 'absent',
        ];
    }
}

echo json_encode([
    'SUPABASE_URL' => $supabaseUrl,
    'SUPABASE_ANON_KEY' => maskKey($supabaseAnonKey),
    'DATABASE_URL_PARSED' => $parsedDbUrl,
    'DB_TYPE' => getenv('DB_TYPE') ?: 'not set',
    'DB_HOST' => getenv('DB_HOST') ?: 'not set',
    'DB_PORT' => getenv('DB_PORT') ?: 'not set',
    'DB_NAME' => getenv('DB_NAME') ?: 'not set',
    'DB_USER' => getenv('DB_USER') ?: 'not set',
    'RESEND_API_KEY' => maskKey(getenv('RESEND_API_KEY')),
    'isSupabaseConfigured' => isSupabaseConfigured() ? 'yes' : 'no',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
