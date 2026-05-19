<?php
$pageTitle = "Messages";
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$productId = (int)($_GET['product_id'] ?? 0);
$otherUserId = (int)($_GET['other_user_id'] ?? 0);
$currentUserId = currentUserId();

if (!$otherUserId) {
    setFlash('error', 'Invalid conversation context.');
    redirect(BASE_URL . '/pages/inbox.php');
}

// Special Case: Support Chat (product_id = 0)
// Allowed for any valid users (will be treated as Support if either is Admin, else standard Direct Message)

if ($productId > 0) {
    // Fetch context info
    $stmt = $pdo->prepare("SELECT p.title, p.price, p.discount_percent, i.image_path FROM products p LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE WHERE p.id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = :id");
    $stmt->execute([':id' => $otherUserId]);
    $otherUser = $stmt->fetch();

    if (!$product || !$otherUser) {
        setFlash('error', 'Product or User no longer exists.');
        redirect(BASE_URL . '/pages/inbox.php');
    }

    // Keep product chat context valid: conversations should involve the seller for this product.
    $stmt = $pdo->prepare("SELECT user_id FROM products WHERE id = :pid");
    $stmt->execute([':pid' => $productId]);
    $sellerId = (int) $stmt->fetchColumn();
    $isValidConversation = $sellerId > 0
        && ($currentUserId === $sellerId || $otherUserId === $sellerId)
        && ($currentUserId !== $otherUserId);

    if (!$isValidConversation) {
        setFlash('error', 'Invalid chat context for this product.');
        redirect(BASE_URL . '/pages/inbox.php');
    }
} else {
    // Support or Direct Message Context
    $stmt = $pdo->prepare("SELECT username, avatar, role FROM users WHERE id = :id");
    $stmt->execute([':id' => $otherUserId]);
    $otherUser = $stmt->fetch();
    
    if (!$otherUser) {
        setFlash('error', 'User no longer exists.');
        redirect(BASE_URL . '/pages/inbox.php');
    }
    
    // Check if either current user or other user is admin
    $stmtMe = $pdo->prepare("SELECT role FROM users WHERE id = :id");
    $stmtMe->execute([':id' => $currentUserId]);
    $myRole = $stmtMe->fetchColumn();
    
    $isSupport = ($otherUser['role'] === 'admin' || $myRole === 'admin');
    
    $product = [
        'title' => $isSupport ? 'CampusMarket Support' : 'Direct Message',
        'price' => 0,
        'discount_percent' => 0,
        'image_path' => null
    ];
    $sellerId = -1; // Not a seller conversation
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container main-content mb-20" style="max-width: 900px; margin-top: 6rem;">
    <!-- Chat Header Context -->
    <div class="glass-panel mb-4 p-4 flex justify-between items-center" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-md);">
        <div class="flex items-center gap-4">
            <button onclick="goBackOrInbox()" class="flex items-center justify-center cursor-pointer hover-scale transition-all duration-200" style="width: 40px; height: 40px; border-radius: var(--radius-md); border: 1px solid var(--border-light); background: var(--bg-surface); color: var(--text-main); margin-right: 0.25rem;" title="Go Back">
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <div style="width: 50px; height: 50px; flex-shrink: 0;">
                <?php if (!empty($otherUser['avatar'])): ?>
                    <img src="<?= avatarUrl($otherUser['avatar']) ?>" alt="<?= htmlspecialchars($otherUser['username']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: var(--bg-surface); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.25rem; color: var(--primary);">
                        <?php echo strtoupper(substr($otherUser['username'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="mb-0 font-bold" style="line-height: 1.2;">@<?= htmlspecialchars($otherUser['username']) ?></h3>
                <p class="text-muted small mb-0 flex items-center gap-1">
                    <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 2px;"></span> Active recently
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 text-right hidden sm:flex">
            <?php if ($productId > 0): ?>
                <div>
                    <p class="mb-0 text-muted small uppercase tracking-wider font-bold" style="font-size: 0.65rem;">Regarding Item</p>
                    <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $productId ?>" class="font-bold text-main hover:text-primary transition-colors" style="text-decoration: none; font-size: 0.9rem; display: block; max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($product['title']) ?>
                    </a>
                    <p class="text-primary font-bold mb-0" style="font-size: 0.9rem;"><?= renderProductPrice($product) ?></p>
                </div>
                <div style="width: 48px; height: 48px; flex-shrink: 0; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-light);">
                    <img src="<?= getProductImage($product['image_path'] ?? null) ?>" alt="Product" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php else: ?>
                <div>
                    <p class="mb-0 text-muted small uppercase tracking-wider font-bold" style="font-size: 0.65rem;">Conversation</p>
                    <span class="font-bold text-main" style="font-size: 0.9rem;"><?= htmlspecialchars($product['title']) ?></span>
                    <p class="text-secondary font-bold mb-0" style="font-size: 0.9rem;"><?= $product['title'] === 'CampusMarket Support' ? 'Official Help' : 'Private DM' ?></p>
                </div>
                <div style="width: 48px; height: 48px; flex-shrink: 0; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-light); background: var(--secondary-light); display: flex; align-items: center; justify-content: center; color: var(--secondary);">
                    <?php if ($product['title'] === 'CampusMarket Support'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chat Container -->
    <div class="glass-panel" style="position: relative; border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-lg); background: var(--bg-surface);">
        
        <!-- Messages Area -->
        <div id="chat-box" style="height: 400px; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; background: var(--bg-main); scroll-behavior: smooth;">
            <!-- Messages will be loaded here via JS -->
        </div>
        
        <!-- Deal Handshake Bar -->
        <div id="deal-handshake-bar" style="display:none; border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); padding: 0.9rem 1.25rem; background: var(--bg-surface);">
            <!-- Content injected by JS based on deal status -->
        </div>
        
        <!-- Action area -->
        <?php if ($productId > 0 && $currentUserId !== $sellerId): ?>
            <!-- Buyer's context: showing order proposal button -->
            <div class="purchase-cta-bar p-3 border-t flex justify-between items-center" style="background: var(--bg-surface); border-color: var(--border-light); opacity: 0.95;">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--primary); border: 1px solid var(--border-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-main mb-0" style="line-height: 1.2;">Ready to buy?</h4>
                        <p class="text-xs text-muted mb-0">Send a formal purchase request to the seller.</p>
                    </div>
                </div>
                <form action="api_messages.php" method="POST" class="m-0">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="propose">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <button type="button" class="btn btn-primary btn-sm shadow-md font-bold uppercase tracking-wider hover-scale" 
                            style="font-size: 0.7rem; padding: 0.5rem 1.25rem; border-radius: var(--radius-lg); letter-spacing: 0.05em;" 
                            onclick="proposeOrder()">
                        Propose Order
                    </button>
                </form>
            </div>
        <?php elseif ($productId === 0): ?>
            <!-- Support / Direct Message context -->
            <div class="p-3 border-t flex justify-between items-center" style="background: var(--bg-surface); border-color: var(--border-light); opacity: 0.95;">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--secondary); border: 1px solid var(--border-light);">
                        <?php if ($isSupport): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($isSupport): ?>
                        <h4 class="text-sm font-bold text-main mb-0" style="line-height: 1.2;">CampusMarket Support</h4>
                        <p class="text-xs text-muted mb-0">Our team usually responds within 24 hours.</p>
                        <?php else: ?>
                        <h4 class="text-sm font-bold text-main mb-0" style="line-height: 1.2;">Direct Message</h4>
                        <p class="text-xs text-muted mb-0">Private conversation with @<?= htmlspecialchars($otherUser['username']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Input Area -->
        <div style="background: var(--bg-surface); border-top: 1px solid var(--border-light); padding: 0.75rem;">
            <form id="chat-form" class="flex gap-3 relative m-0">
                <input type="text" id="chat-input" class="premium-input" style="background: var(--bg-surface); color: var(--text-main); padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light); font-size: 1rem; flex-grow: 1;" placeholder="Type your message..." required autocomplete="off">
                <button type="submit" class="btn btn-primary hover-scale shadow-md" style="border-radius: var(--radius-lg); width: 54px; height: 54px; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" title="Send">
                    <svg style="width: 24px; height: 24px; transform: translateX(2px);" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom scrollbar for chat */
#chat-box::-webkit-scrollbar { width: 6px; }
#chat-box::-webkit-scrollbar-track { background: transparent; }
#chat-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
#chat-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<script>
function goBackOrInbox() {
    if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
        const ref = document.referrer;
        if (ref.includes('login.php') || ref.includes('register.php') || ref.includes('messages.php')) {
            window.location.href = 'inbox.php';
        } else {
            window.history.back();
        }
    } else {
        window.location.href = 'inbox.php';
    }
}

const productId = <?= $productId ?>;
const otherUserId = <?= $otherUserId ?>;
const chatBox = document.getElementById('chat-box');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
let loadingDiv = document.getElementById('chat-loading');
const realtimeRoom = `chat:${productId}:${[<?= $currentUserId ?>, otherUserId].sort((a, b) => a - b).join(':')}`;
let realtimeChannel = null;
let pollIntervalId = null;

function fetchMessages() {
    const cacheBuster = Date.now();
    fetch(`api_messages.php?action=fetch&product_id=${productId}&other_user_id=${otherUserId}&_=${cacheBuster}`, {
        cache: 'no-store'
    })
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
            } else {
                console.warn('API error:', data.error);
            }
        })
        .catch(err => {
            console.warn('Failed to fetch messages:', err);
            // Even if fetch fails, if we have an optimistic message, it might be stuck.
            // But we can't easily distinguish which one it is here without a ID.
        });
}

