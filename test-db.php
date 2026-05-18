<?php
require_once 'includes/bootstrap.php';

header('Content-Type: text/plain');
echo "Checking product_images table in local MySQL...\n";

try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT * FROM product_images LIMIT 15");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: " . $row['id'] . " | Product ID: " . $row['product_id'] . " | Path: " . $row['image_path'] . "\n";
        }
    } else {
        echo "FAILURE: \$pdo not defined\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
