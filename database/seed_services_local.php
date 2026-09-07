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

$serviceListings = [
    [2, 11, 'Calculus and Physics Tutoring', 'Patient one-to-one tutoring for calculus, physics, and exam preparation.', 250.00, 'hourly'],
    [3, 15, 'Website Setup and Tech Support', 'Student-friendly help with websites, software setup, and everyday tech problems.', 400.00, 'flat'],
    [4, 14, 'Student Photography Sessions', 'Portraits, graduation photos, and campus event photography with edited digital delivery.', 750.00, 'flat'],
    [5, 13, 'Dorm Moving and Handyman Help', 'Careful help with small moves, furniture assembly, and basic dorm repairs.', 300.00, 'hourly'],
];

$listingStmt = $pdo->prepare(
    "INSERT INTO products (user_id, category_id, title, description, price, price_currency, `condition`, status, listing_type, pricing_model, availability_status, service_expires_at) " .
    "SELECT :user_id, :category_id, :title, :description, :price, 'TRY', 'new', 'active', 'service', :pricing_model, 'available', DATE_ADD(NOW(), INTERVAL 30 DAY) " .
    "WHERE NOT EXISTS (SELECT 1 FROM products WHERE user_id = :existing_user_id AND listing_type = 'service' AND title = :existing_title)"
);

$serviceListingsAdded = 0;
foreach ($serviceListings as [$userId, $categoryId, $title, $description, $price, $pricingModel]) {
    $listingStmt->execute([
        ':user_id' => $userId,
        ':category_id' => $categoryId,
        ':title' => $title,
        ':description' => $description,
        ':price' => $price,
        ':pricing_model' => $pricingModel,
        ':existing_user_id' => $userId,
        ':existing_title' => $title,
    ]);
    $serviceListingsAdded += $listingStmt->rowCount();
}

echo "services_count={$count}\n";
echo "added={$added}\n";
echo "service_listings_added={$serviceListingsAdded}\n";
