<?php
// pages/messages.php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __('inbox.messages_tab');
requireLogin();

$productId = (int)($_GET['product_id'] ?? 0);
$otherUserId = (int)($_GET['other_user_id'] ?? $_GET['to'] ?? 0);
$currentUserId = currentUserId();

if (!$otherUserId) {
    setFlash('error', 'Invalid conversation context.');
    redirect(BASE_URL . '/pages/inbox.php');
}

// Special Case: Support Chat (product_id = 0)
// Allowed for any valid users (will be treated as Support if either is Admin, else standard Direct Message)

if ($productId > 0) {
    // Fetch context info
    $stmt = $pdo->prepare("SELECT p.title, p.price, p.discount_percent, p.price_currency, p.listing_type, p.pricing_model, i.image_path FROM products p LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE WHERE p.id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT username, avatar, role, last_seen_at FROM users WHERE id = :id");
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
    $stmt = $pdo->prepare("SELECT username, avatar, role, last_seen_at FROM users WHERE id = :id");
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

// Compute initial presence status from last_seen_at
function computePresence(?string $lastSeenAt): array {
    if (!$lastSeenAt) {
        return ['status' => 'offline', 'label' => null];
    }
    $diffSeconds = time() - strtotime($lastSeenAt);
    if ($diffSeconds <= 120) {
        return ['status' => 'online',  'label' => null];
    } elseif ($diffSeconds <= 3600) {
        $mins = (int) ceil($diffSeconds / 60);
        return ['status' => 'recently', 'label' => $mins];
    } else {
        return ['status' => 'offline', 'label' => null];
    }
}
$otherPresence = computePresence($otherUser['last_seen_at'] ?? null);
$isOtherAdmin = (($otherUser['role'] ?? '') === 'admin');
$appLogoUrl = rtrim(BASE_URL, '/') . '/public/images/logo.png';
$otherAvatarUrl = $isOtherAdmin
    ? $appLogoUrl
    : avatarUrl($otherUser['avatar'] ?? null);
$bodyClass = 'page-chat';

require_once __DIR__ . '/../includes/header.php';

$presenceColor = match($otherPresence['status']) {
    'online'   => '#10b981',
    'recently' => '#f59e0b',
    default    => '#94a3b8',
};
$presenceText = match($otherPresence['status']) {
    'online'   => __('chat.online_now'),
    'recently' => ($otherPresence['label'] === 1
        ? __('chat.active_1min_ago')
        : __('chat.active_mins_ago', ['mins' => $otherPresence['label']])),
    default    => __('chat.offline'),
};
?>

<div class="chat-page-shell main-content">
    <div class="chat-thread-panel glass-panel">
        <header class="chat-composer-header">
            <button type="button" onclick="goBackOrInbox()" class="chat-back-btn" title="<?= htmlspecialchars(__('nav.mobile_menu_back')) ?>" aria-label="<?= htmlspecialchars(__('nav.mobile_menu_back')) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <?php if ($isOtherAdmin): ?>
            <div class="chat-peer">
                <img src="<?= htmlspecialchars($otherAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="chat-peer-avatar chat-peer-avatar--logo">
                <div class="chat-peer-meta">
                    <span class="chat-peer-name">@<?= htmlspecialchars($otherUser['username']) ?></span>
                    <p id="user-presence" class="chat-peer-status mb-0">
                        <span id="presence-dot" class="chat-presence-dot" style="background:<?= $presenceColor ?>;"></span>
                        <span id="presence-text"><?= htmlspecialchars($presenceText) ?></span>
                    </p>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= BASE_URL ?>pages/profile.php?id=<?= $otherUserId ?>" class="chat-peer">
                <img src="<?= htmlspecialchars($otherAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="chat-peer-avatar">
                <div class="chat-peer-meta">
                    <span class="chat-peer-name">@<?= htmlspecialchars($otherUser['username']) ?></span>
                    <p id="user-presence" class="chat-peer-status mb-0">
                        <span id="presence-dot" class="chat-presence-dot" style="background:<?= $presenceColor ?>;"></span>
                        <span id="presence-text"><?= htmlspecialchars($presenceText) ?></span>
                    </p>
                </div>
            </a>
            <?php endif; ?>

            <div class="chat-header-actions">
                <button id="clear-chat-btn" type="button" class="chat-header-btn chat-header-btn--danger" onclick="clearChat()">
                    <?= __('chat.clear_chat') ?>
                </button>
            </div>
        </header>

        <?php if ($productId > 0): ?>
        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $productId ?>" class="chat-product-strip">
            <img src="<?= getProductImage($product['image_path'] ?? null) ?>" alt="" class="chat-product-strip__thumb">
            <div class="chat-product-strip__body">
                <span class="chat-product-strip__label"><?= __('chat.regarding_item') ?></span>
                <span class="chat-product-strip__title"><?= htmlspecialchars($product['title']) ?></span>
                <span class="chat-product-strip__price"><?= renderProductPrice($product) ?></span>
            </div>
            <svg class="chat-product-strip__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        <?php elseif ($product['title'] === 'CampusMarket Support'): ?>
        <div class="chat-context-strip chat-context-strip--support">
            <span><?= __('chat.official_help') ?></span>
        </div>
        <?php endif; ?>

        <!-- Compact Deal Banner -->
        <div id="deal-handshake-bar" class="chat-deal-banner-wrapper" style="display:none;" hidden></div>

        <div id="chat-box" class="chat-messages" aria-live="polite">
            <!-- Messages loaded via JS -->
        </div>

        <?php if ($productId > 0 && $currentUserId !== $sellerId): ?>
            <?php $isServiceChat = ($product['listing_type'] ?? 'product') === 'service'; ?>
            <div class="chat-action-bar purchase-cta-bar" <?= $isServiceChat ? 'style="border-left: 4px solid var(--service);"' : '' ?>>
                <div class="chat-action-bar__copy">
                    <strong><?= $isServiceChat ? '📅 Book This Service' : __('chat.ready_to_buy') ?></strong>
                    <span><?= $isServiceChat ? 'Schedule and agree on a service time' : __('chat.send_purchase_request') ?></span>
                </div>
                <form action="api_messages.php" method="POST" class="m-0">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="propose">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <button type="button" class="btn <?= $isServiceChat ? 'btn--service' : 'btn-primary' ?> btn-sm" onclick="<?= $isServiceChat ? 'checkDealStatus(true)' : 'proposeOrder()' ?>">
                        <?= $isServiceChat ? '✨ Book Service' : __('chat.propose_order') ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div id="reply-preview" class="chat-reply-preview" hidden>
            <div class="chat-reply-preview__copy">
                <span class="chat-reply-preview__label">Replying to</span>
                <span id="reply-preview-text" class="chat-reply-preview__text"></span>
            </div>
            <button type="button" id="cancel-reply-btn" class="chat-reply-preview__cancel" aria-label="Cancel reply">&times;</button>
        </div>

        <div class="chat-input-bar">
            <form id="chat-form" class="chat-input-form m-0">
                <input type="text" id="chat-input" class="chat-input-field premium-input" placeholder="<?= htmlspecialchars(__('chat.placeholder')) ?>" required autocomplete="off" maxlength="250">
                <button type="submit" class="chat-send-btn btn btn-primary" title="<?= htmlspecialchars(__('chat.send')) ?>" aria-label="<?= htmlspecialchars(__('chat.send')) ?>">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Deal Handshake Bottom Sheet Drawer Modal -->
<div id="deal-sheet-backdrop" class="deal-sheet-backdrop" style="display:none;" hidden onclick="closeDealSheet(event)">
    <div class="deal-sheet-card" onclick="event.stopPropagation()">
        <div class="deal-sheet-handle-bar"></div>
        <div class="deal-sheet-header">
            <div class="deal-sheet-title-wrap">
                <div class="deal-sheet-icon-wrap" id="deal-sheet-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="deal-sheet-title" id="deal-sheet-title"><?= __('deal.deal_info') ?></h3>
                    <p class="deal-sheet-subtitle" id="deal-sheet-subtitle"><?= __('chat.orders_deal_explainer') ?></p>
                </div>
            </div>
            <button type="button" class="deal-sheet-close-btn" onclick="closeDealSheet()" aria-label="Close">&times;</button>
        </div>
        <div class="deal-sheet-body" id="deal-sheet-body">
            <!-- Dynamic deal sheet content populated via JS -->
        </div>
    </div>
</div>

<style>
#chat-box::-webkit-scrollbar { width: 6px; }
#chat-box::-webkit-scrollbar-track { background: transparent; }
#chat-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
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
const dealExplainerHtml = <?= json_encode('<p class="chat-deal-bar__hint text-muted small mb-3">' . htmlspecialchars(__('chat.orders_deal_explainer'), ENT_QUOTES, 'UTF-8') . '</p>') ?>;
const isAdmin = <?= isAdmin() ? 'true' : 'false' ?>;
const chatBox = document.getElementById('chat-box');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
let loadingDiv = document.getElementById('chat-loading');
const realtimeRoom = `chat:${productId}:${[<?= $currentUserId ?>, otherUserId].sort((a, b) => a - b).join(':')}`;
let realtimeChannel = null;
let pollIntervalId = null;
let translationConfigured = <?= getTranslationService()->isConfigured() ? 'true' : 'false' ?>;
const translatedStorageKey = `cm_translated_${productId}_${otherUserId}`;
let translatedMessageIds = new Set();
let selectedReply = null;

try {
    const storedTranslated = sessionStorage.getItem(translatedStorageKey);
    if (storedTranslated) {
        translatedMessageIds = new Set(JSON.parse(storedTranslated));
    }
} catch (e) {
    translatedMessageIds = new Set();
}

function saveTranslatedIds() {
    try {
        sessionStorage.setItem(translatedStorageKey, JSON.stringify([...translatedMessageIds]));
    } catch (e) {}
}

function buildTranslatedBodyHtml(translatedText, originalText, sourceLang) {
    const langName = CampusMarketI18n.getLangName(sourceLang);
    const translatedLabel = __('chat.translated_from', { lang: langName });
    const viewOriginalText = __('chat.view_original');

    return `
        <div class="message-text-content translated-text">${translatedText}</div>
        <div class="message-text-content original-text" style="display: none; opacity: 0.85; font-style: italic;">${originalText}</div>
        <div class="translation-meta" style="font-size: 0.7rem; opacity: 0.7; margin-top: 6px; display: flex; align-items: center; gap: 6px; border-top: 1px dashed var(--border-light); padding-top: 4px;">
            <span>🌐 ${translatedLabel}</span>
            <button type="button" class="btn-toggle-translation" onclick="toggleOriginalText(this)" style="background: none; border: none; padding: 0; margin: 0; color: var(--primary); font-size: 0.7rem; cursor: pointer; text-decoration: underline; font-weight: bold;">${viewOriginalText}</button>
        </div>
    `;
}

function buildIncomingBodyHtml(msg) {
    const showTranslated = translatedMessageIds.has(msg.id) && msg.cached_translation;
    if (showTranslated) {
        return buildTranslatedBodyHtml(msg.cached_translation, msg.original_text, msg.cached_source_lang);
    }

    const translateBtn = translationConfigured
        ? `<div class="translation-actions" style="margin-top: 6px;">
            <button type="button" class="btn-translate-msg" onclick="translateSingleMessage(${msg.id}, this)" style="background: none; border: none; padding: 0; margin: 0; color: var(--primary); font-size: 0.72rem; cursor: pointer; text-decoration: underline; font-weight: 600;">${__('chat.translate')}</button>
        </div>`
        : '';

    return `
        <div class="message-text-content">${msg.body}</div>
        ${translateBtn}
    `;
}

window.translateSingleMessage = function(messageId, btn) {
    if (!translationConfigured) return;

    const previousLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = __('chat.translating');

    const formData = new FormData();
    formData.append('action', 'translate_message');
    formData.append('message_id', messageId);
    formData.append('product_id', productId);
    formData.append('other_user_id', otherUserId);
    formData.append('csrf_token', window.__csrfToken || '');

    fetch('api_messages.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            btn.disabled = false;
            btn.textContent = previousLabel;
            alert(data.error || __('chat.translate_failed'));
            return;
        }

        if (data.already_same_language) {
            btn.remove();
            return;
        }

        translatedMessageIds.add(messageId);
        saveTranslatedIds();

        const bubble = chatBox.querySelector(`[data-message-id="${messageId}"]`);
        const bodyWrap = bubble ? bubble.querySelector('.message-body-wrap') : null;
        if (bodyWrap) {
            bodyWrap.innerHTML = buildTranslatedBodyHtml(
                data.translated_text,
                data.original_text,
                data.source_lang
            );
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = previousLabel;
        alert(__('chat.translate_failed'));
    });
};

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
                if (typeof data.translation_configured === 'boolean') {
                    translationConfigured = data.translation_configured;
                }
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

