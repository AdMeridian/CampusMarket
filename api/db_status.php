<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $dbConfig = resolveDatabaseConfig();
    $supaUrl = appEnv('SUPABASE_URL');
    
    global $pdo;
    $universityCount = 0;
    $pdoError = null;

    if (isset($pdo)) {
        try {
            $stmt = $pdo->query('SELECT count(*) FROM public.universities');
            $universityCount = (int) $stmt->fetchColumn();
        } catch (Throwable $t) {
            $universityCount = -1;
            $pdoError = $t->getMessage();
        }
    }

    echo json_encode([
        'ok' => true,
        'build_time' => '2026-08-15T22:48:00+03:00',
        'vercel_env' => appEnv('VERCEL_ENV') ?: 'local',
        'supabase_url' => $supaUrl,
        'resolved_db_host' => $dbConfig['host'] ?? 'unknown',
        'resolved_db_user' => $dbConfig['user'] ?? 'unknown',
        'resolved_db_name' => $dbConfig['db'] ?? 'unknown',
        'resolved_db_port' => $dbConfig['port'] ?? 'unknown',
        'resolved_db_source' => $dbConfig['source'] ?? 'unknown',
        'universities_count_in_db' => $universityCount,
        'db_error_details' => $pdoError,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'build_time' => '2026-08-15T22:48:00+03:00',
        'error' => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine(),
    ], JSON_PRETTY_PRINT);
}
