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
 * Check if the logged-in user is a listing agent (managed listings account).
 */
function isAgent(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
}

/**
 * Whether a user account is a listing agent (business-managed listings).
 */
function userIsListingAgent(PDO $pdo, int $userId): bool {
    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $cache[$userId] = ((string) $stmt->fetchColumn() === 'agent');
    return $cache[$userId];
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
        redirect(BASE_URL);
    }
}

// ─── Formatting ──────────────────────────────────────────

/**
 * Normalize a product's currency code (TRY or USD).
 */
function productCurrencyCode(array $product): string {
    $code = strtoupper(trim((string)($product['price_currency'] ?? DEFAULT_PRODUCT_CURRENCY)));
    return array_key_exists($code, PRODUCT_CURRENCIES) ? $code : DEFAULT_PRODUCT_CURRENCY;
}

function currencySymbol(?string $currencyCode = null): string {
    $code = strtoupper(trim((string)($currencyCode ?? DEFAULT_PRODUCT_CURRENCY)));
    if (!array_key_exists($code, PRODUCT_CURRENCIES)) {
        return APP_CURRENCY;
    }
    return PRODUCT_CURRENCIES[$code]['symbol'];
}

/**
 * Format a monetary amount with the given currency symbol.
 */
function formatPrice($amount, ?string $currencyCode = null): string {
    $value = (float)$amount;
    $formatted = abs($value - round($value)) < 0.001
        ? number_format($value, 0)
        : number_format($value, 2);
    $code = strtoupper(trim((string)($currencyCode ?? DEFAULT_PRODUCT_CURRENCY)));
    if (!array_key_exists($code, PRODUCT_CURRENCIES)) {
        $code = DEFAULT_PRODUCT_CURRENCY;
    }
    $symbol = PRODUCT_CURRENCIES[$code]['symbol'];
    $position = PRODUCT_CURRENCIES[$code]['position'] ?? 'after';
    if ($position === 'before') {
        return $symbol . $formatted;
    }
    return $formatted . ' ' . $symbol;
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
    $currency = productCurrencyCode($product);
    if ($discountPercent <= 0 || $final >= $base) {
        return '<span>' . formatPrice($base, $currency) . '</span>';
    }
    return
        '<span style="font-weight:800;color:var(--primary);">' . formatPrice($final, $currency) . '</span> ' .
        '<span style="text-decoration:line-through;opacity:.65;font-weight:600;font-size:.9em;margin-left:0.35rem;">' . formatPrice($base, $currency) . '</span> ' .
        '<span class="badge" style="font-size:.68rem;padding:.15rem .45rem;margin-left:0.35rem;background:#ef4444;color:white;font-weight:700;border-radius:4px;display:inline-block;vertical-align:middle;text-transform:uppercase;letter-spacing:0.02em;">Discounted</span> ' .
        '<span class="badge badge-new" style="font-size:.68rem;padding:.15rem .45rem;margin-left:0.2rem;display:inline-block;vertical-align:middle;">-' . $discountPercent . '%</span>';
}

/**
 * Stacked price layout for product cards (discount details above the sale price).
 */
