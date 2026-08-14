<?php
// pages/api_search_suggestions.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $params = [];
    $filterSql = productSearchFilterSql($query, $params, 'p', 'c');
    
    $sql = "SELECT p.id, p.title, p.price, p.discount_percent, p.price_currency, c.name as category_name, i.image_path 
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
            WHERE p.status = 'active'" . $filterSql;
            
    $orderBySql = productSearchOrderBySql($query, $params, 'p', 'c');
    $sql .= " " . $orderBySql . " LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results to ensure URLs are absolute
    foreach ($results as &$row) {
        $row['image_url'] = getProductImage($row['image_path'] ?? null);
        
        // calculate final price since discount could be applied
        $final_price = getDiscountedPrice($row);
        $row['formatted_price'] = formatPrice($final_price, productCurrencyCode($row));
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
    
} catch (Throwable $e) {
    error_log("Search suggestions error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