function parseMessageTimestamp(iso) {
    if (!iso) {
        return new Date(NaN);
    }

    const trimmed = String(iso).trim();
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(trimmed)) {
        return new Date(trimmed.replace(' ', 'T') + 'Z');
    }

    return new Date(trimmed);
}

function formatMessageTime(iso) {
    const date = parseMessageTimestamp(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function buildMessageBubbleHtml(msg, options = {}) {
    const isMine = !!msg.is_mine;
    const timeStr = options.sending ? __('chat.sending') : formatMessageTime(msg.created_at);
    const bodyHtml = isMine
        ? `<div class="message-body-wrap"><div class="message-text-content">${msg.body}</div></div>`
        : `<div class="message-body-wrap">${buildIncomingBodyHtml(msg)}</div>`;
    const replyHtml = msg.reply_to_message_id && msg.reply_body
        ? `<div class="message-reply-quote"><span class="message-reply-quote__label">Replying to ${msg.reply_sender_name || 'message'}</span><span class="message-reply-quote__text">${msg.reply_body}</span></div>`
        : '';

    return `
        ${replyHtml}
        ${bodyHtml}
        <div class="message-time">${timeStr}</div>
    `;
}

function buildMessageActionsHtml(msg, options = {}) {
    if (options.sending || !msg.id) return '';
    const isMine = !!msg.is_mine;
    const canDelete = isMine;
    const canReply = true;

    let html = '<div class="message-actions-bar">';
    if (canReply) {
        html += `<button type="button" class="btn-action-msg btn-reply-msg" onclick="selectReplyMessage(${msg.id})" title="Reply" aria-label="Reply">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
        </button>`;
    }
    if (canDelete) {
        html += `<button type="button" class="btn-action-msg btn-delete-msg" onclick="deleteMessage(${msg.id})" title="${__('chat.delete_msg')}" aria-label="${__('chat.delete_msg')}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>`;
    }
    html += '</div>';
    return html;
}

function attachSwipeToReply(row, bubble, messageId) {
    let startX = 0;
    let startY = 0;
    let isSwiping = false;
    let isHorizontal = null;

    bubble.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 1) return;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        isSwiping = true;
        isHorizontal = null;
        bubble.style.transition = 'none';
    }, { passive: true });

    bubble.addEventListener('touchmove', (e) => {
        if (!isSwiping || e.touches.length !== 1) return;
        const deltaX = e.touches[0].clientX - startX;
        const deltaY = e.touches[0].clientY - startY;

        if (isHorizontal === null) {
            if (Math.abs(deltaX) > 8 || Math.abs(deltaY) > 8) {
                isHorizontal = Math.abs(deltaX) > Math.abs(deltaY);
            }
        }

        if (!isHorizontal) return;

        // Swiping horizontally to the right
        if (deltaX > 0 && deltaX < 140) {
            const drag = Math.min(deltaX * 0.75, 65);
            bubble.style.transform = `translateX(${drag}px)`;
            if (drag > 38) {
                row.classList.add('swiping-ready');
            } else {
                row.classList.remove('swiping-ready');
            }
        }
    }, { passive: true });

    const endSwipe = () => {
        if (!isSwiping) return;
        isSwiping = false;
        bubble.style.transition = 'transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        bubble.style.transform = '';

        if (row.classList.contains('swiping-ready')) {
            row.classList.remove('swiping-ready');
            if (navigator.vibrate) {
                try { navigator.vibrate(15); } catch(e) {}
            }
            selectReplyMessage(messageId);
        }
    };

    bubble.addEventListener('touchend', endSwipe, { passive: true });
    bubble.addEventListener('touchcancel', endSwipe, { passive: true });
}

