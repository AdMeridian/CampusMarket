<?php
require_once __DIR__ . '/../includes/bootstrap.php';
ob_start(); // Buffer output to prevent warnings from breaking JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function logApiError($msg) {
    file_put_contents(__DIR__ . '/../api_error.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$currentUserId = currentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function isValidProductConversation(PDO $pdo, int $productId, int $currentUserId, int $otherUserId): bool {
    if ($productId <= 0 || $currentUserId <= 0 || $otherUserId <= 0 || $currentUserId === $otherUserId) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = :pid");
    $stmt->execute([':pid' => $productId]);
    $sellerId = (int) $stmt->fetchColumn();

    return $sellerId > 0
        && ($currentUserId === $sellerId || $otherUserId === $sellerId);
}

if ($action === 'fetch') {
    try {
        $productId = (int)($_GET['product_id'] ?? 0);
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);
    
    if (!$productId || !$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    if (!isValidProductConversation($pdo, $productId, $currentUserId, $otherUserId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Mark messages sent to me as read
    $stmtRead = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :uid AND sender_id = :other AND product_id = :pid AND is_read = 0");
    $stmtRead->execute([
        ':uid' => $currentUserId,
        ':other' => $otherUserId,
        ':pid' => $productId
    ]);

    // Keep notification badge in sync with read state in chat.
    $stmtNotifRead = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = :uid
          AND type = 'message'
          AND reference_id = :pid
          AND is_read = 0
    ");
    $stmtNotifRead->execute([
        ':uid' => $currentUserId,
        ':pid' => $productId
    ]);
    
    // Fetch messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE m.product_id = :pid
          AND (
              (m.sender_id = :uid1 AND m.receiver_id = :other1) OR
              (m.sender_id = :other2 AND m.receiver_id = :uid2)
          )
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        ':pid' => $productId,
        ':uid1' => $currentUserId,
        ':other1' => $otherUserId,
        ':other2' => $otherUserId,
        ':uid2' => $currentUserId
    ]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $results = [];
    foreach ($messages as $msg) {
        $results[] = [
            'id' => $msg['id'],
            'body' => htmlspecialchars($msg['body']),
            'is_mine' => $msg['sender_id'] == $currentUserId,
            'sender_name' => $msg['sender_name'],
            'created_at' => date('Y-m-d H:i:s', strtotime($msg['created_at']))
        ];
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'messages' => $results]);
    exit;
} catch (Exception $e) {
    logApiError("Fetch Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
}

if ($action === 'send') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $body = sanitize($_POST['body'] ?? '');
    
    if (!$productId || !$receiverId || empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Missing or empty parameters']);
        exit;
    }

    if (!isValidProductConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation context']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (:sid, :rid, :pid, :body)");
        $stmt->execute([
            ':sid' => $currentUserId,
            ':rid' => $receiverId,
            ':pid' => $productId,
            ':body' => $body
        ]);
        
        // Notify receiver
        createNotification($pdo, $receiverId, 'message', "New Message", "You received a new message.", $productId);
        
        $pdo->commit();
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
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if (!$productId) {
        echo json_encode(['error' => 'Missing product ID']);
        exit;
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, user_id FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidProductConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Generate proposed message
    $quotedPrice = formatPrice(getDiscountedPrice($product));
    $proposedBody = "Hi! I'm interested in purchasing your item '" . $product['title'] . "' for " . $quotedPrice . ". Can we arrange a meetup to complete the transaction? Please let me know your availability.";
    
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
        $pdo->rollBack();
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
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, user_id FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidProductConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Generate proposed message
    $quotedPrice = formatPrice(getDiscountedPrice($product));
    $proposedBody = "Hi! I'm interested in purchasing your item '" . $product['title'] . "' for " . $quotedPrice . ". Can we arrange a meetup to complete the transaction? Please let me know your availability.";
    
    echo json_encode(['success' => true, 'proposed_text' => $proposedBody]);
    exit;
}

// ─── Deal Handshake: Check Status ────────────────────────
if ($action === 'check_deal_status') {
    $productId = (int)($_GET['product_id'] ?? 0);
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);

    if (!$productId || !$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
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

    // Fetch product title for display
    $stmtProd = $pdo->prepare("SELECT title FROM products WHERE id = :id");
    $stmtProd->execute([':id' => $productId]);
    $productTitle = $stmtProd->fetchColumn();

    echo json_encode([
        'show_handshake' => true,
        'deal' => [
            'id' => $deal['id'],
            'status' => $deal['status'],
            'buyer_confirmed_at' => $deal['buyer_confirmed_at'],
            'seller_confirmed_at' => $deal['seller_confirmed_at'],
            'is_seller' => $isSeller,
            'buyer_username' => $buyerUsername,
            'product_title' => $productTitle,
        ]
    ]);
    exit;
}

// ─── Deal Handshake: Confirm Deal ────────────────────────
if ($action === 'confirm_deal') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $otherUserId = (int)($_POST['other_user_id'] ?? 0);

    if (!$productId || !$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    // Determine seller
    $stmt = $pdo->prepare("SELECT user_id, title FROM products WHERE id = :pid");
    $stmt->execute([':pid' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $sellerId = (int)$product['user_id'];
    $productTitle = $product['title'];
    $isSeller = ($currentUserId === $sellerId);
    $buyerId = $isSeller ? $otherUserId : $currentUserId;

    // Get current user's username
    $stmtMe = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmtMe->execute([':id' => $currentUserId]);
    $myUsername = $stmtMe->fetchColumn();

    try {
        $pdo->beginTransaction();

        if ($isSeller) {
            // Seller confirms → mark completed and delist product
            $stmtUp = $pdo->prepare("
                UPDATE deal_confirmations 
                SET seller_confirmed_at = NOW(), status = 'completed', updated_at = NOW()
                WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid
            ");
            $stmtUp->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);

            // Mark product as sold
            $stmtProd = $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = :pid");
            $stmtProd->execute([':pid' => $productId]);

            // Notify buyer
            createNotification($pdo, $buyerId, 'order', 'Deal Confirmed!',
                "$myUsername confirmed the deal for '$productTitle'. It has been marked as sold.", $productId);

            $pdo->commit();
            echo json_encode(['success' => true, 'action' => 'delisted']);
        } else {
            // Buyer confirms → awaiting seller
            $stmtUp = $pdo->prepare("
                UPDATE deal_confirmations 
                SET buyer_confirmed_at = NOW(), status = 'buyer_confirmed', updated_at = NOW()
                WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid
            ");
            $stmtUp->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);

            // Notify seller
            createNotification($pdo, $sellerId, 'order', 'Deal Confirmation Request',
                "$myUsername says the deal for '$productTitle' is done. Open the chat to confirm and delist.", $productId);

            $pdo->commit();
            echo json_encode(['success' => true, 'action' => 'awaiting_seller']);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// dismiss_deal action removed as handshake bar is now persistent until completion

echo json_encode(['error' => 'Invalid action']);