function renderMessages(messages) {
    if (loadingDiv) {
        loadingDiv.remove();
        loadingDiv = null;
    }
    
    // Remember if we were at the bottom to auto-scroll
    const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;

    chatBox.innerHTML = '';
    
    if (messages.length === 0) {
        chatBox.innerHTML = `
            <div class="text-center text-muted my-auto flex flex-col items-center justify-center opacity-50">
                <div class="text-5xl mb-2">👋</div>
                <p>Say hello to start the conversation!</p>
            </div>
        `;
        return;
    }

    let lastDate = null;

    messages.forEach(msg => {
        // Simple date group check
        const msgDate = new Date(msg.created_at).toLocaleDateString();
        if (msgDate !== lastDate) {
            const dateDiv = document.createElement('div');
            dateDiv.style.textAlign = 'center';
            dateDiv.style.margin = '1rem 0';
            dateDiv.innerHTML = `<span style="background: var(--bg-surface); color: var(--text-muted); padding: 0.2rem 0.8rem; border-radius: var(--radius-md); font-size: 0.75rem; font-weight: bold;">${msgDate}</span>`;
            chatBox.appendChild(dateDiv);
            lastDate = msgDate;
        }

        const msgDiv = document.createElement('div');
        msgDiv.style.maxWidth = '75%';
        msgDiv.style.padding = '0.875rem 1.25rem';
        msgDiv.style.boxShadow = 'none';
        msgDiv.style.position = 'relative';
        msgDiv.style.lineHeight = '1.6';
        msgDiv.style.wordWrap = 'break-word';
        
        if (msg.is_mine) {
            msgDiv.style.alignSelf = 'flex-end';
            msgDiv.style.background = 'var(--primary)';
            msgDiv.style.color = '#ffffff';
            msgDiv.style.borderRadius = '10px';
            msgDiv.style.marginLeft = 'auto';
        } else {
            msgDiv.style.alignSelf = 'flex-start';
            msgDiv.style.background = 'var(--bg-surface)';
            msgDiv.style.color = 'var(--text-main)';
            msgDiv.style.border = '1px solid var(--border-light)';
            msgDiv.style.borderRadius = '10px';
            msgDiv.style.marginRight = 'auto';
        }
        
        let timeStr = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        msgDiv.innerHTML = `
            <div style="font-size: 1rem;">${msg.body}</div>
            <div style="font-size: 0.65rem; text-align: right; margin-top: 4px; opacity: ${msg.is_mine ? '0.8' : '0.5'};">
                ${timeStr}
            </div>
        `;
        chatBox.appendChild(msgDiv);
    });
    
    if (isScrolledToBottom) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    
    // Add optimistic message
    const msgDiv = document.createElement('div');
    msgDiv.style.maxWidth = '75%';
    msgDiv.style.padding = '0.875rem 1.25rem';
    msgDiv.style.alignSelf = 'flex-end';
    msgDiv.style.background = 'var(--primary)';
    msgDiv.style.color = '#ffffff';
    msgDiv.style.borderRadius = '10px';
    msgDiv.style.marginLeft = 'auto';
    msgDiv.style.boxShadow = 'none';
    msgDiv.style.lineHeight = '1.6';
    msgDiv.style.wordWrap = 'break-word';
    msgDiv.style.opacity = '0.7'; // Indicate sending
    msgDiv.innerHTML = `<div style="font-size: 1rem;">${text}</div><div style="font-size: 0.65rem; text-align: right; margin-top: 4px;">Sending...</div>`;
    chatBox.appendChild(msgDiv);
    chatBox.scrollTop = chatBox.scrollHeight;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('product_id', productId);
    formData.append('receiver_id', otherUserId);
    formData.append('body', text);
    formData.append('csrf_token', window.__csrfToken || '');
    
    chatInput.value = '';
    chatInput.focus();
    
    fetch('api_messages.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (realtimeChannel) {
                realtimeChannel.send({
                    type: 'broadcast',
                    event: 'new_message',
                    payload: {
                        product_id: productId,
                        sender_id: <?= $currentUserId ?>,
                        receiver_id: otherUserId,
                        sent_at: new Date().toISOString()
                    }
                });
            }
            fetchMessages();
        } else {
            alert('Error sending message: ' + data.error);
            msgDiv.remove();
            chatInput.value = text;
        }
    })
    .catch(err => {
        console.error('Send failed:', err);
        alert('Failed to send message. Please try again.');
        msgDiv.remove();
        chatInput.value = text;
    });
});

