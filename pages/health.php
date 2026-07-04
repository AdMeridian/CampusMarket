<?php
/**
 * Lightweight preview/production diagnostics (no layout).
 */
header('Content-Type: text/plain; charset=utf-8');

$lines = [];
$lines[] = 'CampusMarket health check';
$lines[] = 'PHP: ' . PHP_VERSION;
$lines[] = 'VERCEL_ENV: ' . (getenv('VERCEL_ENV') ?: '(unset)');
$lines[] = 'HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? '(unset)');

try {
    require_once __DIR__ . '/../config/constants.php';
    $lines[] = 'BASE_URL: ' . BASE_URL;

    require_once ROOT_PATH . 'config/db.php';
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
