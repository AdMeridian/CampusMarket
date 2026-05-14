<?php
// config/db.php
// Central Database Connection using PDO

// Database configuration with support for MySQL (Local) and PostgreSQL (Supabase)
$type = getenv('DB_TYPE') ?: 'mysql'; // Default to mysql for local XAMPP
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: ($type === 'mysql' ? '3306' : '5432');
$db   = getenv('DB_NAME') ?: 'campusmarket';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
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
     if (getenv('VERCEL')) {
         // Log the error internally
         error_log("DB Connection Error: " . $e->getMessage());
     }
     throw new Exception("Database connection failed: " . $e->getMessage());
}

// Global accessor for the database
return $pdo;