function renderProductCardPrice(array $product): string {
    $discountPercent = (int)($product['discount_percent'] ?? 0);
    $base = (float)($product['price'] ?? 0);
    $final = getDiscountedPrice($product);
    $currency = productCurrencyCode($product);

    if ($discountPercent <= 0 || $final >= $base) {
        return '<span class="product-card-price__now product-card-price__now--regular">' . formatPrice($base, $currency) . '</span>';
    }

    return
        '<span class="product-card-price__was">' .
            '<span class="product-card-price__original">' . formatPrice($base, $currency) . '</span>' .
            '<span class="product-card-price__pct">-' . $discountPercent . '%</span>' .
        '</span>' .
        '<span class="product-card-price__now">' . formatPrice($final, $currency) . '</span>';
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

/**
 * Delete a stored image from Supabase Storage or the local uploads folder.
 */
function deleteStoredImageFile(string $path): bool {
    $path = trim($path);
    if ($path === '') {
        return false;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        require_once __DIR__ . '/../config/supabase.php';
        return deleteSupabaseStorageObject($path);
    }

    $absPath = __DIR__ . '/../public/' . ltrim($path, '/');
    if (is_file($absPath)) {
        return @unlink($absPath);
    }

    return false;
}

/**
 * Permanently deletes a product and all of its associated image files from storage and the database.
 */
function permanentlyDeleteProduct(PDO $pdo, int $productId): bool {
    // 1. Fetch all associated images for the product
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Delete each image file from storage (Supabase or local uploads folder)
    foreach ($images as $img) {
        if (!empty($img)) {
            deleteStoredImageFile($img);
        }
    }
    
    // 3. Delete the product record from the DB (CASCADE deletes tags/images records)
    $del = $pdo->prepare("DELETE FROM products WHERE id = ?");
    return $del->execute([$productId]);
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

    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    $html  = '<div class="pagination">';
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '" class="btn-page">← Prev</a>';
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html  .= '<a href="' . $baseUrl . $separator . 'page=' . $i . '" class="btn-page' . $active . '">' . $i . '</a>';
    }
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '" class="btn-page">Next →</a>';
    }
    $html .= '</div>';
    return $html;
}

// ─── Notifications ───────────────────────────────────────

/**
 * Resolve the other participant in a product conversation for deep links.
 */
function notificationConversationPartnerId(PDO $pdo, int $currentUserId, int $productId): int {
    if ($productId > 0) {
        $stmt = $pdo->prepare("
            SELECT sender_id, receiver_id
            FROM messages
            WHERE (sender_id = :uid OR receiver_id = :uid)
              AND product_id = :pid
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':uid' => $currentUserId, ':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ((int)$row['sender_id'] === $currentUserId)
                ? (int)$row['receiver_id']
                : (int)$row['sender_id'];
        }

        $stmt = $pdo->prepare('SELECT user_id FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $sellerId = (int)$stmt->fetchColumn();
        if ($sellerId > 0 && $sellerId !== $currentUserId) {
            return $sellerId;
        }
        if ($sellerId > 0 && $sellerId === $currentUserId) {
            $stmt = $pdo->prepare("
                SELECT buyer_id FROM orders
                WHERE product_id = ? AND buyer_id != ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$productId, $currentUserId]);
            $buyerId = (int)$stmt->fetchColumn();
            if ($buyerId > 0) {
                return $buyerId;
            }
        }
        return 0;
    }

    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminId = (int)$adminStmt->fetchColumn();
    return ($adminId > 0 && $adminId !== $currentUserId) ? $adminId : 0;
}

/**
 * Build a messages.php URL for a product conversation.
 */
function notificationMessagesUrl(PDO $pdo, int $currentUserId, int $productId): string {
    $base = rtrim(BASE_URL, '/') . '/pages/messages.php';
    $otherUserId = notificationConversationPartnerId($pdo, $currentUserId, $productId);
    if ($otherUserId > 0) {
        return $base . '?product_id=' . $productId . '&other_user_id=' . $otherUserId;
    }
    if ($productId > 0) {
        return rtrim(BASE_URL, '/') . '/pages/product.php?id=' . $productId;
    }
    return rtrim(BASE_URL, '/') . '/pages/inbox.php';
}

/**
 * Human-readable category label for activity feed rows.
 */
