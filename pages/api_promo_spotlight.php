<?php
// pages/api_promo_spotlight.php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

try {
    $products = getSpotlightPromoProducts($pdo, 6);
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'products' => $products
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load spotlight products',
        'products' => []
    ]);
}
