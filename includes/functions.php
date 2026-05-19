<?php
// ============================================================
// CampusMarket — Shared Utility Functions
// ============================================================

// ─── Input & Output ──────────────────────────────────────

/**
 * Sanitize user input to prevent XSS
 */
function sanitize(?string $input): string {
    return strip_tags(trim((string)$input));
}

/**
 * Sanitize user input (from local work)
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Redirect to a URL
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message in session
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Display and clear the flash message (call in header or page)
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Auth Helpers ────────────────────────────────────────

/**
 * Check if a user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an admin
 */
function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get the current logged-in user's ID
 */
function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require login — redirect to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'You must be logged in to access that page.');
        redirect(BASE_URL . 'pages/login.php');
    }
}

/**
 * Require admin role — redirect to home if not admin
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect(BASE_URL . 'index.php');
    }
}

// ─── Formatting ──────────────────────────────────────────

/**
 * Format a price in Turkish Lira (Local work)
 */
function formatPrice($amount): string {
    return number_format((float)$amount) . ' ' . APP_CURRENCY;
}

/**
 * Calculate discounted listing price.
 */
function getDiscountedPrice(array $product): float {
    $base = (float)($product['price'] ?? 0);
    $discountPercent = (int)($product['discount_percent'] ?? 0);
    if ($discountPercent <= 0) {
        return $base;
    }
    $discountPercent = max(0, min(90, $discountPercent));
    return round($base * (1 - ($discountPercent / 100)), 2);
}

/**
 * Check if a listing is old enough for seller discounting.
 */
function isDiscountEligible(array $product, int $minimumDays = LISTING_DISCOUNT_MIN_DAYS): bool {
    if (($product['status'] ?? 'active') !== 'active') return false;
    if (!isset($product['created_at'])) return false;
    $created = strtotime((string)$product['created_at']);
    if (!$created) return false;
    return ((time() - $created) >= ($minimumDays * 86400));
}

/**
 * Render product price with discount visual when applicable.
 */
function renderProductPrice(array $product): string {
    $discountPercent = (int)($product['discount_percent'] ?? 0);
    $base = (float)($product['price'] ?? 0);
    $final = getDiscountedPrice($product);
    if ($discountPercent <= 0 || $final >= $base) {
        return '<span>' . formatPrice($base) . '</span>';
    }
    return
        '<span style="font-weight:800;color:var(--primary);">' . formatPrice($final) . '</span> ' .
        '<span style="text-decoration:line-through;opacity:.65;font-weight:600;font-size:.9em;">' . formatPrice($base) . '</span> ' .
        '<span class="badge badge-new" style="font-size:.68rem;padding:.15rem .45rem;">-' . $discountPercent . '%</span>';
}

/**
 * Human-readable time ago (e.g., "3 hours ago")
 */
function timeAgo(?string $datetime): string {
    if (!$datetime) return 'Recently';
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year'   . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month'  . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'    . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'   . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Get a condition badge label and CSS class
 */
function conditionBadge(?string $condition): array {
    return match($condition) {
        'new'      => ['label' => 'New',      'class' => 'badge-new'],
        'like_new' => ['label' => 'Like New', 'class' => 'badge-like-new'],
        'used'     => ['label' => 'Used',     'class' => 'badge-used'],
        'poor'     => ['label' => 'Poor',     'class' => 'badge-poor'],
        default    => ['label' => 'Unknown',  'class' => 'badge-used'],
    };
}

// ─── File Upload ─────────────────────────────────────────

/**
 * Professional Image Upload Handler
 * Returns ['success' => bool, 'path' => string]
 */
function handleUpload(array $file, string $subfolder = 'products/'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // Basic Validation
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large'];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $ext;
    $subfolder = trim($subfolder, '/');
    $objectName = $subfolder . '/' . $filename;
    
    // Check if Supabase env vars are set
    require_once __DIR__ . '/../config/supabase.php';
    $supabaseUrl = supabaseUrl();
    $supabaseKey = supabaseAnonKey();

    if (empty($supabaseUrl) || empty($supabaseKey)) {
        // Local upload fallback if Supabase not configured
        $relPath = 'uploads/' . $objectName;
        $absPath = __DIR__ . '/../public/' . $relPath;
        $dir = dirname($absPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $absPath)) {
            return ['success' => true, 'path' => $relPath];
        }
        return ['success' => false, 'error' => 'Failed to move file'];
    }

    // Upload to Supabase Storage
    $bucket = 'marketplace';
    $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $objectName;
    
    $fileData = file_get_contents($file['tmp_name']);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $supabaseKey,
        "apikey: " . $supabaseKey,
        "Content-Type: " . $file['type']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80000) {
        curl_close($ch);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Return absolute URL so it works seamlessly on frontend
        $publicUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectName;
        return ['success' => true, 'path' => $publicUrl];
    } else {
        return ['success' => false, 'error' => 'Upload failed: ' . $response];
    }
}

