<?php
/**
 * Lightweight preview/production diagnostics (no layout).
 */
header('Content-Type: text/plain; charset=utf-8');

define('APP_SKIP_DB_CONNECT', true);
require_once __DIR__ . '/../config/db.php';

$lines = [];
$lines[] = 'CampusMarket health check';
$lines[] = 'PHP: ' . PHP_VERSION;
$lines[] = 'VERCEL_ENV: ' . (appEnv('VERCEL_ENV') ?: '(unset)');

try {
    require_once __DIR__ . '/../config/constants.php';
    $lines[] = 'BASE_URL: ' . BASE_URL;

    foreach (databaseEnvDiagnostics() as $key => $value) {
        $lines[] = $key . ': ' . $value;
    }

    $pdo = connectDatabase();
    $lines[] = 'DB: connected';
    $lines[] = 'DB ping: ' . (string) $pdo->query('SELECT 1')->fetchColumn();

    require_once ROOT_PATH . 'includes/functions.php';
    $recent = getRecentProducts($pdo, 2, HOME_RECENT_LISTING_DAYS);
    $lines[] = 'Recent listings query: ' . count($recent) . ' row(s)';

    $lines[] = 'Status: OK';
} catch (Throwable $e) {
    http_response_code(500);
    $lines[] = 'Status: ERROR';
    $lines[] = $e->getMessage();
    $lines[] = $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $lines) . "\n";