function createMessageRow(msg, options = {}) {
    const isMine = !!msg.is_mine;
    const row = document.createElement('div');
    row.className = 'message-row ' + (isMine ? 'message-row--out' : 'message-row--in');

    const bubble = document.createElement('div');
    bubble.className = 'message-bubble ' + (isMine ? 'message-bubble--out' : 'message-bubble--in');
    if (options.sending) {
        bubble.classList.add('message-bubble--pending');
    }
    if (msg.id) {
        row.dataset.messageId = String(msg.id);
        bubble.dataset.messageId = String(msg.id);
    }
    bubble.dataset.replyText = msg.body || '';
    bubble.innerHTML = buildMessageBubbleHtml(msg, options);

    const actionsHtml = buildMessageActionsHtml(msg, options);

    if (isMine) {
        row.innerHTML = actionsHtml;
        row.appendChild(bubble);
    } else {
        row.appendChild(bubble);
        if (actionsHtml) {
            const t = document.createElement('div');
            t.innerHTML = actionsHtml;
            if (t.firstElementChild) {
                row.appendChild(t.firstElementChild);
            }
        }
    }

    if (msg.id && !options.sending) {
        attachSwipeToReply(row, bubble, msg.id);
    }

    return row;
}

window.selectReplyMessage = function(messageId) {
    const bubble = chatBox.querySelector(`[data-message-id="${messageId}"]`);
    if (!bubble) return;

    selectedReply = {
        id: Number(messageId),
        text: bubble.dataset.replyText || ''
    };
    document.getElementById('reply-preview-text').textContent = selectedReply.text;
    document.getElementById('reply-preview').hidden = false;
    chatInput.focus();
};

