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

function renderProductPrice(array $product): string {
    $discountPercent = (int)($product['discount_percent'] ?? 0);
    $base = (float)($product['price'] ?? 0);
    $final = getDiscountedPrice($product);
    if ($discountPercent <= 0 || $final >= $base) {
        return '<span>' . formatPrice($base) . '</span>';
    }
    return
        '<span style="font-weight:800;color:var(--primary);">' . formatPrice($final) . '</span> ' .
        '<span style="text-decoration:line-through;opacity:.65;font-weight:600;font-size:.9em;margin-left:0.35rem;">' . formatPrice($base) . '</span> ' .
        '<span class="badge" style="font-size:.68rem;padding:.15rem .45rem;margin-left:0.35rem;background:#ef4444;color:white;font-weight:700;border-radius:4px;display:inline-block;vertical-align:middle;text-transform:uppercase;letter-spacing:0.02em;">Discounted</span> ' .
        '<span class="badge badge-new" style="font-size:.68rem;padding:.15rem .45rem;margin-left:0.2rem;display:inline-block;vertical-align:middle;">-' . $discountPercent . '%</span>';
}

/**
 * Human-readable time ago (e.g., "3 hours ago")
 */
function timeAgo(?string $datetime): string {
    if (!$datetime) return __('time.recently');
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y === 1 ? __('time.year_ago') : __('time.years_ago', ['count' => $diff->y]);
    }
    if ($diff->m > 0) {
        return $diff->m === 1 ? __('time.month_ago') : __('time.months_ago', ['count' => $diff->m]);
    }
    if ($diff->d > 0) {
        return $diff->d === 1 ? __('time.day_ago') : __('time.days_ago', ['count' => $diff->d]);
    }
    if ($diff->h > 0) {
        return $diff->h === 1 ? __('time.hour_ago') : __('time.hours_ago', ['count' => $diff->h]);
    }
    if ($diff->i > 0) {
        return $diff->i === 1 ? __('time.minute_ago') : __('time.minutes_ago', ['count' => $diff->i]);
    }
    return __('time.just_now');
}

/**
 * Get a condition badge label and CSS class
 */