function proposeOrder() {
    fetch('api_messages.php?action=get_propose&product_id=' + productId)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            chatInput.value = data.proposed_text;
            chatInput.focus();
        } else {
            alert('Error: ' + data.error);
        }
    });
}

// ─── Deal Handshake Logic ──────────────────────────────
const handshakeBar = document.getElementById('deal-handshake-bar');

function checkDealStatus() {
    fetch(`api_messages.php?action=check_deal_status&product_id=${productId}&other_user_id=${otherUserId}&_=${Date.now()}`, {
        cache: 'no-store'
    })
        .then(res => res.json())
        .then(data => {
            if (data.show_handshake) {
                renderHandshakeBar(data.deal);
                handshakeBar.style.display = 'block';
            } else {
                handshakeBar.style.display = 'none';
            }
        })
        .catch(() => {
            handshakeBar.style.display = 'none';
        });
}

window.currentDeal = null;
window.handshakeCollapsed = false;
window.handshakeTimeout = null;

function collapseHandshake() {
    window.handshakeCollapsed = true;
    renderHandshakeBar(window.currentDeal);
    
    if (window.handshakeTimeout) clearTimeout(window.handshakeTimeout);
    window.handshakeTimeout = setTimeout(() => {
        window.handshakeCollapsed = false;
        renderHandshakeBar(window.currentDeal);
    }, 60000);
}