/**
 * Get product image URL
 */
function getProductImage(?string $path): string {
    if (empty($path)) {
        return BASE_URL . 'public/images/default-product.png';
    }
    if (strpos($path, 'http') === 0) {
        return $path;
    }
    return rtrim(BASE_URL, '/') . '/public/' . ltrim($path, '/');
}

/**
 * Backward-compatible upload helper used by older pages.
 * Returns relative path string on success, false on failure.
 */
function uploadImage(array $file, string $subfolder = 'products') {
    $normalizedSubfolder = rtrim($subfolder, '/') . '/';
    $result = handleUpload($file, $normalizedSubfolder);
    return $result['success'] ? $result['path'] : false;
}

// ─── Pagination ──────────────────────────────────────────

/**
 * Calculate offset for SQL LIMIT/OFFSET
 */
function getOffset(int $page, int $perPage = ITEMS_PER_PAGE): int {
    return ($page - 1) * $perPage;
}

/**
 * Render simple prev/next pagination links
 */
function paginationLinks(int $totalItems, int $currentPage, string $baseUrl): string {
    $totalPages = (int) ceil($totalItems / ITEMS_PER_PAGE);
    if ($totalPages <= 1) return '';

    $html  = '<div class="pagination">';
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '" class="btn-page">← Prev</a>';
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html  .= '<a href="' . $baseUrl . '?page=' . $i . '" class="btn-page' . $active . '">' . $i . '</a>';
    }
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '" class="btn-page">Next →</a>';
    }
    $html .= '</div>';
    return $html;
}

// ─── Notifications ───────────────────────────────────────

/**
 * Create a notification for a user
 */
function createNotification(PDO $pdo, int $userId, string $type, string $title, string $body, ?int $referenceId = null): void {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body, reference_id)
        VALUES (:user_id, :type, :title, :body, :ref_id)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':type'    => $type,
        ':title'   => $title,
        ':body'    => $body,
        ':ref_id'  => $referenceId,
    ]);
}

/**
 * Count unread notifications for a user
 */
