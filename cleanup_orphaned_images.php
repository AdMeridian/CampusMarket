<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/supabase.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// 1. Fetch all valid image paths from DB
$stmt = $pdo->query("SELECT image_path FROM product_images");
$dbImages = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Create an associative array for fast lookup
$validImages = [];
foreach ($dbImages as $img) {
    if (!empty($img)) {
        // We only care about the filename or the full path
        // For supabase, image_path is typically the full public URL
        $validImages[$img] = true;
    }
}

$deletedCount = 0;
$scannedCount = 0;

// 2. Scan Supabase Storage 'marketplace' bucket under 'products'
$url = rtrim(supabaseUrl(), '/') . '/storage/v1/object/list/marketplace';
$serviceRoleKey = supabaseServiceRoleKey();

if ($url && $serviceRoleKey) {
    echo "Scanning Supabase Storage 'marketplace/products'...\n";
    $payload = json_encode([
        'prefix' => 'products/',
        'limit' => 1000,
        'offset' => 0,
        'sortBy' => ['column' => 'name', 'order' => 'asc']
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $serviceRoleKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $files = json_decode($response, true);
        if (is_array($files)) {
            foreach ($files as $file) {
                // Skip the folder itself or empty names
                if (empty($file['name']) || $file['name'] === '.emptyFolderPlaceholder') {
                    continue;
                }
                
                // Reconstruct the expected full URL to match DB format
                // In functions.php it expects something like:
                // SUPABASE_URL/storage/v1/object/public/marketplace/products/filename.jpg
                $fileName = $file['name'];
                $expectedPath = rtrim(supabaseUrl(), '/') . '/storage/v1/object/public/marketplace/products/' . $fileName;
                
                $scannedCount++;
                
                if (!isset($validImages[$expectedPath])) {
                    echo "Orphaned image found in Supabase: $expectedPath\n";
                    if (deleteSupabaseStorageObject($expectedPath)) {
                        echo " -> DELETED.\n";
                        $deletedCount++;
                    } else {
                        echo " -> FAILED to delete.\n";
                    }
                }
            }
        }
    } else {
        echo "Failed to list Supabase files. HTTP Code: $httpCode\nResponse: $response\n";
    }
} else {
    echo "Supabase not configured, skipping remote scan.\n";
}

// 3. Scan Local Uploads
$localDir = __DIR__ . '/public/uploads/products';
if (is_dir($localDir)) {
    echo "\nScanning Local Storage '$localDir'...\n";
    $files = scandir($localDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $absPath = $localDir . '/' . $file;
        if (is_file($absPath)) {
            // Reconstruct the relative URL/path saved in DB
            $expectedPath = '/uploads/products/' . $file;
            $scannedCount++;
            
            if (!isset($validImages[$expectedPath])) {
                echo "Orphaned image found Locally: $expectedPath\n";
                if (@unlink($absPath)) {
                    echo " -> DELETED.\n";
                    $deletedCount++;
                } else {
                    echo " -> FAILED to delete.\n";
                }
            }
        }
    }
}

echo "\nDone! Scanned: $scannedCount | Deleted orphaned images: $deletedCount\n";
