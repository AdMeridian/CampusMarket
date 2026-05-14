<?php
// Vercel Database Debug Tool (LOUD MODE)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain');
echo "--- LOUD DEBUG MODE ---\n";
echo "Checking environment variables...\n";

// Debug info
echo "<h3>Environment Debug</h3>";
echo "DB_TYPE: " . (getenv('DB_TYPE') ?: 'NOT SET (defaulting to mysql)') . "<br>";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET (defaulting to localhost)') . "<br>";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "<br>";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "<br>";
echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "<br>";
echo "VERCEL: " . (getenv('VERCEL') ?: 'NOT SET') . "<br>";
echo "<hr>";

$type = getenv('DB_TYPE') ?: 'mysql';
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

echo "TYPE: $type\n";
echo "HOST: $host\n";
echo "PORT: $port\n";
echo "DB:   $db\n";
echo "USER: $user\n";
echo "PASS: " . ($pass ? "SET (hidden)" : "NOT SET") . "\n\n";

if (!$host || !$user) {
    die("CRITICAL: Essential environment variables (DB_HOST or DB_USER) are missing in Vercel settings!\n");
}

echo "Attempting connection with PDO...\n";
try {
    $dsn = ($type === 'pgsql') 
        ? "pgsql:host=$host;port=$port;dbname=$db;sslmode=require"
        : "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "SUCCESS: Connected to $type!\n";
} catch (PDOException $e) {
    echo "FAILURE: Connection failed!\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "CODE:  " . $e->getCode() . "\n";
}