function countUnreadNotifications(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Count unread chat messages for a user.
 */
function countUnreadMessages(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

// ─── Marketplace Data Helpers ────────────────────────────

/**
 * Fetch the latest active products for the homepage
 */
function getRecentProducts(PDO $pdo, int $limit = 8): array {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch featured products for the homepage scroller
 */
function getFeaturedProducts(PDO $pdo, int $limit = 6): array {
    static $hasFeaturedUntil = null;
    if ($hasFeaturedUntil === null) {
        $colStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'products'
              AND column_name = 'featured_until'
            LIMIT 1
        ");
        $colStmt->execute();
        $hasFeaturedUntil = (bool) $colStmt->fetchColumn();
    }

    $featuredWindowFilter = $hasFeaturedUntil
        ? " AND (p.featured_until IS NULL OR p.featured_until > NOW())"
        : "";

    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.status = 'active' AND p.is_featured = TRUE{$featuredWindowFilter}
        ORDER BY p.discount_set_at DESC, p.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch all categories with a count of active products in each
 */
function getTopCategories(PDO $pdo): array {
    return $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        GROUP BY c.id
        ORDER BY product_count DESC
    ")->fetchAll();
}

/**
 * Fetch top donors for the Hall of Fame
 */
function getDonors(PDO $pdo, int $limit = 5): array {
    static $hasPromotionPayments = null;
    if ($hasPromotionPayments === null) {
        $tableStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'promotion_payments'
            LIMIT 1
        ");
        $tableStmt->execute();
        $hasPromotionPayments = (bool) $tableStmt->fetchColumn();
    }

    if (!$hasPromotionPayments) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT u.username, u.avatar
        FROM promotion_payments pp
        JOIN users u ON u.id = pp.user_id
        WHERE pp.payment_type = 'donation' AND pp.status = 'approved'
        GROUP BY u.id, u.username, u.avatar
        ORDER BY MAX(pp.approved_at) DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get average rating and count for a seller
 */
function getSellerRating(PDO $pdo, int $sellerId): array {
    $stmt = $pdo->prepare("
        SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as review_count
        FROM ratings
        WHERE seller_id = :sid
    ");
    $stmt->execute([':sid' => $sellerId]);
    $result = $stmt->fetch();
    return [
        'avg'   => $result['avg_rating'] ?? 0,
        'count' => $result['review_count']
    ];
}

/**
 * Compute seller trust score (0-100) based on reviews, completion reliability, and sell speed.
 */
function getSellerTrustScore(PDO $pdo, int $sellerId): array {
    $rating = getSellerRating($pdo, $sellerId);
    $avgRating = (float)($rating['avg'] ?? 0);
    $reviewCount = (int)($rating['count'] ?? 0);

    $orderStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            AVG(CASE WHEN o.status = 'completed' THEN EXTRACT(EPOCH FROM (o.updated_at - p.created_at)) / 3600 END) AS avg_hours_to_sell
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE p.user_id = :sid
    ");
    $orderStmt->execute([':sid' => $sellerId]);
    $orderMetrics = $orderStmt->fetch() ?: [];

    $totalOrders = (int)($orderMetrics['total_orders'] ?? 0);
    $completedOrders = (int)($orderMetrics['completed_orders'] ?? 0);
    $avgHoursToSell = isset($orderMetrics['avg_hours_to_sell']) ? (float)$orderMetrics['avg_hours_to_sell'] : null;

    $ratingQuality = max(0.0, min(1.0, $avgRating / 5.0));
    $reviewConfidence = min(1.0, $reviewCount / 10.0);
    $ratingScore = 50.0 * ((0.7 * $ratingQuality) + (0.3 * $reviewConfidence));

    $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) : 0.0;
    $reliabilityScore = 20.0 * $completionRate;

    // 30 points if sold in <=24h, 0 points if >=14 days. Linear in-between.
    $speedScore = 0.0;
    if ($avgHoursToSell !== null) {
        $speedNorm = 1.0 - (($avgHoursToSell - 24.0) / (336.0 - 24.0));
        $speedNorm = max(0.0, min(1.0, $speedNorm));
        $speedScore = 30.0 * $speedNorm;
    }

    $score = (int)round($ratingScore + $reliabilityScore + $speedScore);
    $score = max(0, min(100, $score));

    $tier = 'New Seller';
    if ($completedOrders >= 3 || $reviewCount >= 3) {
        if ($score >= 88) $tier = 'Highly Trusted';
        elseif ($score >= 75) $tier = 'Trusted';
        else $tier = 'Growing Reputation';
    }

    return [
        'score' => $score,
        'tier' => $tier,
        'review_count' => $reviewCount,
        'avg_rating' => $avgRating,
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
    ];
}

/**
 * Build a public URL for a user avatar.
 */
function avatarUrl(?string $avatarPath): string {
    if (empty($avatarPath)) {
        return 'https://www.gravatar.com/avatar/?d=mp&s=200';
    }
    // If it's already a full URL (e.g. Supabase Storage), return it as is
    if (filter_var($avatarPath, FILTER_VALIDATE_URL)) {
        return $avatarPath;
    }
    // Otherwise, return local path
    return BASE_URL . 'public/' . ltrim($avatarPath, '/');
}

/**
 * Render star glyphs for a rating (0–5, half-star supported).
 */
function renderStars(?float $avg): string {
    $avg = (float)($avg ?? 0);
    $full = (int) floor($avg);
    $half = ($avg - $full) >= 0.5;
    $html = '';
    for ($i = 0; $i < $full; $i++)              $html .= '★';
    if ($half)                                  $html .= '⯨';
    for ($i = $full + ($half ? 1 : 0); $i < 5; $i++) $html .= '☆';
    return $html;
}

/**
 * "Joined Apr 2026" style date for profile header.
 */
function formatJoinDate(string $timestamp): string {
    $ts = strtotime($timestamp);
    return $ts ? date('M Y', $ts) : 'Unknown';
}

/* ─── CSRF Protection ─────────────────────────────────────── */

/**
 * Return a hidden <input> containing the current CSRF token.
 * Drop this inside every <form method="post">.
 */
function csrfTokenField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Abort with 403 if the submitted csrf_token does not match the session.
 * Call at the top of every POST handler.
 * Checks both POST field and X-CSRF-Token header (for AJAX).
 */
function verifyCsrfToken(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    // Double-submit cookie (stateless — works on Vercel serverless): compare the
    // submitted field/header against the cookie the browser echoes back.
    $cookieToken   = $_COOKIE['csrf_token'] ?? '';
    $sessionToken  = $_SESSION['csrf_token'] ?? '';
    $valid = ($cookieToken  !== '' && hash_equals($cookieToken,  $submitted))
          || ($sessionToken !== '' && hash_equals($sessionToken, $submitted));
    if (!$valid) {
        http_response_code(403);
        die('403 Forbidden — Invalid or missing CSRF token.');
    }
}

/**
 * Same as verifyCsrfToken but returns a JSON error for API endpoints.
 */
function verifyCsrfTokenJson(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $cookieToken  = $_COOKIE['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $valid = ($cookieToken  !== '' && hash_equals($cookieToken,  $submitted))
          || ($sessionToken !== '' && hash_equals($sessionToken, $submitted));
    if (!$valid) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}
