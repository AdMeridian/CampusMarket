<?php
// config/db.php
// Central Database Connection using PDO

// Database configuration with support for MySQL (Local) and PostgreSQL (Supabase)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('POSTGRES_PRISMA_URL') ?: '';

$type = getenv('DB_TYPE') ?: (getenv('POSTGRES_HOST') || $databaseUrl ? 'pgsql' : 'mysql');
$host = getenv('DB_HOST') ?: getenv('POSTGRES_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: getenv('POSTGRES_PORT') ?: ($type === 'mysql' ? '3306' : '5432');
$db   = getenv('DB_NAME') ?: getenv('POSTGRES_DATABASE') ?: 'campusmarket';
$user = getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: getenv('DB_Pass') ?: getenv('POSTGRES_PASSWORD') ?: '';

if ($databaseUrl) {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed)) {
        $type = 'pgsql';
        $host = $parsed['host'] ?? $host;
        $port = isset($parsed['port']) ? (string) $parsed['port'] : $port;
        $db = isset($parsed['path']) ? ltrim($parsed['path'], '/') : $db;
        $user = isset($parsed['user']) ? urldecode($parsed['user']) : $user;
        $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $pass;
    }
}

$charset = 'utf8mb4';

// Select DSN based on type
if ($type === 'pgsql') {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
} else {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Always log full error details internally, never expose them to users.
     error_log("DB Connection Error: " . $e->getMessage());
     throw new Exception("Database connection failed. Please try again later.");
}

// Global accessor for the database
return $pdo;
