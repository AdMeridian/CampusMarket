<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order_expiry.php';
ob_start(); // Buffer output to prevent warnings from breaking JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function logApiError($msg) {
    file_put_contents(__DIR__ . '/../api_error.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'set_language' && !isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$currentUserId = isLoggedIn() ? currentUserId() : 0;

function isValidConversation(PDO $pdo, int $productId, int $currentUserId, int $otherUserId): bool {
    if ($currentUserId <= 0 || $otherUserId <= 0 || $currentUserId === $otherUserId) {
        return false;
    }

    // Special Case: Support / Direct Message Chat (product_id = 0)
    // Allowed for any valid registered users
    if ($productId === 0) {
        return true;
    }

    // Standard Product Chat
    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = :pid");
    $stmt->execute([':pid' => $productId]);
    $sellerId = (int) $stmt->fetchColumn();

    return $sellerId > 0
        && ($currentUserId === $sellerId || $otherUserId === $sellerId);
}

if ($action === 'search_users') {
    $query = sanitize($_GET['q'] ?? '');
    if (strlen($query) < 1) {
        // Return recent contacts if available
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.username, u.avatar
            FROM messages m
            JOIN users u ON u.id = (CASE WHEN m.sender_id = :uid1 THEN m.receiver_id ELSE m.sender_id END)
            WHERE (m.sender_id = :uid2 OR m.receiver_id = :uid3)
              AND u.id != :uid4
            ORDER BY m.id DESC
            LIMIT 6
        ");
        $stmt->execute([
            ':uid1' => $currentUserId,
            ':uid2' => $currentUserId,
            ':uid3' => $currentUserId,
            ':uid4' => $currentUserId,
        ]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($users as $u) {
            $results[] = [
                'id' => (int)$u['id'],
                'username' => $u['username'],
                'avatar_url' => avatarUrl($u['avatar']),
                'is_recent' => true,
            ];
        }
        ob_clean();
        echo json_encode(['success' => true, 'users' => $results]);
        exit;
    }
    
    // Search users by username, excluding the current logged-in user (case-insensitive)
    $stmt = $pdo->prepare("
        SELECT id, username, avatar 
        FROM users 
        WHERE LOWER(username) LIKE LOWER(:q) AND id != :my_id 
        LIMIT 10
    ");
    $stmt->execute([
        ':q' => '%' . $query . '%',
        ':my_id' => $currentUserId
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($users as $u) {
        $results[] = [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'avatar_url' => avatarUrl($u['avatar']),
            'is_recent' => false,
        ];
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'users' => $results]);
    exit;
}

if ($action === 'share_to_inbox') {
    verifyCsrfTokenJson();
    $productId = (int)($_POST['product_id'] ?? 0);
    $recipientId = (int)($_POST['recipient_id'] ?? 0);
    $note = trim(sanitize($_POST['note'] ?? ''));

    if ($productId <= 0 || $recipientId <= 0 || $recipientId === $currentUserId) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $userStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $userStmt->execute([$recipientId]);
    $recipient = $userStmt->fetch();
    if (!$recipient) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Recipient not found']);
        exit;
    }

    $prodStmt = $pdo->prepare("SELECT id, title, price, price_currency FROM products WHERE id = ? AND status = 'active'");
    $prodStmt->execute([$productId]);
    $prod = $prodStmt->fetch();
    if (!$prod) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Listing not found or no longer active']);
        exit;
    }

    $productUrl = rtrim(BASE_URL, '/') . '/pages/product.php?id=' . $productId;
    $msgBody = $note !== ''
        ? $note . "\n\n" . $productUrl
        : $productUrl;

    if (mb_strlen($msgBody) > 500) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Message too long']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (:sid, :rid, NULL, :body)");
        $stmt->execute([
            ':sid' => $currentUserId,
            ':rid' => $recipientId,
            ':body' => $msgBody,
        ]);
        $messageId = (int)$pdo->lastInsertId();

        $pdo->commit();

        try {
            $shareStmt = $pdo->prepare("INSERT INTO product_shares (product_id, user_id, channel) VALUES (?, ?, 'inbox')");
            $shareStmt->execute([$productId, $currentUserId]);
        } catch (Throwable $e) {}

        $senderUsername = $_SESSION['username'] ?? 'Someone';
        createNotification(
            $pdo,
            $recipientId,
            'message',
            'Listing Shared',
            '@' . $senderUsername . ' shared a listing with you: ' . $prod['title'],
            $productId
        );

        ob_clean();
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'chat_url' => rtrim(BASE_URL, '/') . '/pages/messages.php?other_user_id=' . $recipientId . '&product_id=0',
            'recipient_username' => $recipient['username']
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logApiError("Share to inbox error: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Failed to send listing: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'fetch') {
    try {
        $productId = (int)($_GET['product_id'] ?? 0);
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);
    
    if ($otherUserId <= 0) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    if (!isValidConversation($pdo, $productId, $currentUserId, $otherUserId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Mark messages sent to me as read (all messages from this sender to me, regardless of product)
    $stmtRead = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = :uid AND sender_id = :other AND is_read = FALSE");
    $stmtRead->execute([
        ':uid' => $currentUserId,
        ':other' => $otherUserId
    ]);

    // Keep notification badge in sync with read state in chat.
    $stmtNotifRead = $pdo->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE user_id = :uid
          AND type = 'message'
          AND is_read = FALSE
          AND (
              reference_id IN (
                  SELECT DISTINCT COALESCE(product_id, 0)
                  FROM messages
                  WHERE sender_id = :other
                    AND receiver_id = :uid
              )
              OR (
                  reference_id IS NULL
                  AND EXISTS (
                      SELECT 1
                      FROM messages
                      WHERE sender_id = :other
                        AND receiver_id = :uid
                        AND product_id IS NULL
                  )
              )
          )
    ");
    $stmtNotifRead->execute([
        ':uid' => $currentUserId,
        ':other' => $otherUserId
    ]);
    
    // Fetch messages with translation for current user's preferred language (all messages between these two users)
    $myLang = i18nGetLocale();
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name,
               replied.body AS reply_body,
               replied.sender_id AS reply_sender_id,
               reply_sender.username AS reply_sender_name,
               t.translated_text, t.source_lang
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN messages replied ON replied.id = m.reply_to_message_id
        LEFT JOIN users reply_sender ON reply_sender.id = replied.sender_id
        LEFT JOIN message_translations t ON m.id = t.message_id AND t.target_lang = :mylang
        WHERE (
              (m.sender_id = :uid1 AND m.receiver_id = :other1 AND (m.deleted_by_sender = 0 OR m.deleted_by_sender IS NULL)) OR
              (m.sender_id = :other2 AND m.receiver_id = :uid2 AND (m.deleted_by_receiver = 0 OR m.deleted_by_receiver IS NULL))
          )
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        ':mylang' => $myLang,
        ':uid1' => $currentUserId,
        ':other1' => $otherUserId,
        ':other2' => $otherUserId,
        ':uid2' => $currentUserId
    ]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Batch-resolve any mentioned products in these messages
    $mentionedProductIds = [];
    foreach ($messages as $mRow) {
        if (!empty($mRow['product_id'])) {
            $mentionedProductIds[(int)$mRow['product_id']] = (int)$mRow['product_id'];
        }
        if (preg_match_all('#(?:product\.php\?id=|/product/)(\d+)#i', (string)$mRow['body'], $pMatches)) {
            foreach ($pMatches[1] as $matchId) {
                $mentionedProductIds[(int)$matchId] = (int)$matchId;
            }
        }
    }

    $productMetaMap = [];
    if (!empty($mentionedProductIds)) {
        try {
            $pIdsList = array_values($mentionedProductIds);
            $placeholders = implode(',', array_fill(0, count($pIdsList), '?'));
            $metaStmt = $pdo->prepare("
                SELECT p.id, p.title, p.price, p.price_currency, p.status,
                       pi.image_path
                FROM products p
                LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = TRUE
                WHERE p.id IN ($placeholders)
            ");
            $metaStmt->execute($pIdsList);
            foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $pRow) {
                $productMetaMap[(int)$pRow['id']] = [
                    'id' => (int)$pRow['id'],
                    'title' => htmlspecialchars($pRow['title'], ENT_QUOTES, 'UTF-8'),
                    'price' => (float)$pRow['price'],
                    'price_currency' => $pRow['price_currency'] ?: 'TRY',
                    'image_url' => getProductImage($pRow['image_path']),
                    'status' => $pRow['status'],
                    'url' => rtrim(BASE_URL, '/') . '/pages/product.php?id=' . (int)$pRow['id'],
                ];
            }
        } catch (Throwable $metaErr) {
            logApiError("Failed to resolve product preview cards: " . $metaErr->getMessage());
        }
    }

    // Format response (original text only; per-message translate is a separate action)
    $results = [];
    foreach ($messages as $msg) {
        $originalText = $msg['body'];
        $cachedTranslation = null;
        $cachedSourceLang = '';

        if ($msg['sender_id'] != $currentUserId && $msg['translated_text'] !== null) {
            $cachedTranslation = $msg['translated_text'];
            $cachedSourceLang = (string)($msg['source_lang'] ?? '');
        }

        $sharedProduct = null;
        if (preg_match('#(?:product\.php\?id=|/product/)(\d+)#i', $originalText, $singleMatch)) {
            $targetPid = (int)$singleMatch[1];
            if (isset($productMetaMap[$targetPid])) {
                $sharedProduct = $productMetaMap[$targetPid];
            }
        }

        $results[] = [
            'id' => $msg['id'],
            'body' => htmlspecialchars($originalText, ENT_QUOTES, 'UTF-8'),
            'original_text' => htmlspecialchars($originalText, ENT_QUOTES, 'UTF-8'),
            'cached_translation' => $cachedTranslation !== null
                ? htmlspecialchars($cachedTranslation, ENT_QUOTES, 'UTF-8')
                : null,
            'cached_source_lang' => $cachedSourceLang,
            'reply_to_message_id' => !empty($msg['reply_to_message_id']) ? (int)$msg['reply_to_message_id'] : null,
            'reply_body' => $msg['reply_body'] !== null
                ? htmlspecialchars((string)$msg['reply_body'], ENT_QUOTES, 'UTF-8')
                : null,
            'reply_sender_name' => $msg['reply_sender_name'] !== null
                ? htmlspecialchars((string)$msg['reply_sender_name'], ENT_QUOTES, 'UTF-8')
                : null,
            'is_mine' => $msg['sender_id'] == $currentUserId,
            'sender_name' => $msg['sender_name'],
            'created_at' => date('c', strtotime((string)($msg['created_at'] ?? ''))),
            'shared_product' => $sharedProduct,
        ];
    }

    $translator = getTranslationService();
    ob_clean();
    echo json_encode([
        'success' => true,
        'messages' => $results,
        'translation_configured' => $translator->isConfigured(),
    ]);
    exit;
} catch (Exception $e) {
    logApiError("Fetch Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
}

if ($action === 'send') {
    verifyCsrfTokenJson();
    $productId = (int)($_POST['product_id'] ?? 0);
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $replyToMessageId = (int)($_POST['reply_to_message_id'] ?? 0);
    $body = sanitize($_POST['body'] ?? '');
    
    if ($receiverId <= 0 || empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Missing or empty parameters']);
        exit;
    }
    
    if (mb_strlen($body) > 250) {
        echo json_encode(['success' => false, 'error' => 'Message exceeds maximum length of 250 characters']);
        exit;
    }

    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation context']);
        exit;
    }

    if ($replyToMessageId > 0) {
        $replyStmt = $pdo->prepare("SELECT id FROM messages WHERE id = :reply_id AND ((sender_id = :uid1 AND receiver_id = :other1) OR (sender_id = :other2 AND receiver_id = :uid2)) LIMIT 1");
        $replyStmt->execute([
            ':reply_id' => $replyToMessageId,
            ':uid1' => $currentUserId,
            ':other1' => $receiverId,
            ':other2' => $receiverId,
            ':uid2' => $currentUserId,
        ]);
        if (!$replyStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Invalid reply target']);
            exit;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, body, reply_to_message_id) VALUES (:sid, :rid, :pid, :body, :reply_to_message_id)");
        $stmt->execute([
            ':sid' => $currentUserId,
            ':rid' => $receiverId,
            ':pid' => $productId > 0 ? $productId : null,
            ':body' => $body,
            ':reply_to_message_id' => $replyToMessageId > 0 ? $replyToMessageId : null,
        ]);
        
        // Auto-create order if a product is involved
        if ($productId > 0) {
            $stmtProd = $pdo->prepare("SELECT user_id, price, status, listing_type FROM products WHERE id = ?");
            $stmtProd->execute([$productId]);
            $prod = $stmtProd->fetch();
            if ($prod && ($prod['listing_type'] ?? 'product') !== 'service' && ($prod['status'] ?? '') === 'active') {
                $sellerId = (int)$prod['user_id'];
                $buyerId = ($currentUserId === $sellerId) ? $receiverId : $currentUserId;
                
                // Check if a pending order already exists for this buyer and product
                $stmtCheck = $pdo->prepare("SELECT id FROM orders WHERE product_id = ? AND buyer_id = ? AND status = 'pending'");
                $stmtCheck->execute([$productId, $buyerId]);
                if (!$stmtCheck->fetch()) {
                    // Create pending order
                    $stmtOrder = $pdo->prepare("
                        INSERT INTO orders (product_id, buyer_id, amount, status, notes, expires_at)
                        VALUES (?, ?, ?, 'pending', ?, " . pendingOrderExpiresAtExpression() . ")
                    ");
                    $stmtOrder->execute([$productId, $buyerId, $prod['price'], 'Auto-created from direct message inquiry.']);
                }
            }

            // Track service inquiry events
            if ($prod && ($prod['listing_type'] ?? 'product') === 'service' && $currentUserId !== (int)$prod['user_id']) {
                $priorInqStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE product_id = ? AND sender_id = ?");
                $priorInqStmt->execute([$productId, $currentUserId]);
                $countInquiries = (int)$priorInqStmt->fetchColumn();
                $isFirst = ($countInquiries <= 1);

                if (function_exists('queuePostHogEvent')) {
                    if ($isFirst) {
                        queuePostHogEvent('service_first_inquiry', [
                            'product_id' => $productId,
                            'provider_id' => (int)$prod['user_id'],
                            'buyer_id' => $currentUserId
                        ]);
                    }
                    queuePostHogEvent('service_inquiry_sent', [
                        'product_id' => $productId,
                        'provider_id' => (int)$prod['user_id'],
                        'buyer_id' => $currentUserId,
                        'is_first' => $isFirst
                    ]);
                }
            }
        }
        
        // Notify receiver
        createNotification($pdo, $receiverId, 'message', "New Message", "You received a new message.", $productId > 0 ? $productId : null);
        
        $pdo->commit();

        // Off-site alert fallback: email for new buyer/seller messages.
        sendNewMessageEmailAlert(
            $pdo,
            $receiverId,
            $currentUserId,
            $productId > 0 ? $productId : null,
            $body
        );

        ob_clean();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logApiError("Send Error: " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'propose') {
    verifyCsrfTokenJson();
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if (!$productId) {
        echo json_encode(['error' => 'Missing product ID']);
        exit;
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, price_currency, user_id, listing_type FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    if (($product['listing_type'] ?? 'product') === 'service') {
        echo json_encode(['error' => 'Service bookings are not available during the pilot. Message the provider directly.']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Generate proposed message
    $quotedPrice = formatPrice(getDiscountedPrice($product), productCurrencyCode($product));
    $proposedBody = "Hi! I'm interested in purchasing '" . $product['title'] . "' for " . $quotedPrice . ".";
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (:sid, :rid, :pid, :body)");
        $stmt->execute([
            ':sid' => $currentUserId,
            ':rid' => $receiverId,
            ':pid' => $productId,
            ':body' => $proposedBody
        ]);
        
        // Notify receiver
        createNotification($pdo, $receiverId, 'message', "Purchase Proposal", "Someone wants to buy your item.", $productId);
        
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Purchase proposal sent!']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

if ($action === 'get_propose') {
    $productId = (int)($_GET['product_id'] ?? 0);
    
    if (!$productId) {
        echo json_encode(['error' => 'Missing product ID']);
        exit;
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, price_currency, user_id, listing_type FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    if (($product['listing_type'] ?? 'product') === 'service') {
        echo json_encode(['error' => 'Service bookings are not available during the pilot. Message the provider directly.']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Generate proposed message
    $quotedPrice = formatPrice(getDiscountedPrice($product), productCurrencyCode($product));
    $proposedBody = "Hi! I'm interested in purchasing '" . $product['title'] . "' for " . $quotedPrice . ".";
    
    echo json_encode(['success' => true, 'proposed_text' => $proposedBody]);
    exit;
}

// ─── Deal Handshake: Check Status ────────────────────────
if ($action === 'check_deal_status') {
    $productId = (int)($_GET['product_id'] ?? 0);
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);

    if (!$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    if ($productId > 0) {
        $typeStmt = $pdo->prepare("SELECT listing_type FROM products WHERE id = ?");
        $typeStmt->execute([$productId]);
        if ($typeStmt->fetchColumn() === 'service') {
            echo json_encode(['show_handshake' => false]);
            exit;
        }
    }

    if ($productId === 0) {
        // Special logic for product_id = 0
        $stmtBuyer = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = :me AND receiver_id = :other AND (product_id = 0 OR product_id IS NULL)");
        $stmtBuyer->execute([':me' => $currentUserId, ':other' => $otherUserId]);
        $myMsgCount = (int)$stmtBuyer->fetchColumn();

        $stmtSeller = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = :other AND receiver_id = :me AND (product_id = 0 OR product_id IS NULL)");
        $stmtSeller->execute([':other' => $otherUserId, ':me' => $currentUserId]);
        $otherMsgCount = (int)$stmtSeller->fetchColumn();

        if ($myMsgCount < 1 || $otherMsgCount < 1) {
            echo json_encode(['show_handshake' => false]);
            exit;
        }

        // Check if there is an active buyer_confirmed deal
        $stmtDeal = $pdo->prepare("
            SELECT d.*, p.title as product_title, p.listing_type, p.pricing_model, p.user_id as seller_id, u.username as buyer_username
            FROM deal_confirmations d
            JOIN products p ON d.product_id = p.id
            JOIN users u ON d.buyer_id = u.id
            WHERE p.listing_type <> 'service'
            AND ((d.buyer_id = :me AND d.seller_id = :other) OR (d.buyer_id = :other AND d.seller_id = :me))
            AND d.status = 'buyer_confirmed'
            LIMIT 1
        ");
        $stmtDeal->execute([':me' => $currentUserId, ':other' => $otherUserId]);
        $deal = $stmtDeal->fetch(PDO::FETCH_ASSOC);

        if ($deal) {
            $isSeller = ($currentUserId === (int)$deal['seller_id']);
            echo json_encode([
                'show_handshake' => true,
                'deal' => [
                    'id' => $deal['id'],
                    'status' => $deal['status'],
                    'buyer_confirmed_at' => $deal['buyer_confirmed_at'],
                    'seller_confirmed_at' => $deal['seller_confirmed_at'],
                    'is_seller' => $isSeller,
                    'buyer_username' => $deal['buyer_username'],
                    'product_title' => $deal['product_title'],
                    'product_id' => $deal['product_id'],
                    'listing_type' => $deal['listing_type'],
                    'pricing_model' => $deal['pricing_model']
                ]
            ]);
            exit;
        }

        // Check if there are active products between them
        $stmtProds = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id IN (:me, :other) AND status = 'active'");
        $stmtProds->execute([':me' => $currentUserId, ':other' => $otherUserId]);
        if ((int)$stmtProds->fetchColumn() > 0) {
            echo json_encode([
                'show_handshake' => true,
                'deal' => [
                    'status' => 'choose_product'
                ]
            ]);
            exit;
        }

        echo json_encode(['show_handshake' => false]);
        exit;
    }


    // Determine seller
    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = :pid");
    $stmt->execute([':pid' => $productId]);
    $sellerId = (int)$stmt->fetchColumn();

    if (!$sellerId) {
        echo json_encode(['show_handshake' => false]);
        exit;
    }

    // Figure out buyer/seller roles
    $isSeller = ($currentUserId === $sellerId);
    $buyerId = $isSeller ? $otherUserId : $currentUserId;

    // Check both parties have sent at least one message
    $stmtBuyer = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = :buyer AND receiver_id = :seller AND product_id = :pid");
    $stmtBuyer->execute([':buyer' => $buyerId, ':seller' => $sellerId, ':pid' => $productId]);
    $buyerMsgCount = (int)$stmtBuyer->fetchColumn();

    $stmtSeller = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = :seller AND receiver_id = :buyer AND product_id = :pid");
    $stmtSeller->execute([':seller' => $sellerId, ':buyer' => $buyerId, ':pid' => $productId]);
    $sellerMsgCount = (int)$stmtSeller->fetchColumn();

    if ($buyerMsgCount < 1 || $sellerMsgCount < 1) {
        echo json_encode(['show_handshake' => false]);
        exit;
    }

    // Check/create deal_confirmations record
    $stmtDeal = $pdo->prepare("SELECT * FROM deal_confirmations WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid");
    $stmtDeal->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
    $deal = $stmtDeal->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        // Create a new pending record
        $stmtInsert = $pdo->prepare("INSERT INTO deal_confirmations (product_id, buyer_id, seller_id) VALUES (:pid, :bid, :sid)");
        $stmtInsert->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
        $deal = [
            'id' => $pdo->lastInsertId(),
            'status' => 'pending',
            'buyer_confirmed_at' => null,
            'seller_confirmed_at' => null,
        ];
    }

    /* Dismissed status is no longer used to hide the bar, ensuring it remains persistent until completion */
    /* if ($deal['status'] === 'dismissed') {
        echo json_encode(['show_handshake' => false]);
        exit;
    } */

    // Fetch buyer username for display
    $stmtBuyerName = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmtBuyerName->execute([':id' => $buyerId]);
    $buyerUsername = $stmtBuyerName->fetchColumn();

    // Fetch product info for display
    $stmtProd = $pdo->prepare("SELECT title, listing_type, pricing_model FROM products WHERE id = :id");
    $stmtProd->execute([':id' => $productId]);
    $productInfo = $stmtProd->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'show_handshake' => true,
        'deal' => [
            'id' => $deal['id'],
            'status' => $deal['status'],
            'buyer_confirmed_at' => $deal['buyer_confirmed_at'],
            'seller_confirmed_at' => $deal['seller_confirmed_at'],
            'is_seller' => $isSeller,
            'buyer_username' => $buyerUsername,
            'product_title' => $productInfo['title'] ?? '',
            'listing_type' => $productInfo['listing_type'] ?? 'product',
            'pricing_model' => $productInfo['pricing_model'] ?? 'flat'
        ]
    ]);
    exit;
}

// ─── Deal Handshake: Confirm Deal ────────────────────────
if ($action === 'confirm_deal') {
    verifyCsrfTokenJson();
    $productId = (int)($_POST['product_id'] ?? 0);
    $otherUserId = (int)($_POST['other_user_id'] ?? 0);

    if (!$productId || !$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    // Determine seller and product info
    $stmt = $pdo->prepare("
        SELECT p.user_id, p.title, p.category_id, p.listing_type, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = :pid
    ");
    $stmt->execute([':pid' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    if (($product['listing_type'] ?? 'product') === 'service') {
        echo json_encode(['error' => 'Service bookings are not available during the pilot.']);
        exit;
    }

    $sellerId = (int)$product['user_id'];
    $productTitle = $product['title'];
    $listingType = $product['listing_type'];
    $isSeller = ($currentUserId === $sellerId);
    $buyerId = $isSeller ? $otherUserId : $currentUserId;

    // Get current user's username
    $stmtMe = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmtMe->execute([':id' => $currentUserId]);
    $myUsername = $stmtMe->fetchColumn();

    try {
        $pdo->beginTransaction();

        if ($isSeller) {
            $saleResult = completeProductSale($pdo, $productId, $sellerId, $buyerId, 'chat');
            if (!$saleResult['success']) {
                throw new RuntimeException('Sale completion failed');
            }

            createNotification($pdo, $buyerId, 'order', 'Deal Confirmed!',
                "$myUsername confirmed the deal for '$productTitle'. It has been marked as sold.", $productId);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'action' => 'delisted',
                'listing_id' => $productId,
                'category' => $product['category_name'] ?? ''
            ]);
        } else {
            // Check if deal confirmation exists, if not create it
            $stmtCheck = $pdo->prepare("SELECT id FROM deal_confirmations WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid");
            $stmtCheck->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            $exists = $stmtCheck->fetchColumn();
            
            if (!$exists) {
                $stmtIns = $pdo->prepare("INSERT INTO deal_confirmations (product_id, buyer_id, seller_id, status) VALUES (:pid, :bid, :sid, 'pending')");
                $stmtIns->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            }

            // Capture scheduling data if provided
            $schedStart = !empty($_POST['scheduled_start']) ? $_POST['scheduled_start'] : null;
            $schedEnd = !empty($_POST['scheduled_end']) ? $_POST['scheduled_end'] : null;

            // Buyer confirms → awaiting seller
            $stmtUp = $pdo->prepare("
                UPDATE deal_confirmations 
                SET buyer_confirmed_at = NOW(), status = 'buyer_confirmed', updated_at = NOW(),
                    scheduled_start = :start, scheduled_end = :end
                WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid
            ");
            $stmtUp->execute([
                ':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId,
                ':start' => $schedStart, ':end' => $schedEnd
            ]);

            // Notify seller
            $notificationTitle = ($listingType === 'service') ? 'Service Booking Request' : 'Deal Confirmation Request';
            $notificationMsg = ($listingType === 'service')
                ? "$myUsername wants to book your service '$productTitle'. Open the chat to confirm the schedule."
                : "$myUsername says the deal for '$productTitle' is done. Open the chat to confirm and delist.";
            createNotification($pdo, $sellerId, 'order', $notificationTitle, $notificationMsg, $productId);

            $pdo->commit();
            echo json_encode(['success' => true, 'action' => 'awaiting_seller']);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

if ($action === 'amend_service_booking') {
    verifyCsrfTokenJson();
    echo json_encode(['success' => false, 'error' => 'Service bookings are not available during the pilot.']);
    exit;
}

// ─── Get Active Products ────────────────────────
if ($action === 'get_active_products') {
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, title, user_id, price, price_currency FROM products WHERE user_id IN (:me, :other) AND status = 'active' AND listing_type <> 'service' AND price IS NOT NULL ORDER BY created_at DESC");
    $stmt->execute([':me' => $currentUserId, ':other' => $otherUserId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format products
    $results = [];
    foreach ($products as $p) {
        $results[] = [
            'id' => $p['id'],
            'title' => $p['title'],
            'user_id' => $p['user_id'],
            'price' => formatPrice($p['price'], productCurrencyCode($p)),
            'is_mine' => $p['user_id'] == $currentUserId
        ];
    }
    echo json_encode(['success' => true, 'products' => $results]);
    exit;
}

// ─── Delete Message ────────────────────────
if ($action === 'delete_message') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfTokenJson();
    }
    $messageId = (int)($_POST['message_id'] ?? $_GET['message_id'] ?? 0);
    
    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing message ID']);
        exit;
    }
    
    // Fetch message details
    $stmt = $pdo->prepare("SELECT sender_id, receiver_id, created_at FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    
    if (!$msg) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }
    
    $senderId = (int)$msg['sender_id'];
    $receiverId = (int)$msg['receiver_id'];
    $createdAt = strtotime($msg['created_at']);
    $now = time();
    $diffMinutes = ($now - $createdAt) / 60;
    
    if ($senderId !== $currentUserId && $receiverId !== $currentUserId && !isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized to delete this message']);
        exit;
    }
    
    try {
        if (isAdmin()) {
            $stmtDel = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmtDel->execute([$messageId]);
        } elseif ($senderId === $currentUserId && $diffMinutes <= 10) {
            // Delete for both sides
            $stmtDel = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmtDel->execute([$messageId]);
        } else {
            // Soft delete from current user's side
            if ($senderId === $currentUserId) {
                $stmtUpd = $pdo->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE id = ?");
            } else {
                $stmtUpd = $pdo->prepare("UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?");
            }
            $stmtUpd->execute([$messageId]);
            
            // If deleted by both, delete the row
            $stmtDelClean = $pdo->prepare("DELETE FROM messages WHERE id = ? AND deleted_by_sender = 1 AND deleted_by_receiver = 1");
            $stmtDelClean->execute([$messageId]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── Clear Chat ────────────────────────────
if ($action === 'clear_chat') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfTokenJson();
    }
    $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
    $otherUserId = (int)($_POST['other_user_id'] ?? $_GET['other_user_id'] ?? 0);
    
    if ($otherUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing other user ID']);
        exit;
    }
    
    if (!isValidConversation($pdo, $productId, $currentUserId, $otherUserId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation context']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update messages sent by current user to other user
        $stmtUpSender = $pdo->prepare("
            UPDATE messages
            SET deleted_by_sender = 1
            WHERE sender_id = :uid AND receiver_id = :other
        ");
        $stmtUpSender->execute([
            ':uid' => $currentUserId,
            ':other' => $otherUserId
        ]);
        
        // Update messages received by current user from other user
        $stmtUpReceiver = $pdo->prepare("
            UPDATE messages
            SET deleted_by_receiver = 1
            WHERE receiver_id = :uid AND sender_id = :other
        ");
        $stmtUpReceiver->execute([
            ':uid' => $currentUserId,
            ':other' => $otherUserId
        ]);
        
        // Clean up messages deleted by both sides
        $stmtClean = $pdo->prepare("
            DELETE FROM messages
            WHERE deleted_by_sender = 1 AND deleted_by_receiver = 1
              AND (
                  (sender_id = :uid1 AND receiver_id = :other1) OR
                  (sender_id = :other2 AND receiver_id = :uid2)
              )
        ");
        $stmtClean->execute([
            ':uid1' => $currentUserId,
            ':other1' => $otherUserId,
            ':other2' => $otherUserId,
            ':uid2' => $currentUserId
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── Per-message translation ───────────────────────
if ($action === 'translate_message') {
    verifyCsrfTokenJson();

    $messageId = (int)($_POST['message_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $otherUserId = (int)($_POST['other_user_id'] ?? 0);

    if ($messageId <= 0 || $otherUserId <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }

    if (!isValidConversation($pdo, $productId, $currentUserId, $otherUserId)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid conversation context']);
        exit;
    }

    $translator = getTranslationService();
    if (!$translator->isConfigured()) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Translation unavailable']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.body, m.sender_id, m.receiver_id
        FROM messages m
        WHERE m.id = :id
          AND (
              (m.sender_id = :uid1 AND m.receiver_id = :other1 AND (m.deleted_by_sender = 0 OR m.deleted_by_sender IS NULL)) OR
              (m.sender_id = :other2 AND m.receiver_id = :uid2 AND (m.deleted_by_receiver = 0 OR m.deleted_by_receiver IS NULL))
          )
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $messageId,
        ':uid1' => $currentUserId,
        ':other1' => $otherUserId,
        ':other2' => $otherUserId,
        ':uid2' => $currentUserId,
    ]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }

    if ((int)$message['sender_id'] === $currentUserId) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Cannot translate your own message']);
        exit;
    }

    $myLang = i18nGetLocale();
    $originalText = (string)$message['body'];
    $transResult = $translator->translateMessage($messageId, $originalText, $myLang, $pdo);

    if ($transResult['source_lang'] === $myLang || $transResult['source_lang'] === 'unknown') {
        ob_clean();
        echo json_encode([
            'success' => true,
            'already_same_language' => true,
            'message_id' => $messageId,
            'original_text' => htmlspecialchars($originalText, ENT_QUOTES, 'UTF-8'),
        ]);
        exit;
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'translated_text' => htmlspecialchars($transResult['translated_text'], ENT_QUOTES, 'UTF-8'),
        'original_text' => htmlspecialchars($originalText, ENT_QUOTES, 'UTF-8'),
        'source_lang' => $transResult['source_lang'],
    ]);
    exit;
}

// ─── Set Preferred Language ────────────────────────
if ($action === 'set_language') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfTokenJson();
    }
    $lang = $_POST['language'] ?? $_GET['language'] ?? '';
    
    if (empty($lang) || !array_key_exists($lang, SUPPORTED_LANGUAGES)) {
        echo json_encode(['success' => false, 'error' => 'Invalid or unsupported language']);
        exit;
    }
    
    if (isLoggedIn()) {
        setUserPreferredLanguage($pdo, currentUserId(), $lang);
    }
    
    $_SESSION['preferred_language'] = $lang;
    
    // Set language cookie so it persists for guests and serverless functions
    $isSecureRequest = (
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
    setcookie('campusmarket_lang', $lang, [
        'expires'  => time() + 86400 * 30, // 30 days
        'path'     => '/',
        'secure'   => $isSecureRequest,
        'samesite' => 'Lax',
        'httponly' => false, // Accessible by client-side JS
    ]);
    
    i18nInit($lang);
    echo json_encode(['success' => true]);
    exit;
}

// ─── Get User Presence ───────────────────────────────────
if ($action === 'get_presence') {
    $targetUserId = (int)($_GET['user_id'] ?? 0);
    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing user_id']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT last_seen_at FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['last_seen_at']) {
            ob_clean();
            echo json_encode(['success' => true, 'status' => 'offline', 'mins' => null]);
            exit;
        }

        $diffSeconds = time() - strtotime($row['last_seen_at']);

        if ($diffSeconds <= 120) {
            $status = 'online';
            $mins   = null;
        } elseif ($diffSeconds <= 3600) {
            $status = 'recently';
            $mins   = (int) ceil($diffSeconds / 60);
        } else {
            $status = 'offline';
            $mins   = null;
        }

        ob_clean();
        echo json_encode(['success' => true, 'status' => $status, 'mins' => $mins]);
    } catch (\Throwable $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'DB error']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
