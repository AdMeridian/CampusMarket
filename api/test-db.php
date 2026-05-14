<?php
// Vercel Database Debug Tool (LOUD MODE)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// require __DIR__ . '/../includes/bootstrap.php';

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

$type = getenv('DB_TYPE') ?: (getenv('POSTGRES_HOST') ? 'pgsql' : 'mysql');
$host = getenv('DB_HOST') ?: getenv('POSTGRES_HOST');
$port = getenv('DB_PORT') ?: getenv('POSTGRES_PORT');
$db   = getenv('DB_NAME') ?: getenv('POSTGRES_DATABASE');
$user = getenv('DB_USER') ?: getenv('POSTGRES_USER');
$pass = getenv('DB_PASS') ?: getenv('DB_Pass') ?: getenv('POSTGRES_PASSWORD');

echo "TYPE: $type\n";
echo "HOST: $host\n";
echo "PORT: $port\n";
echo "DB:   $db\n";
echo "USER: $user\n";
echo "PASS: " . ($pass ? "SET (hidden)" : "NOT SET") . "\n\n";

if (!$host || !$user) {
    die("CRITICAL: Essential environment variables (DB_HOST or DB_USER) are missing in Vercel settings!\n");
}

$ref = 'ghfzfzscpjlknooxxfjx';
$ports_to_test = [5432, 6543];
$host_variants = [
    $host,
    'aws-0-ap-southeast-1.pooler.supabase.com'
];
$user_variants = [
    $user,
    "$user.$ref"
];

foreach ($host_variants as $current_host) {
    foreach ($user_variants as $current_user) {
        foreach ($ports_to_test as $current_port) {
            echo "Testing Host: $current_host | User: $current_user | Port: $current_port...\n";
            try {
                $dsn = "pgsql:host=$current_host;port=$current_port;dbname=$db;sslmode=require";
                
                $pdo = new PDO($dsn, $current_user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3
                ]);
                echo "✅ SUCCESS: Connected!\n";
                echo "DSN was: $dsn\n";
                echo "User was: $current_user\n";
                die();
            } catch (PDOException $e) {
                echo "❌ FAILED: " . $e->getMessage() . "\n";
            }
        }
    }
}