function clearReplySelection() {
    selectedReply = null;
    document.getElementById('reply-preview').hidden = true;
    document.getElementById('reply-preview-text').textContent = '';
}

document.getElementById('cancel-reply-btn').addEventListener('click', clearReplySelection);

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
            <div class="chat-empty-state">
                <div class="chat-empty-state__icon" aria-hidden="true">👋</div>
                <p>${__('chat.say_hello')}</p>
            </div>
        `;
        return;
    }

    let lastDate = null;

    messages.forEach(msg => {
        const msgDate = parseMessageTimestamp(msg.created_at).toLocaleDateString();
        if (msgDate !== lastDate) {
            const dateDiv = document.createElement('div');
            dateDiv.className = 'chat-date-divider';
            dateDiv.innerHTML = `<span class="chat-date-divider__pill">${msgDate}</span>`;
            chatBox.appendChild(dateDiv);
            lastDate = msgDate;
        }

        chatBox.appendChild(createMessageRow(msg));
    });
    
    if (isScrolledToBottom) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
}

window.toggleOriginalText = function(btn) {
    const bubble = btn.closest('.message-bubble');
    const translatedEl = bubble.querySelector('.translated-text');
    const originalEl = bubble.querySelector('.original-text');
    
    if (translatedEl.style.display === 'none') {
        translatedEl.style.display = 'block';
        originalEl.style.display = 'none';
        btn.textContent = __('chat.view_original');
    } else {
        translatedEl.style.display = 'none';
        originalEl.style.display = 'block';
        btn.textContent = __('chat.hide_original');
    }
};

chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    
    // Add optimistic message
    const optimisticMsg = {
        id: null,
        is_mine: true,
        body: text,
        reply_to_message_id: selectedReply ? selectedReply.id : null,
        reply_body: selectedReply ? selectedReply.text : null,
        reply_sender_name: selectedReply ? 'message' : null,
        created_at: new Date().toISOString()
    };
    chatBox.appendChild(createMessageRow(optimisticMsg, { sending: true }));
    const pendingRow = chatBox.lastElementChild;
    chatBox.scrollTop = chatBox.scrollHeight;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('product_id', productId);
    formData.append('receiver_id', otherUserId);
    formData.append('body', text);
    if (selectedReply) {
        formData.append('reply_to_message_id', selectedReply.id);
    }
    formData.append('csrf_token', window.__csrfToken || '');
    
    chatInput.value = '';
    clearReplySelection();
    chatInput.focus();
    
    fetch('api_messages.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof posthog !== 'undefined') posthog.capture('message_sent');
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
            alert(__('chat.error_send') + data.error);
            pendingRow.remove();
            chatInput.value = text;
        }
    })
    .catch(err => {
        console.error('Send failed:', err);
        alert(__('chat.failed_send'));
        pendingRow.remove();
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

function deleteMessage(msgId) {
    if (!confirm(__('chat.confirm_delete_msg'))) return;
    const formData = new FormData();
    formData.append('action', 'delete_message');
    formData.append('message_id', msgId);
    formData.append('csrf_token', window.__csrfToken || '');

    fetch('api_messages.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
            } else {
                alert(data.error || 'Error deleting message');
            }
        })
        .catch(err => console.error('Delete failed:', err));
}

function clearChat() {
    if (!confirm(__('chat.confirm_clear_chat'))) return;
    const formData = new FormData();
    formData.append('action', 'clear_chat');
    formData.append('product_id', productId);
    formData.append('other_user_id', otherUserId);
    formData.append('csrf_token', window.__csrfToken || '');

    fetch('api_messages.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
            } else {
                alert(data.error || 'Error clearing chat');
            }
        })
        .catch(err => console.error('Clear chat failed:', err));
}

// ─── Deal Handshake Logic (Banner & Bottom Sheet) ──────────
const handshakeBar = document.getElementById('deal-handshake-bar');
const dealSheetBackdrop = document.getElementById('deal-sheet-backdrop');
const dealSheetBody = document.getElementById('deal-sheet-body');

window.currentDeal = null;

function openDealSheet() {
    if (!dealSheetBackdrop) return;
    dealSheetBackdrop.hidden = false;
    dealSheetBackdrop.style.display = 'flex';
    requestAnimationFrame(() => {
        dealSheetBackdrop.classList.add('is-active');
    });
}

function closeDealSheet(event) {
    if (event && event.target && event.target.closest && event.target.closest('.deal-sheet-card') && !event.target.classList.contains('deal-sheet-close-btn')) {
        return;
    }
    if (!dealSheetBackdrop) return;
    dealSheetBackdrop.classList.remove('is-active');
    setTimeout(() => {
        dealSheetBackdrop.style.display = 'none';
        dealSheetBackdrop.hidden = true;
    }, 220);
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && dealSheetBackdrop && dealSheetBackdrop.classList.contains('is-active')) {
        closeDealSheet();
    }
});

function checkDealStatus(autoOpen = false) {
    fetch(`api_messages.php?action=check_deal_status&product_id=${productId}&other_user_id=${otherUserId}&_=${Date.now()}`, {
        cache: 'no-store'
    })
        .then(res => res.json())
        .then(data => {
            if (data.show_handshake) {
                renderHandshakeBar(data.deal);
                handshakeBar.hidden = false;
                handshakeBar.style.display = 'block';
                if (autoOpen) {
                    openDealSheet();
                }
            } else {
                handshakeBar.hidden = true;
                handshakeBar.style.display = 'none';
            }
        })
        .catch(() => {
            handshakeBar.style.display = 'none';
        });
}

function renderHandshakeBar(deal) {
    window.currentDeal = deal;
    const status = deal.status;
    const isSeller = deal.is_seller;
    const buyerName = deal.buyer_username || 'Buyer';
    const productTitle = deal.product_title || 'this item';
    const isService = deal.listing_type === 'service';

    let bannerHtml = '';
    let sheetHtml = '';

    if (status === 'choose_product') {
        bannerHtml = `
            <div class="chat-deal-banner chat-deal-banner--choose" onclick="openDealSheet()">
                <div class="chat-deal-banner__info">
                    <span class="chat-deal-banner__icon">🤝</span>
                    <div class="chat-deal-banner__texts">
                        <span class="chat-deal-banner__headline">${__('deal.did_transaction')}</span>
                        <span class="chat-deal-banner__sub">${__('deal.select_item')}</span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-xs chat-deal-banner__btn" onclick="event.stopPropagation(); openDealSheet(); openProductSelector();">
                    ${__('deal.select_item_prompt')}
                </button>
            </div>
        `;

        sheetHtml = `
            <div id="choose-product-initial" class="deal-sheet-flow">
                <p class="deal-sheet-explainer">${__('chat.orders_deal_explainer')}</p>
                <div class="deal-sheet-prompt-card">
                    <div class="deal-sheet-prompt-title">${__('deal.did_transaction')}</div>
                    <div class="deal-sheet-prompt-desc">${__('deal.select_item')}</div>
                </div>
                <div class="deal-sheet-actions">
                    <button type="button" onclick="openProductSelector()" class="btn btn-primary btn-md w-full">${__('deal.yes')} · ${__('deal.select_item_prompt')}</button>
                    <button type="button" onclick="closeDealSheet()" class="btn btn-secondary btn-md w-full">${__('deal.no')}</button>
                </div>
            </div>
            <div id="choose-product-selector" class="deal-sheet-flow" style="display: none;">
                <p class="deal-sheet-explainer">${__('deal.select_item')}</p>
                <div style="margin-bottom: 1rem;">
                    <select id="deal-product-select" class="premium-input" style="width: 100%; padding: 0.6rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); background: var(--bg-surface); color: var(--text-main);">
                        <option value="">${__('deal.loading_items')}</option>
                    </select>
                </div>
                <div class="deal-sheet-actions">
                    <button type="button" onclick="submitChosenProduct()" class="btn btn-primary btn-md w-full">${__('deal.confirm')}</button>
                    <button type="button" onclick="cancelChooseProduct()" class="btn btn-secondary btn-md w-full">${__('deal.cancel')}</button>
                </div>
            </div>
        `;
    } else if (status === 'pending') {
        let promptText = isService ? __('deal.want_to_book_service', {default: 'Want to book this service?'}) : __('deal.did_deal_happen');
        let promptSub = isService ? __('deal.select_time_to_book', {default: 'Select a time to book this service.'}) : __('deal.confirm_marks_sold');
        let yesBtnText = isService ? __('deal.book_service', {default: 'Book Service'}) : __('deal.yes_done');

        bannerHtml = `
            <div class="chat-deal-banner chat-deal-banner--primary" onclick="openDealSheet()">
                <div class="chat-deal-banner__info">
                    <span class="chat-deal-banner__icon">${isService ? '📅' : '🤝'}</span>
                    <div class="chat-deal-banner__texts">
                        <span class="chat-deal-banner__headline">${promptText}</span>
                        <span class="chat-deal-banner__sub">${promptSub}</span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-xs chat-deal-banner__btn" onclick="event.stopPropagation(); openDealSheet();">
                    ${yesBtnText}
                </button>
            </div>
        `;

        let extraFields = '';
        if (isService) {
            extraFields = `
                <div style="margin-bottom: 1rem; padding: 0.85rem; border: 1px solid var(--border-light); border-radius: var(--radius-md); background: var(--bg-main);">
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 140px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">Start Date & Time</label>
                            <input type="datetime-local" id="deal_sched_start" class="premium-input" style="width: 100%; padding: 0.45rem 0.55rem; font-size: 0.85rem; border-radius: var(--radius-md);">
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">End Date & Time</label>
                            <input type="datetime-local" id="deal_sched_end" class="premium-input" style="width: 100%; padding: 0.45rem 0.55rem; font-size: 0.85rem; border-radius: var(--radius-md);">
                        </div>
                    </div>
                </div>
            `;
        }

        sheetHtml = `
            <div class="deal-sheet-flow">
                <p class="deal-sheet-explainer">${__('chat.orders_deal_explainer')}</p>
                <div class="deal-sheet-prompt-card">
                    <div class="deal-sheet-prompt-title">${promptText}</div>
                    <div class="deal-sheet-prompt-desc">${promptSub}</div>
                </div>
                ${extraFields}
                <div class="deal-sheet-actions">
                    <button type="button" onclick="confirmDeal(${deal.product_id || 'null'})" class="btn btn-primary btn-md w-full">${yesBtnText}</button>
                    <button type="button" onclick="closeDealSheet()" class="btn btn-secondary btn-md w-full">${__('deal.not_yet')}</button>
                </div>
            </div>
        `;
    } else if (status === 'buyer_confirmed' && !isSeller) {
        bannerHtml = `
            <div class="chat-deal-banner chat-deal-banner--awaiting" onclick="openDealSheet()">
                <div class="chat-deal-banner__info">
                    <span class="chat-deal-banner__icon">⏳</span>
                    <div class="chat-deal-banner__texts">
                        <span class="chat-deal-banner__headline">${__('deal.awaiting_confirmation')}</span>
                        <span class="chat-deal-banner__sub">${__('deal.you_confirmed_waiting')}</span>
                    </div>
                </div>
                <span class="chat-deal-banner__badge">${__('deal.deal_info')}</span>
            </div>
        `;

        sheetHtml = `
            <div class="deal-sheet-flow">
                <p class="deal-sheet-explainer">${__('chat.orders_deal_explainer')}</p>
                <div class="deal-sheet-prompt-card deal-sheet-prompt-card--muted">
                    <div class="deal-sheet-prompt-title">${__('deal.awaiting_confirmation')}</div>
                    <div class="deal-sheet-prompt-desc">${__('deal.you_confirmed_waiting')}</div>
                </div>
                <div class="deal-sheet-actions">
                    <button type="button" onclick="closeDealSheet()" class="btn btn-secondary btn-md w-full">${__('common.close') || 'Close'}</button>
                </div>
            </div>
        `;
    } else if (status === 'buyer_confirmed' && isSeller) {
        bannerHtml = `
            <div class="chat-deal-banner chat-deal-banner--action" onclick="openDealSheet()">
                <div class="chat-deal-banner__info">
                    <span class="chat-deal-banner__icon">🔔</span>
                    <div class="chat-deal-banner__texts">
                        <span class="chat-deal-banner__headline">${__('deal.says_done', {buyer: buyerName})}</span>
                        <span class="chat-deal-banner__sub">${__('deal.confirm_to_mark', {product: productTitle})}</span>
                    </div>
                </div>
                <button type="button" class="btn btn-success btn-xs chat-deal-banner__btn" style="background: var(--secondary); border-color: var(--secondary); color: #fff;" onclick="event.stopPropagation(); openDealSheet();">
                    ${__('deal.confirm_delist')}
                </button>
            </div>
        `;

        sheetHtml = `
            <div class="deal-sheet-flow">
                <p class="deal-sheet-explainer">${__('chat.orders_deal_explainer')}</p>
                <div class="deal-sheet-prompt-card deal-sheet-prompt-card--success">
                    <div class="deal-sheet-prompt-title">${__('deal.says_done', {buyer: buyerName})}</div>
                    <div class="deal-sheet-prompt-desc">${__('deal.confirm_to_mark', {product: productTitle})}</div>
                </div>
                <div class="deal-sheet-actions">
                    <button type="button" onclick="confirmDeal(${deal.product_id || 'null'})" class="btn btn-primary btn-md w-full" style="background: var(--secondary); border-color: var(--secondary);">${__('deal.confirm_delist')}</button>
                    <button type="button" onclick="closeDealSheet()" class="btn btn-secondary btn-md w-full">${__('deal.not_done_yet')}</button>
                </div>
            </div>
        `;
    } else if (status === 'completed') {
        bannerHtml = `
            <div class="chat-deal-banner chat-deal-banner--completed" onclick="openDealSheet()">
                <div class="chat-deal-banner__info">
                    <span class="chat-deal-banner__icon">✅</span>
                    <div class="chat-deal-banner__texts">
                        <span class="chat-deal-banner__headline">${__('deal.deal_confirmed')}</span>
                        <span class="chat-deal-banner__sub">${__('deal.item_marked_sold')}</span>
                    </div>
                </div>
                <span class="chat-deal-banner__badge chat-deal-banner__badge--success">✓ ${__('deal.deal_info')}</span>
            </div>
        `;

        sheetHtml = `
            <div class="deal-sheet-flow">
                <div class="deal-sheet-prompt-card deal-sheet-prompt-card--success">
                    <div class="deal-sheet-prompt-title" style="color: var(--secondary);">${__('deal.deal_confirmed')}</div>
                    <div class="deal-sheet-prompt-desc">${__('deal.item_marked_sold')}</div>
                </div>
                <div class="deal-sheet-actions">
                    <button type="button" onclick="closeDealSheet()" class="btn btn-secondary btn-md w-full">${__('common.close') || 'Close'}</button>
                </div>
            </div>
        `;
    } else {
        handshakeBar.hidden = true;
        handshakeBar.style.display = 'none';
        closeDealSheet();
        return;
    }

    handshakeBar.innerHTML = bannerHtml;
    dealSheetBody.innerHTML = sheetHtml;
}

function openProductSelector() {
    const initView = document.getElementById('choose-product-initial');
    const selectView = document.getElementById('choose-product-selector');
    if (initView) initView.style.display = 'none';
    if (selectView) selectView.style.display = 'block';
    
    const select = document.getElementById('deal-product-select');
    if (!select) return;
    
    fetch('api_messages.php?action=get_active_products&other_user_id=' + otherUserId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                let options = '<option value="">' + __('deal.select_item_prompt') + '</option>';
                data.products.forEach(p => {
                    options += `<option value="${p.id}" data-is-mine="${p.is_mine}">${p.title} - ${p.price}</option>`;
                });
                select.innerHTML = options;
            } else {
                select.innerHTML = '<option value="">' + __('deal.no_active_items') + '</option>';
            }
        })
        .catch(err => {
            select.innerHTML = '<option value="">' + __('deal.error_loading') + '</option>';
        });
}

function cancelChooseProduct() {
    const initView = document.getElementById('choose-product-initial');
    const selectView = document.getElementById('choose-product-selector');
    if (selectView) selectView.style.display = 'none';
    if (initView) initView.style.display = 'block';
}

function submitChosenProduct() {
    const select = document.getElementById('deal-product-select');
    if (!select || select.selectedIndex <= 0) return;
    
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
        if (!confirm(__('deal.confirm_delist_warning'))) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('action', 'confirm_deal');
    formData.append('product_id', finalProductId);
    formData.append('other_user_id', otherUserId);
    formData.append('csrf_token', window.__csrfToken || '');

    // Grab scheduling inputs if present
    const schedStart = document.getElementById('deal_sched_start');
    const schedEnd = document.getElementById('deal_sched_end');
    if (schedStart && schedStart.value) {
        formData.append('scheduled_start', schedStart.value);
    }
    if (schedEnd && schedEnd.value) {
        formData.append('scheduled_end', schedEnd.value);
    }

    fetch('api_messages.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (typeof posthog !== 'undefined') {
                    if (data.action === 'awaiting_seller') {
                        posthog.capture('deal_initiated', { listing_id: finalProductId });
                    } else if (data.action === 'delisted') {
                        posthog.capture('deal_confirmed', { listing_id: finalProductId });
                    }
                }
                closeDealSheet();
                checkDealStatus();
            } else {
                alert('Error: ' + (data.error || 'Unknown'));
            }
        });
}

// ─── Presence polling ─────────────────────────────────────
const PRESENCE_OTHER_USER_ID = <?= $otherUserId ?>;

function refreshPresence() {
    fetch(`api_messages.php?action=get_presence&user_id=${PRESENCE_OTHER_USER_ID}&_=${Date.now()}`, { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const dot  = document.getElementById('presence-dot');
            const text = document.getElementById('presence-text');
            if (!dot || !text) return;

            const colorMap  = { online: '#10b981', recently: '#f59e0b', offline: '#94a3b8' };
            const statusKey = data.status;
            dot.style.background = colorMap[statusKey] || '#94a3b8';

            if (statusKey === 'online') {
                text.textContent = __('chat.online_now');
            } else if (statusKey === 'recently') {
                const mins = data.mins;
                text.textContent = mins === 1 ? __('chat.active_1min_ago') : __('chat.active_mins_ago', { mins });
            } else {
                text.textContent = __('chat.offline');
            }
        })
        .catch(() => {});
}

// Poll presence every 30 s
setInterval(refreshPresence, 30000);

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