function conditionBadge(?string $condition): array {
    return match($condition) {
        'new'      => ['label' => __('condition.new'),      'class' => 'badge-new'],
        'like_new' => ['label' => __('condition.like_new'), 'class' => 'badge-like-new'],
        'used'     => ['label' => __('condition.used'),     'class' => 'badge-used'],
        'poor'     => ['label' => __('condition.poor'),     'class' => 'badge-poor'],
        default    => ['label' => __('condition.unknown'),  'class' => 'badge-used'],
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

    // Secure Extension Whitelist Check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed_exts)) {
        return ['success' => false, 'error' => 'Invalid file extension'];
    }

    // Verify file content is a valid image using getimagesize
    $img_info = @getimagesize($file['tmp_name']);
    if ($img_info === false) {
        return ['success' => false, 'error' => 'Uploaded file is not a valid image'];
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $subfolder = trim($subfolder, '/');
    $objectName = $subfolder . '/' . $filename;
    
    // Check if Supabase env vars are set
    require_once __DIR__ . '/../config/supabase.php';
    $supabaseUrl = supabaseUrl();
    $supabaseKey = supabaseAnonKey();
    $supabaseServiceKey = function_exists('supabaseServiceRoleKey') ? supabaseServiceRoleKey() : '';
    error_log("handleUpload debug: URL='" . $supabaseUrl . "', KeyLen=" . strlen($supabaseKey) . ", ServiceKeyLen=" . strlen($supabaseServiceKey));

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
    $uploadKey = $supabaseServiceKey !== '' ? $supabaseServiceKey : $supabaseKey;
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $uploadKey,
        "apikey: " . $uploadKey,
        "Content-Type: " . $file['type']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Return absolute URL so it works seamlessly on frontend
        $publicUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectName;
        return ['success' => true, 'path' => $publicUrl];
    } else {
        error_log("Supabase storage upload failed. URL='" . $url . "', Code=" . $httpCode . ", Response='" . $response . "'");
        // Resilient fallback: if cloud upload fails, still allow local upload path.
        $relPath = 'uploads/' . $objectName;
        $absPath = __DIR__ . '/../public/' . $relPath;
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (@move_uploaded_file($file['tmp_name'], $absPath)) {
            return ['success' => true, 'path' => $relPath];
        }
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

    // Web push (best-effort): send background push if configured.
    try {
        require_once __DIR__ . '/web_push.php';
        $targetUrl = '/pages/notifications.php';
        if ($type === 'message') {
            $targetUrl = '/pages/inbox.php';
        }
        triggerWebPushBestEffort($userId, $title, $body, $targetUrl);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Email alert for new direct messages when users are away from the site.
 * Rate-limited: one email per receiver/sender/product every 5 minutes.
 */
function sendNewMessageEmailAlert(PDO $pdo, int $receiverId, int $senderId, ?int $productId, string $messageBody): void {
    static $hasAlertsTable = null;
    if ($hasAlertsTable === null) {
        try {
            $hasAlertsTable = (bool)$pdo->query("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = 'public' AND table_name = 'message_email_alerts'
                LIMIT 1
            ")->fetchColumn();
        } catch (Throwable $e) {
            $hasAlertsTable = false;
        }
    }
    if (!$hasAlertsTable) {
        return;
    }

    try {
        $userStmt = $pdo->prepare("SELECT username, email, is_verified FROM users WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $receiverId]);
        $receiver = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$receiver || empty($receiver['email']) || !(bool)$receiver['is_verified']) {
            return;
        }

        $senderStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $senderStmt->execute([':id' => $senderId]);
        $senderName = (string)($senderStmt->fetchColumn() ?: 'Someone');

        $throttleStmt = $pdo->prepare("
            SELECT sent_at
            FROM message_email_alerts
            WHERE receiver_id = :rid
              AND sender_id = :sid
              AND COALESCE(product_id, 0) = COALESCE(:pid, 0)
            ORDER BY sent_at DESC
            LIMIT 1
        ");
        $throttleStmt->execute([
            ':rid' => $receiverId,
            ':sid' => $senderId,
            ':pid' => $productId,
        ]);
        $lastSent = $throttleStmt->fetchColumn();
        if ($lastSent) {
            $elapsed = time() - strtotime((string)$lastSent);
            if ($elapsed < 300) {
                return;
            }
        }

        $inboxUrl = rtrim(BASE_URL, '/') . '/pages/inbox.php';
        $subject = "{$senderName} sent you a message on CampusMarket";
        $headline = 'New message received';
        $snippet = trim($messageBody);
        if (strlen($snippet) > 180) {
            $snippet = substr($snippet, 0, 177) . '...';
        }
        $body = "you have a new message from @{$senderName}. \"{$snippet}\"";

        $sendResult = sendMarketplaceAlertEmail(
            (string)$receiver['email'],
            (string)$receiver['username'],
            $subject,
            $headline,
            $body,
            $inboxUrl,
            'View Message'
        );

        if (!empty($sendResult['ok'])) {
            $ins = $pdo->prepare("
                INSERT INTO message_email_alerts (receiver_id, sender_id, product_id, sent_at)
                VALUES (:rid, :sid, :pid, NOW())
            ");
            $ins->execute([
                ':rid' => $receiverId,
                ':sid' => $senderId,
                ':pid' => $productId,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[email-alert] sendNewMessageEmailAlert failed: ' . $e->getMessage());
    }
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
 * Core marketplace tags used by AI suggestions and the create-listing form.
 */
function getDefaultMarketplaceTags(): array {
    return [
        ['Electronics', 'electronics'],
        ['Books', 'books'],
        ['Study Guides', 'study-guides'],
        ['Dorm Decor', 'dorm-decor'],
        ['Furniture', 'furniture'],
        ['Kitchenwear', 'kitchenwear'],
        ['Bikes', 'bikes'],
        ['Scooters', 'scooters'],
        ['Clothing', 'clothing'],
        ['Stationery', 'stationery'],
        ['Snacks', 'snacks'],
        ['Sports & Fitness', 'sports-fitness'],
    ];
}

/**
 * Re-insert any missing default tags (safe to run multiple times).
 */
function seedDefaultTags(PDO $pdo): int {
    $added = 0;
    $check = $pdo->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');

    foreach (getDefaultMarketplaceTags() as [$name, $slug]) {
        $check->execute([$slug]);
        if (!$check->fetchColumn()) {
            $insert->execute([$name, $slug]);
            $added++;
        }
    }

    return $added;
}

/**
 * Remove all donation payment records (test checkout data, Hall of Fame, etc.).
 * Promotion payments are not affected.
 */
function clearDonationData(PDO $pdo): int {
    $stmt = $pdo->prepare("DELETE FROM promotion_payments WHERE payment_type = 'donation'");
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Count donation payment records currently stored.
 */
function countDonationRecords(PDO $pdo): int {
    try {
        $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        if ($isPostgres) {
            $tableStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = 'promotion_payments'
                LIMIT 1
            ");
        } else {
            $tableStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'promotion_payments'
                LIMIT 1
            ");
        }
        $tableStmt->execute();
        if (!(bool)$tableStmt->fetchColumn()) {
            return 0;
        }
        return (int)$pdo->query("SELECT COUNT(*) FROM promotion_payments WHERE payment_type = 'donation'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Fetch top donors for the Hall of Fame
 */
function getDonors(PDO $pdo, int $limit = 5): array {
    static $hasPromotionPayments = null;
    if ($hasPromotionPayments === null) {
        $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        if ($isPostgres) {
            $tableStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = 'promotion_payments'
                LIMIT 1
            ");
            $tableStmt->execute();
        } else {
            $tableStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'promotion_payments'
                LIMIT 1
            ");
            $tableStmt->execute();
        }
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

    $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $timeDiffSql = $isPostgres 
        ? "EXTRACT(EPOCH FROM (o.updated_at - p.created_at)) / 3600" 
        : "TIMESTAMPDIFF(HOUR, p.created_at, o.updated_at)";

    $orderStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            AVG(CASE WHEN o.status = 'completed' THEN {$timeDiffSql} END) AS avg_hours_to_sell
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
function csrfToken(): string {
    return $_SESSION['csrf_token'] ?? $_COOKIE['csrf_token'] ?? '';
}

function csrfTokenField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
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

// ─── Language / i18n Helpers ─────────────────────────────────

/**
 * Get a user's preferred language from the database.
 */
function getUserPreferredLanguage(PDO $pdo, int $userId): string {
    try {
        $stmt = $pdo->prepare("SELECT preferred_language FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $lang = $stmt->fetchColumn();
        if ($lang && array_key_exists($lang, SUPPORTED_LANGUAGES)) {
            return $lang;
        }
    } catch (PDOException $e) {
        // Column may not exist yet — graceful fallback
    }
    return DEFAULT_LANGUAGE;
}

/**
 * Set a user's preferred language in the database and session.
 */
function setUserPreferredLanguage(PDO $pdo, int $userId, string $lang): bool {
    if (!array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET preferred_language = :lang WHERE id = :id");
        $stmt->execute([':lang' => $lang, ':id' => $userId]);
        $_SESSION['preferred_language'] = $lang;
        return true;
    } catch (PDOException $e) {
        error_log("setUserPreferredLanguage error: " . $e->getMessage());
        return false;
    }
}

// ─── Search Helpers ──────────────────────────────────────────

/**
 * Expand a search query with common synonyms
 */
function expandSearchQuery(string $query): array {
    $lowerQuery = mb_strtolower(trim($query));
    if ($lowerQuery === '') return [];
    
    $synonyms = [
        // Electronics
        'mobile'      => ['phone', 'iphone', 'smartphone', 'cellphone'],
        'phone'       => ['mobile', 'iphone', 'smartphone', 'cellphone'],
        'pc'          => ['laptop', 'computer', 'macbook', 'desktop'],
        'laptop'      => ['pc', 'computer', 'macbook', 'desktop', 'mac'],
        'computer'    => ['pc', 'laptop', 'macbook', 'desktop', 'mac'],
        'tech'        => ['electronics', 'device', 'gadget', 'apple', 'huawei'],
        'device'      => ['electronics', 'tech', 'gadget'],
        'audio'       => ['speaker', 'headphones', 'earbuds', 'airpods'],
        'tablet'      => ['ipad', 'pad'],

        // Books & Study
        'book'        => ['textbook', 'notebook', 'study', 'literature', 'guide', 'novel', 'read'],
        'textbook'    => ['book', 'study', 'class', 'course'],
        'math'        => ['calculus', 'algebra', 'geometry'],
        'science'     => ['biology', 'chemistry', 'physics'],

        // Furniture
        'furniture'   => ['desk', 'chair', 'sofa', 'lamp', 'shelf', 'table', 'bed', 'mirror', 'nightstand'],
        'seat'        => ['chair', 'sofa', 'couch'],
        'storage'     => ['shelf', 'bookshelf', 'cart', 'drawer'],

        // Clothing
        'clothes'     => ['clothing', 'dress', 'shirt', 'jeans', 'jacket', 'trousers', 'wear', 'apparel', 'outfit'],
        'clothing'    => ['clothes', 'dress', 'shirt', 'jeans', 'jacket', 'trousers', 'wear', 'apparel', 'outfit'],
        'shirt'       => ['t-shirt', 'tee', 'blouse', 'top'],
        'pants'       => ['jeans', 'trousers'],

        // Kitchen
        'kitchen'     => ['cook', 'food', 'appliance', 'cutlery', 'microwave', 'fridge', 'blender'],
        'cutlery'     => ['fork', 'spoon', 'knife'],
        'appliance'   => ['microwave', 'fridge', 'cooker', 'blender', 'air fryer'],
        'cookware'    => ['pot', 'pan', 'board', 'cutter'],

        // Health & Care
        'health'      => ['care', 'hygiene', 'wash', 'sanitizer', 'first aid', 'skincare'],
        'hygiene'     => ['wash', 'soap', 'deodorant', 'shampoo', 'toothpaste'],
        'beauty'      => ['skincare', 'wash', 'lotion'],

        // Food & Beverages
        'food'        => ['snack', 'drink', 'beverage', 'candy', 'juice', 'soda', 'chips', 'chocolate'],
        'drink'       => ['beverage', 'juice', 'soda', 'coca-cola', 'fanta', 'lemonade', 'water'],
        'beverage'    => ['drink', 'juice', 'soda'],
        'snack'       => ['chips', 'candy', 'popcorn', 'chocolate', 'doritos', 'skittles'],

        // Stationery
        'stationery'  => ['paper', 'pen', 'pencil', 'notebook', 'ruler', 'calculator', 'eraser'],
        'writing'     => ['pen', 'pencil', 'marker'],
        'school'      => ['stationery', 'book', 'notebook', 'calculator', 'bag'],

        // Dorm Essentials
        'dorm'        => ['room', 'decor', 'essential', 'hanger', 'lamp', 'laundry', 'mirror'],
        'room'        => ['dorm', 'decor', 'lamp', 'mirror', 'storage'],

        // Transportation
        'transport'   => ['bike', 'bicycle', 'scooter', 'cycling', 'ride'],
        'bike'        => ['bicycle', 'scooter', 'transport', 'cycling'],
        'bicycle'     => ['bike', 'cycling', 'transport'],
        'scooter'     => ['bike', 'bicycle', 'transport', 'kick scooter']
    ];
    
    $terms = [$lowerQuery];
    foreach ($synonyms as $key => $synList) {
        if (strpos($lowerQuery, $key) !== false || in_array($lowerQuery, $synList)) {
            $terms = array_merge($terms, $synList);
            $terms[] = $key;
        }
    }
    
    return array_unique($terms);
}