function expandHandshake() {
    window.handshakeCollapsed = false;
    if (window.handshakeTimeout) clearTimeout(window.handshakeTimeout);
    renderHandshakeBar(window.currentDeal);
}

function renderHandshakeBar(deal) {
    window.currentDeal = deal;
    const status = deal.status;
    const isSeller = deal.is_seller;
    const buyerName = deal.buyer_username || 'Buyer';
    const productTitle = deal.product_title || 'this item';

    let html = '';
    let borderStyle = '';

    if (window.handshakeCollapsed) {
        handshakeBar.setAttribute('style', `display:block; position:absolute; right: 0; top: 1rem; padding: 0.5rem 1rem; background: var(--primary); color: white; border-radius: 20px 0 0 20px; cursor: pointer; z-index: 100; box-shadow: -2px 2px 10px rgba(0,0,0,0.1); transition: all 0.2s ease;`);
        handshakeBar.innerHTML = `
            <div onclick="expandHandshake()" style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span style="font-size: 0.8rem; font-weight: 600;">Deal Info</span>
            </div>
        `;
        return;
    }

    if (status === 'choose_product') {
        borderStyle = 'border-left: 4px solid var(--primary); background: var(--bg-surface); opacity: 0.95;';
        html = `
            <div id="choose-product-initial" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--primary); border: 1px solid var(--border-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); line-height: 1.2;">Did a transaction happen?</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">Select the item to confirm the deal and delist it.</div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button onclick="openProductSelector()" class="btn btn-primary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem;">Yes</button>
                    <button onclick="collapseHandshake()" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem; opacity: 0.7;">No</button>
                </div>
            </div>
            <div id="choose-product-selector" style="display: none; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; width: 100%;">
                <div style="flex-grow: 1;">
                    <select id="deal-product-select" class="premium-input" style="width: 100%; padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); background: var(--bg-surface); color: var(--text-main);">
                        <option value="">Loading items...</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button onclick="submitChosenProduct()" class="btn btn-primary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem;">Confirm</button>
                    <button onclick="cancelChooseProduct()" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem;">Cancel</button>
                </div>
            </div>
        `;
    } else if (status === 'pending') {
        borderStyle = 'border-left: 4px solid var(--primary); background: var(--bg-surface); opacity: 0.95;';
        html = `
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--primary); border: 1px solid var(--border-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); line-height: 1.2;">Did this deal happen?</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">Confirming marks this item as sold and removes it from listings.</div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button onclick="confirmDeal(${deal.product_id || 'null'})" class="btn btn-primary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem;">Yes, deal is done!</button>
                    <button onclick="collapseHandshake()" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem; opacity: 0.7;">Not yet</button>
                </div>
            </div>
        `;
    } else if (status === 'buyer_confirmed' && !isSeller) {
        borderStyle = 'border-left: 4px solid var(--text-light); background: var(--bg-surface);';
        html = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border-light);">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); line-height: 1.2;">Awaiting Confirmation</div>
                    <div style="font-weight: 500; font-size: 0.75rem; color: var(--text-muted);">You confirmed this deal. Waiting for the seller to confirm...</div>
                </div>
            </div>
        `;
    } else if (status === 'buyer_confirmed' && isSeller) {
        borderStyle = 'border-left: 4px solid var(--secondary); background: var(--bg-surface);';
        html = `
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--secondary); border: 1px solid var(--border-light);">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); line-height: 1.2;">@${buyerName} says the deal is done!</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">Confirm below to mark &ldquo;${productTitle}&rdquo; as sold.</div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button onclick="confirmDeal(${deal.product_id || 'null'})" class="btn btn-primary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem; background: var(--secondary); border-color: var(--secondary);">Confirm & Delist Item</button>
                    <button onclick="collapseHandshake()" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; border-radius: var(--radius-lg); padding: 0.4rem 1rem; opacity: 0.7;">Not done yet</button>
                </div>
            </div>
        `;
    } else if (status === 'completed') {
        borderStyle = 'border-left: 4px solid var(--secondary); background: var(--bg-surface);';
        html = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="flex items-center justify-center rounded-lg w-10 h-10 shadow-sm" style="background: var(--bg-surface); color: var(--secondary); border: 1px solid var(--border-light);">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--secondary); line-height: 1.2;">Deal confirmed!</div>
                    <div style="font-weight: 500; font-size: 0.75rem; color: var(--text-muted);">This item has been marked as sold.</div>
                </div>
            </div>
        `;
    } else {
        handshakeBar.style.display = 'none';
        return;
    }

    handshakeBar.setAttribute('style', `display:block; border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); padding: 0.9rem 1.25rem; ${borderStyle}`);
    handshakeBar.innerHTML = html;
}

