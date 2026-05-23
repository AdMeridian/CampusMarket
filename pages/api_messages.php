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
        ob_clean();
        echo json_encode(['success' => true, 'users' => []]);
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
            'id' => $u['id'],
            'username' => $u['username'],
            'avatar_url' => avatarUrl($u['avatar'])
        ];
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'users' => $results]);
    exit;
}

if ($action === 'fetch') {
    try {
        $productId = (int)($_GET['product_id'] ?? 0);
    $translateRequested = (string)($_GET['translate'] ?? '0') === '1';
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);
    
    if ($otherUserId <= 0) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    if (!isValidConversation($pdo, $productId, $currentUserId, $otherUserId)) {
        echo json_encode(['error' => 'Invalid conversation context']);
        exit;
    }
    
    // Mark messages sent to me as read
    $stmtRead = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = :uid AND sender_id = :other AND (product_id = :pid1 OR (:pid2 = 0 AND product_id IS NULL)) AND is_read = FALSE");
    $stmtRead->execute([
        ':uid' => $currentUserId,
        ':other' => $otherUserId,
        ':pid1' => $productId,
        ':pid2' => $productId
    ]);

    // Keep notification badge in sync with read state in chat.
    $stmtNotifRead = $pdo->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE user_id = :uid
          AND type = 'message'
          AND (reference_id = :pid1 OR (:pid2 = 0 AND reference_id IS NULL))
          AND is_read = FALSE
    ");
    $stmtNotifRead->execute([
        ':uid' => $currentUserId,
        ':pid1' => $productId,
        ':pid2' => $productId
    ]);
    
    // Fetch messages with translation for current user's preferred language
    $myLang = i18nGetLocale();
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_name,
               t.translated_text, t.source_lang
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN message_translations t ON m.id = t.message_id AND t.target_lang = :mylang
        WHERE (m.product_id = :pid1 OR (:pid2 = 0 AND m.product_id IS NULL))
          AND (
              (m.sender_id = :uid1 AND m.receiver_id = :other1) OR
              (m.sender_id = :other2 AND m.receiver_id = :uid2)
          )
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([
        ':mylang' => $myLang,
        ':pid1' => $productId,
        ':pid2' => $productId,
        ':uid1' => $currentUserId,
        ':other1' => $otherUserId,
        ':other2' => $otherUserId,
        ':uid2' => $currentUserId
    ]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $results = [];
    $translator = $translateRequested ? getTranslationService() : null;
    foreach ($messages as $msg) {
        $body = $msg['body'];
        $originalText = $msg['body'];
        $isTranslated = false;
        $sourceLang = '';

        if ($translateRequested && $msg['sender_id'] != $currentUserId) {
            if ($msg['translated_text'] !== null) {
                if ($msg['source_lang'] !== $myLang && $msg['source_lang'] !== 'unknown') {
                    $body = $msg['translated_text'];
                    $isTranslated = true;
                    $sourceLang = $msg['source_lang'];
                }
            } elseif ($translator && $translator->isConfigured()) {
                // Translate on-the-fly and cache
                $transResult = $translator->translateMessage((int)$msg['id'], $msg['body'], $myLang, $pdo);
                if ($transResult['source_lang'] !== $myLang && $transResult['source_lang'] !== 'unknown') {
                    $body = $transResult['translated_text'];
                    $isTranslated = true;
                    $sourceLang = $transResult['source_lang'];
                }
            }
        }

        $results[] = [
            'id' => $msg['id'],
            'body' => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
            'original_text' => htmlspecialchars($originalText, ENT_QUOTES, 'UTF-8'),
            'is_translated' => $isTranslated,
            'source_lang' => $sourceLang,
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
    verifyCsrfTokenJson();
    $productId = (int)($_POST['product_id'] ?? 0);
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $body = sanitize($_POST['body'] ?? '');
    
    if ($receiverId <= 0 || empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Missing or empty parameters']);
        exit;
    }

    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
        echo json_encode(['success' => false, 'error' => 'Invalid conversation context']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, body) VALUES (:sid, :rid, :pid, :body)");
        $stmt->execute([
            ':sid' => $currentUserId,
            ':rid' => $receiverId,
            ':pid' => $productId > 0 ? $productId : null,
            ':body' => $body
        ]);
        
        // Auto-create order if a product is involved
        if ($productId > 0) {
            $stmtProd = $pdo->prepare("SELECT user_id, price FROM products WHERE id = ?");
            $stmtProd->execute([$productId]);
            $prod = $stmtProd->fetch();
            if ($prod) {
                $sellerId = (int)$prod['user_id'];
                $buyerId = ($currentUserId === $sellerId) ? $receiverId : $currentUserId;
                
                // Check if a pending order already exists for this buyer and product
                $stmtCheck = $pdo->prepare("SELECT id FROM orders WHERE product_id = ? AND buyer_id = ? AND status = 'pending'");
                $stmtCheck->execute([$productId, $buyerId]);
                if (!$stmtCheck->fetch()) {
                    // Create pending order
                    $stmtOrder = $pdo->prepare("INSERT INTO orders (product_id, buyer_id, amount, status, notes) VALUES (?, ?, ?, 'pending', ?)");
                    $stmtOrder->execute([$productId, $buyerId, $prod['price'], 'Auto-created from direct message inquiry.']);
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
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, user_id FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
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
    $stmt = $pdo->prepare("SELECT title, price, discount_percent, user_id FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    $receiverId = $product['user_id'];
    
    if (!isValidConversation($pdo, $productId, $currentUserId, $receiverId)) {
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

    if (!$otherUserId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
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
            SELECT d.*, p.title as product_title, p.user_id as seller_id, u.username as buyer_username
            FROM deal_confirmations d
            JOIN products p ON d.product_id = p.id
            JOIN users u ON d.buyer_id = u.id
            WHERE ((d.buyer_id = :me AND d.seller_id = :other) OR (d.buyer_id = :other AND d.seller_id = :me))
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
                    'product_id' => $deal['product_id']
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
    verifyCsrfTokenJson();
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
            // Check if deal confirmation exists, if not create it
            $stmtCheck = $pdo->prepare("SELECT id FROM deal_confirmations WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid");
            $stmtCheck->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            $exists = $stmtCheck->fetchColumn();
            
            if (!$exists) {
                $stmtIns = $pdo->prepare("INSERT INTO deal_confirmations (product_id, buyer_id, seller_id, status) VALUES (:pid, :bid, :sid, 'pending')");
                $stmtIns->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            }

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

            // Update order status to completed and insert transaction
            $stmtOrderCheck = $pdo->prepare("SELECT id, amount FROM orders WHERE product_id = :pid AND buyer_id = :bid AND status = 'pending'");
            $stmtOrderCheck->execute([':pid' => $productId, ':bid' => $buyerId]);
            $order = $stmtOrderCheck->fetch();
            
            if ($order) {
                // Mark order completed
                $stmtUpdateOrder = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = :id");
                $stmtUpdateOrder->execute([':id' => $order['id']]);
                
                // Insert transaction
                $stmtTrans = $pdo->prepare("INSERT INTO transactions (order_id, amount, status) VALUES (:oid, :amount, 'success')");
                $stmtTrans->execute([':oid' => $order['id'], ':amount' => $order['amount']]);
            }

            // Notify buyer
            createNotification($pdo, $buyerId, 'order', 'Deal Confirmed!',
                "$myUsername confirmed the deal for '$productTitle'. It has been marked as sold.", $productId);

            $pdo->commit();
            echo json_encode(['success' => true, 'action' => 'delisted']);
        } else {
            // Check if deal confirmation exists, if not create it
            $stmtCheck = $pdo->prepare("SELECT id FROM deal_confirmations WHERE product_id = :pid AND buyer_id = :bid AND seller_id = :sid");
            $stmtCheck->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            $exists = $stmtCheck->fetchColumn();
            
            if (!$exists) {
                $stmtIns = $pdo->prepare("INSERT INTO deal_confirmations (product_id, buyer_id, seller_id, status) VALUES (:pid, :bid, :sid, 'pending')");
                $stmtIns->execute([':pid' => $productId, ':bid' => $buyerId, ':sid' => $sellerId]);
            }

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

// ─── Get Active Products ────────────────────────
if ($action === 'get_active_products') {
    $otherUserId = (int)($_GET['other_user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, title, user_id, price FROM products WHERE user_id IN (:me, :other) AND status = 'active' ORDER BY created_at DESC");
    $stmt->execute([':me' => $currentUserId, ':other' => $otherUserId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format products
    $results = [];
    foreach ($products as $p) {
        $results[] = [
            'id' => $p['id'],
            'title' => $p['title'],
            'user_id' => $p['user_id'],
            'price' => formatPrice($p['price']),
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
    
    // Fetch message to verify ownership
    $stmt = $pdo->prepare("SELECT sender_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $senderId = $stmt->fetchColumn();
    
    if (!$senderId) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }
    
    if ($senderId != $currentUserId && !isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized to delete this message']);
        exit;
    }
    
    try {
        $stmtDel = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmtDel->execute([$messageId]);
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
        $stmtDel = $pdo->prepare("
            DELETE FROM messages 
            WHERE (product_id = :pid1 OR (:pid2 = 0 AND product_id IS NULL))
              AND (
                  (sender_id = :uid1 AND receiver_id = :other1) OR
                  (sender_id = :other2 AND receiver_id = :uid2)
              )
        ");
        $stmtDel->execute([
            ':pid1' => $productId,
            ':pid2' => $productId,
            ':uid1' => $currentUserId,
            ':other1' => $otherUserId,
            ':other2' => $otherUserId,
            ':uid2' => $currentUserId
        ]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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

echo json_encode(['error' => 'Invalid action']);
