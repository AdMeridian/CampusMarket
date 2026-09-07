<?php
declare(strict_types=1);

define('APP_SKIP_DB_CONNECT', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

foreach (['DATABASE_URL', 'POSTGRES_URL', 'POSTGRES_PRISMA_URL', 'POSTGRES_URL_NON_POOLING'] as $key) {
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}
putenv('DB_TYPE=mysql');
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=campusmarket');
putenv('DB_USER=root');
putenv('DB_PASS=');
$_ENV['DB_TYPE'] = $_SERVER['DB_TYPE'] = 'mysql';
$_ENV['DB_HOST'] = $_SERVER['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = $_SERVER['DB_PORT'] = '3306';
$_ENV['DB_NAME'] = $_SERVER['DB_NAME'] = 'campusmarket';
$_ENV['DB_USER'] = $_SERVER['DB_USER'] = 'root';
$_ENV['DB_PASS'] = $_SERVER['DB_PASS'] = '';

$pdo = connectDatabase();
ensureServicesTable($pdo);
$added = seedDefaultServices($pdo);
$count = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();

echo "services_count={$count}\n";
echo "added={$added}\n";