function openProductSelector() {
    document.getElementById('choose-product-initial').style.display = 'none';
    document.getElementById('choose-product-selector').style.display = 'flex';
    
    const select = document.getElementById('deal-product-select');
    
    fetch('api_messages.php?action=get_active_products&other_user_id=' + otherUserId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                let options = '<option value="">Select an item...</option>';
                data.products.forEach(p => {
                    options += `<option value="${p.id}" data-is-mine="${p.is_mine}">${p.title} - ${p.price}</option>`;
                });
                select.innerHTML = options;
            } else {
                select.innerHTML = '<option value="">No active items found</option>';
            }
        })
        .catch(err => {
            select.innerHTML = '<option value="">Error loading items</option>';
        });
}

function cancelChooseProduct() {
    document.getElementById('choose-product-selector').style.display = 'none';
    document.getElementById('choose-product-initial').style.display = 'flex';
}

function submitChosenProduct() {
    const select = document.getElementById('deal-product-select');
    if (select.selectedIndex <= 0) return;
    
    const option = select.options[select.selectedIndex];
    const prodId = option.value;
    const isSeller = option.getAttribute('data-is-mine') === 'true';
    
    confirmDeal(prodId, isSeller);
}

function confirmDeal(prodId = null, isSellerOverride = null) {
    const finalProductId = prodId || productId || (window.currentDeal && window.currentDeal.product_id) || 0;
    if (!finalProductId) return;

    const isSeller = isSellerOverride !== null ? isSellerOverride : (window.currentDeal && window.currentDeal.is_seller);

    if (isSeller) {
        if (!confirm("Are you sure you want to mark this item as sold? It will be delisted immediately.")) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('action', 'confirm_deal');
    formData.append('product_id', finalProductId);
    formData.append('other_user_id', otherUserId);
    formData.append('csrf_token', window.__csrfToken || '');

    fetch('api_messages.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                checkDealStatus();
            } else {
                alert('Error: ' + (data.error || 'Unknown'));
            }
        });
}

