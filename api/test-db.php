<?php
// Vercel Database Debug Tool
// Access this via /test-db.php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain');
echo "Checking Database Connection (via api/ gateway)...\n";
echo "DB_TYPE: " . (getenv('DB_TYPE') ?: 'mysql') . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'localhost') . "\n";

try {
    // Attempt connection
    $pdo = require __DIR__ . '/../config/db.php';
    echo "SUCCESS: Database connection established!\n";
    
    $stmt = $pdo->query("SELECT current_user");
    $user = $stmt->fetchColumn();
    echo "Current DB User: " . $user . "\n";
    
} catch (Exception $e) {
    echo "FAILURE: Connection failed!\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