function notificationActivityLabel(string $type, string $title): string {
    if ($title === 'Listing pending approval') {
        return 'Listing Approval';
    }
    if ($title === 'Listing under review') {
        return 'Listing Update';
    }
    if ($title === 'Listing Approved') {
        return 'Listing Update';
    }
    if ($title === 'New Seller Review') {
        return 'Review';
    }
    if (str_contains($title, 'Report') || str_contains($title, 'report')) {
        return 'Report Update';
    }
    if ($title === 'Order Cancelled') {
        return 'Order Update';
    }
    if (in_array($title, ['Order expiring soon', 'Order request expired', 'Pending order expired'], true)) {
        return 'Order Update';
    }

    return match ($type) {
        'message' => 'Message',
        'order' => 'Order Update',
        'wishlist' => 'Wishlist',
        default => 'System Update',
    };
}

/**
 * Whether a user account has the admin role.
 */
function notificationUserIsAdmin(PDO $pdo, int $userId): bool {
    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $cache[$userId] = ((string)$stmt->fetchColumn() === 'admin');
    return $cache[$userId];
}

/**
 * Resolve where an activity notification should navigate.
 */
function notificationTargetUrl(PDO $pdo, array $notification, int $currentUserId): string {
    $type = (string)($notification['type'] ?? 'system');
    $title = (string)($notification['title'] ?? '');
    $refId = isset($notification['reference_id']) ? (int)$notification['reference_id'] : 0;
    $base = rtrim(BASE_URL, '/') . '/';

    if ($type === 'message') {
        return notificationMessagesUrl($pdo, $currentUserId, $refId);
    }

    if ($type === 'order') {
        if (in_array($title, ['Deal Confirmed!', 'Deal Confirmation Request'], true) && $refId > 0) {
            return notificationMessagesUrl($pdo, $currentUserId, $refId);
        }
        if ($title === 'Order expiring soon' && $refId > 0) {
            return $base . 'pages/my_orders.php?order_id=' . $refId;
        }
        if ($refId > 0) {
            return $base . 'pages/my_orders.php?order_id=' . $refId;
        }
        return $base . 'pages/my_orders.php';
    }

    if ($type === 'wishlist') {
        return $refId > 0
            ? $base . 'pages/product.php?id=' . $refId
            : $base . 'pages/wishlist.php';
    }

    if ($title === 'Listing pending approval' && notificationUserIsAdmin($pdo, $currentUserId)) {
        return $base . 'admin/listings.php?status=pending_approval';
    }
    if ($title === 'Listing under review' && $refId > 0) {
        return $base . 'pages/product.php?id=' . $refId;
    }
    if ($title === 'Listing Approved' && $refId > 0) {
        return $base . 'pages/product.php?id=' . $refId;
    }
    if ($title === 'New Seller Review' && $refId > 0) {
        return $base . 'pages/product.php?id=' . $refId;
    }
    if ($title === 'Order Cancelled' && $refId > 0) {
        return $base . 'pages/my_orders.php?order_id=' . $refId;
    }
    if ($refId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM reports WHERE id = ? AND reporter_id = ?');
            $stmt->execute([$refId, $currentUserId]);
            if ($stmt->fetchColumn()) {
                return $base . 'pages/my_reports.php';
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return $base . 'pages/profile.php';
}

/**
 * Path-only URL for web push payloads.
 */
function notificationTargetPath(PDO $pdo, array $notification, int $userId): string {
    $full = notificationTargetUrl($pdo, $notification, $userId);
    $base = rtrim(BASE_URL, '/');
    if (str_starts_with($full, $base)) {
        $path = substr($full, strlen($base));
        return $path !== '' ? $path : '/pages/notifications.php';
    }
    return '/pages/notifications.php';
}

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
        $targetUrl = notificationTargetPath($pdo, [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'reference_id' => $referenceId,
        ], $userId);
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
 * Fetch the latest active products for the homepage (within a recent time window).
 */
function getRecentProducts(PDO $pdo, int $limit = 8, ?int $withinDays = null): array {
    $withinDays = $withinDays ?? (defined('HOME_RECENT_LISTING_DAYS') ? (int) HOME_RECENT_LISTING_DAYS : 30);
    $withinDays = max(1, $withinDays);
    $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $dateFilter = $isPgsql
        ? " AND p.created_at >= NOW() - (CAST(:days AS text) || ' days')::interval"
        : ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)';

    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.status = 'active'{$dateFilter}
        ORDER BY p.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':days', $withinDays, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Top categories with a preview of their newest active listings for the homepage.
 */
function getHomepageCategorySections(PDO $pdo, int $categoryLimit = 4, int $productsPerCategory = 5): array {
    $categoryLimit = max(1, $categoryLimit);
    $productsPerCategory = max(1, $productsPerCategory);
    $sections = [];

    foreach (getTopCategories($pdo) as $category) {
        if ((int) ($category['product_count'] ?? 0) <= 0) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
            WHERE p.category_id = :category_id AND p.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':category_id', (int) $category['id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $productsPerCategory, PDO::PARAM_INT);
        $stmt->execute();

        $sections[] = [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'products' => $stmt->fetchAll(),
        ];

        if (count($sections) >= $categoryLimit) {
            break;
        }
    }

    return $sections;
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
 * Complete a product sale: mark sold, record deal confirmation, complete pending order if any.
 *
 * @param string $source One of chat, order, or manual (off-platform).
 * @return array{success:bool,error?:string,already_sold?:bool}
 */
function completeProductSale(PDO $pdo, int $productId, int $sellerId, ?int $buyerId = null, string $source = 'manual'): array {
    $allowedSources = ['chat', 'order', 'manual'];
    if (!in_array($source, $allowedSources, true)) {
        $source = 'manual';
    }

    $ownsTx = !$pdo->inTransaction();
    if ($ownsTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, user_id, title, status FROM products WHERE id = :pid FOR UPDATE");
        $stmt->execute([':pid' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('Product not found');
        }
        if ((int)$product['user_id'] !== $sellerId) {
            throw new RuntimeException('Not authorized');
        }

        if ($product['status'] === 'sold') {
            if ($ownsTx) {
                $pdo->commit();
            }
            return ['success' => true, 'already_sold' => true];
        }

        if (!in_array($product['status'], ['active', 'pending_approval'], true)) {
            throw new RuntimeException('Listing cannot be marked as sold');
        }

        $pdo->prepare("
            UPDATE products
            SET status = 'sold', deleted_at = NULL, updated_at = NOW()
            WHERE id = :pid
        ")->execute([':pid' => $productId]);

        if ($buyerId !== null) {
            $stmtOrder = $pdo->prepare("
                SELECT id, amount
                FROM orders
                WHERE product_id = :pid AND buyer_id = :bid AND status = 'pending'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmtOrder->execute([':pid' => $productId, ':bid' => $buyerId]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = :id")
                    ->execute([':id' => $order['id']]);

                $stmtTrans = $pdo->prepare("SELECT id FROM transactions WHERE order_id = :oid LIMIT 1");
                $stmtTrans->execute([':oid' => $order['id']]);
                if (!$stmtTrans->fetchColumn()) {
                    $pdo->prepare("INSERT INTO transactions (order_id, amount, status) VALUES (:oid, :amount, 'success')")
                        ->execute([':oid' => $order['id'], ':amount' => $order['amount']]);
                }
            }
        }

        $pdo->prepare("
            UPDATE orders
            SET status = 'cancelled', updated_at = NOW()
            WHERE product_id = :pid AND status = 'pending'
        ")->execute([':pid' => $productId]);

        if ($buyerId !== null) {
            $stmtDeal = $pdo->prepare("
                SELECT id FROM deal_confirmations
                WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid
                LIMIT 1
            ");
            $stmtDeal->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            $dealId = $stmtDeal->fetchColumn();

            if ($dealId) {
                $pdo->prepare("
                    UPDATE deal_confirmations
                    SET seller_confirmed_at = NOW(), status = 'completed', sale_source = :src, updated_at = NOW()
                    WHERE id = :id
                ")->execute([':id' => $dealId, ':src' => $source]);
            } else {
                $pdo->prepare("
                    INSERT INTO deal_confirmations (product_id, buyer_id, seller_id, seller_confirmed_at, status, sale_source)
                    VALUES (:pid, :bid, :sid, NOW(), 'completed', :src)
                ")->execute([
                    ':pid' => $productId,
                    ':bid' => $buyerId,
                    ':sid' => $sellerId,
                    ':src' => $source,
                ]);
            }
        } else {
            $stmtManual = $pdo->prepare("
                SELECT id FROM deal_confirmations
                WHERE product_id = :pid AND seller_id = :sid AND status = 'completed' AND sale_source = 'manual'
                LIMIT 1
            ");
            $stmtManual->execute([':pid' => $productId, ':sid' => $sellerId]);
            if (!$stmtManual->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO deal_confirmations (product_id, buyer_id, seller_id, seller_confirmed_at, status, sale_source)
                    VALUES (:pid, NULL, :sid, NOW(), 'completed', 'manual')
                ")->execute([':pid' => $productId, ':sid' => $sellerId]);
            }
        }

        if ($ownsTx) {
            $pdo->commit();
        }

        return ['success' => true];
    } catch (Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[completeProductSale] ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Compute seller trust score (0-100) based on reviews, completion reliability, and sell speed.
 */
function getSellerTrustScore(PDO $pdo, int $sellerId): array {
    $rating = getSellerRating($pdo, $sellerId);
    $avgRating = (float)($rating['avg'] ?? 0);
    $reviewCount = (int)($rating['count'] ?? 0);

    if (userIsListingAgent($pdo, $sellerId)) {
        return [
            'score' => 92,
            'tier' => 'Highly Trusted',
            'review_count' => $reviewCount,
            'avg_rating' => $avgRating,
            'total_orders' => 0,
            'completed_orders' => 0,
            'sold_products' => 0,
        ];
    }

    $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $timeDiffSql = $isPostgres 
        ? "EXTRACT(EPOCH FROM (o.updated_at - p.created_at)) / 3600" 
        : "TIMESTAMPDIFF(HOUR, p.created_at, o.updated_at)";

    $orderStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN o.status = 'cancelled' AND p.status <> 'sold' THEN 1 ELSE 0 END) AS penalized_cancellations,
            AVG(CASE WHEN o.status = 'completed' THEN {$timeDiffSql} END) AS avg_hours_to_sell
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE p.user_id = :sid
    ");
    $orderStmt->execute([':sid' => $sellerId]);
    $orderMetrics = $orderStmt->fetch() ?: [];

    $totalOrders = (int)($orderMetrics['total_orders'] ?? 0);
    $completedOrders = (int)($orderMetrics['completed_orders'] ?? 0);
    $penalizedCancellations = (int)($orderMetrics['penalized_cancellations'] ?? 0);
    $avgHoursToSell = isset($orderMetrics['avg_hours_to_sell']) ? (float)$orderMetrics['avg_hours_to_sell'] : null;

    $soldTimeDiffSql = $isPostgres
        ? "EXTRACT(EPOCH FROM (p.updated_at - p.created_at)) / 3600"
        : "TIMESTAMPDIFF(HOUR, p.created_at, p.updated_at)";

    $soldStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS sold_count,
            AVG({$soldTimeDiffSql}) AS avg_hours_to_sell
        FROM products p
        WHERE p.user_id = :sid AND p.status = 'sold'
    ");
    $soldStmt->execute([':sid' => $sellerId]);
    $soldMetrics = $soldStmt->fetch() ?: [];
    $soldProducts = (int)($soldMetrics['sold_count'] ?? 0);
    $soldAvgHours = isset($soldMetrics['avg_hours_to_sell']) ? (float)$soldMetrics['avg_hours_to_sell'] : null;

    $dealStmt = $pdo->prepare("
        SELECT COUNT(*) FROM deal_confirmations
        WHERE seller_id = :sid AND status = 'completed'
    ");
    $dealStmt->execute([':sid' => $sellerId]);
    $completedDeals = (int)$dealStmt->fetchColumn();

    $completedSales = max($completedOrders, $soldProducts, $completedDeals);
    if ($avgHoursToSell === null) {
        $avgHoursToSell = $soldAvgHours;
    } elseif ($soldAvgHours !== null) {
        $avgHoursToSell = ($avgHoursToSell + $soldAvgHours) / 2;
    }

    $ratingQuality = max(0.0, min(1.0, $avgRating / 5.0));
    $reviewConfidence = min(1.0, $reviewCount / 10.0);
    $ratingScore = 50.0 * ((0.7 * $ratingQuality) + (0.3 * $reviewConfidence));

    $decidedOrders = $completedOrders + $penalizedCancellations;
    $orderCompletionRate = $decidedOrders > 0 ? ($completedOrders / $decidedOrders) : 0.0;
    $reliabilityFromOrders = 20.0 * $orderCompletionRate;
    // Off-platform / manual sales should lift reliability even without completed orders.
    $reliabilityFromSales = min(20.0, $completedSales * 4.0);
    $reliabilityScore = max($reliabilityFromOrders, $reliabilityFromSales);

    // 30 points if sold in <=24h, 0 points if >=14 days. Linear in-between.
    $speedScore = 0.0;
    if ($avgHoursToSell !== null) {
        $speedNorm = 1.0 - (($avgHoursToSell - 24.0) / (336.0 - 24.0));
        $speedNorm = max(0.0, min(1.0, $speedNorm));
        $speedScore = 30.0 * $speedNorm;
    }
    // Keep real sales meaningful even when the listing took a while to move.
    if ($completedSales > 0) {
        $speedScore = max($speedScore, 10.0);
    }

    $score = (int)round($ratingScore + $reliabilityScore + $speedScore);
    $score = max(0, min(100, $score));

    $tier = 'New Seller';
    if ($completedSales >= 3 || $reviewCount >= 3) {
        if ($score >= 88) $tier = 'Highly Trusted';
        elseif ($score >= 75) $tier = 'Trusted';
        else $tier = 'Growing Reputation';
    } elseif ($completedSales >= 1 || $reviewCount >= 1) {
        if ($score >= 75) $tier = 'Trusted';
        elseif ($score >= 40) $tier = 'Growing Reputation';
        else $tier = 'Active Seller';
    }

    return [
        'score' => $score,
        'tier' => $tier,
        'review_count' => $reviewCount,
        'avg_rating' => $avgRating,
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'sold_products' => $soldProducts,
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
        'book'        => ['notebook', 'study', 'literature', 'guide', 'novel', 'read'],
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

/**
 * Build SQL filter for product search (FTS + LIKE fallback for tags/categories).
 */
function productSearchFilterSql(string $search, array &$params, string $productAlias = 'p', string $categoryAlias = 'c'): string {
    if (trim($search) === '') {
        return '';
    }

    $searchTerms = expandSearchQuery($search);
    if (empty($searchTerms)) {
        return '';
    }

    $termConditions = [];
    foreach ($searchTerms as $term) {
        $ftsTerm = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $term));
        if ($ftsTerm === '') {
            $ftsTerm = $term;
        }

        $termConditions[] = "(
            ({$productAlias}.search_vector IS NOT NULL AND {$productAlias}.search_vector @@ plainto_tsquery('simple', ?))
            OR LOWER({$productAlias}.title) LIKE ?
            OR LOWER({$productAlias}.description) LIKE ?
            OR LOWER({$categoryAlias}.name) LIKE ?
            OR EXISTS (
                SELECT 1 FROM product_tags pt
                JOIN tags t ON pt.tag_id = t.id
                WHERE pt.product_id = {$productAlias}.id AND LOWER(t.name) LIKE ?
            )
        )";
        $params[] = $ftsTerm;
        $params[] = "%$term%";
        $params[] = "%$term%";
        $params[] = "%$term%";
        $params[] = "%$term%";
    }

    return ' AND (' . implode(' OR ', $termConditions) . ')';
}

/**
 * Cached category list for navigation (short TTL file cache).
 */
function getNavCategories(PDO $pdo): array {
    $cacheFile = sys_get_temp_dir() . '/cm_nav_categories_v1.json';
    $ttl = 300;

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $rows = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    @file_put_contents($cacheFile, json_encode($rows));

    return $rows;
}

function invalidateNavCategoriesCache(): void {
    $cacheFile = sys_get_temp_dir() . '/cm_nav_categories_v1.json';
    if (is_file($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Allowed North Cyprus town slugs for listing geotags.
 */
function locationTownSlugs(): array {
    return ['lefkosa', 'girne', 'gazimagusa', 'guzelyurt', 'lefke', 'iskele', 'other'];
}

function isValidLocationTown(?string $town): bool {
    if ($town === null || $town === '') {
        return false;
    }
    return in_array(strtolower($town), locationTownSlugs(), true);
}

function formatLocationTown(?string $town): string {
    if (!isValidLocationTown($town)) {
        return '';
    }
    return __('location.town.' . strtolower($town));
}

/**
 * SQL fragment for filtering products by town.
 */
function locationTownFilterSql(string $productAlias, ?string $town, array &$params): string {
    if (!isValidLocationTown($town)) {
        return '';
    }
    $params[] = strtolower($town);
    return " AND {$productAlias}.location_town = ?";
}

function getUserHomeTown(?int $userId = null): ?string {
    global $pdo;
    if (!$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT home_town FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $town = $stmt->fetchColumn();
        return isValidLocationTown($town) ? strtolower((string)$town) : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * SVG icon for a marketplace category (matched by normalized slug / keywords).
 */
function categoryIconMarkup(string $name): string {
    $slug = categorySlug($name);

    $type = match (true) {
        str_contains($slug, 'kitchen') => 'kitchen',
        str_contains($slug, 'electronic') => 'electronics',
        str_contains($slug, 'clothing') || str_contains($slug, 'fashion') => 'clothing',
        str_contains($slug, 'dorm') || str_contains($slug, 'living') => 'dorm',
        str_contains($slug, 'transport') => 'transportation',
        str_contains($slug, 'book') || str_contains($slug, 'study_material') => 'books',
        str_contains($slug, 'food') || str_contains($slug, 'beverage') => 'food',
        str_contains($slug, 'health') || str_contains($slug, 'personal_care') => 'health',
        str_contains($slug, 'stationery') || str_contains($slug, 'study_suppl') => 'stationery',
        str_contains($slug, 'furniture') => 'furniture',
        str_contains($slug, 'art') || str_contains($slug, 'craft') || str_contains($slug, 'diy') => 'arts',
        str_contains($slug, 'service') => 'services',
        str_contains($slug, 'decor') => 'decor',
        str_contains($slug, 'ticket') => 'tickets',
        default => 'default',
    };

    static $icons = [
        'kitchen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
        'electronics' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'clothing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2a8 8 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>',
        'dorm' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'transportation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"/></svg>',
        'books' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'food' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>',
        'health' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'stationery' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'furniture' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>',
        'arts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>',
        'services' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 13.255A23.931 23.931 0 0 1 12 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2m4 6h.01M5 20h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 0 2z"/></svg>',
        'decor' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16 2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
        'tickets' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 0 0-2 2v3a2 2 0 1 0 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 1 0 0-4V7a2 2 0 0 0-2-2H5z"/></svg>',
        'default' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    ];

    return $icons[$type] ?? $icons['default'];
}