// Initial fetch
fetchMessages();
checkDealStatus();

function startPolling(intervalMs = 3000) {
    if (pollIntervalId) {
        clearInterval(pollIntervalId);
    }
    pollIntervalId = setInterval(fetchMessages, intervalMs);
}

function initRealtime() {
    if (!window.CampusMarketSupabase) {
        startPolling(3000);
        return;
    }

    realtimeChannel = window.CampusMarketSupabase.channel(realtimeRoom);

    realtimeChannel.on('broadcast', { event: 'new_message' }, (payload) => {
        const msg = payload && payload.payload ? payload.payload : null;
        if (!msg || Number(msg.product_id) !== Number(productId)) return;
        if (![Number(<?= $currentUserId ?>), Number(otherUserId)].includes(Number(msg.sender_id))) return;
        fetchMessages();
    });

    realtimeChannel.subscribe((status) => {
        if (status === 'SUBSCRIBED') {
            // Keep a low-frequency fallback in case realtime delivery is missed.
            startPolling(15000);
            return;
        }
        if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT' || status === 'CLOSED') {
            startPolling(3000);
        }
    });
}

initRealtime();

window.addEventListener('beforeunload', () => {
    if (realtimeChannel && window.CampusMarketSupabase) {
        window.CampusMarketSupabase.removeChannel(realtimeChannel);
    }
    if (pollIntervalId) {
        clearInterval(pollIntervalId);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
