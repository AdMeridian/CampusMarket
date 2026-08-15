<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $dbConfig = resolveDatabaseConfig();
    $supaUrl = appEnv('SUPABASE_URL');
    
    // Test PDO connection
    global $pdo;
    $universityCount = 0;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query('SELECT count(*) FROM universities');
            $universityCount = (int) $stmt->fetchColumn();
        } catch (Throwable $t) {
            $universityCount = -1;
        }
    }

    echo json_encode([
        'ok' => true,
        'vercel_env' => appEnv('VERCEL_ENV') ?: 'local',
        'supabase_url' => $supaUrl,
        'resolved_db_host' => $dbConfig['host'] ?? 'unknown',
        'resolved_db_source' => $dbConfig['source'] ?? 'unknown',
        'universities_count_in_db' => $universityCount,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine(),
    ], JSON_PRETTY_PRINT);
}
