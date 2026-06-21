/* public/js/chatbot.js */
// Chatbot: Supabase Edge Function (Gemini) with PHP fallback

document.addEventListener('DOMContentLoaded', () => {
    const chatbotFab = document.getElementById('cm-chatbot-fab');
    const chatbotWindow = document.getElementById('cm-chatbot-window');
    const chatbotClose = document.getElementById('cm-chatbot-close');
    const chatbotForm = document.getElementById('cm-chatbot-form');
    const chatbotInput = document.getElementById('cm-chatbot-input');
    const chatbotMessages = document.getElementById('cm-chatbot-messages');

    if (!chatbotFab || !chatbotWindow || !chatbotClose || !chatbotForm || !chatbotInput || !chatbotMessages) {
        return;
    }

    const adminId = parseInt(chatbotWindow.getAttribute('data-admin-id') || '1', 10);
    const locale = window.__locale || 'en';
    const isTurkish = locale === 'tr';
    const siteBaseUrl = window.__baseUrl || '/';
    const HISTORY_KEY = 'cm_chatbot_history_v2';
    let conversationHistory = loadHistory();
    let isSubmitting = false;

    const strings = {
        welcome: isTurkish
            ? "Merhaba! 👋 Ben CampusMarket yapay zeka asistanıyım. Kampüs pazar yeri kuralları, ödemeler, güvenli alışveriş ve ilan yükleme hakkında sorularınızı yanıtlayabilirim. Nasıl yardımcı olabilirim?"
            : "Hello! 👋 I'm the CampusMarket AI assistant. I can answer questions regarding campus marketplace guidelines, secure payments, safety rules, and listing creation. How can I help you today?",
        placeholder: isTurkish ? "Sorunuzu buraya yazın..." : "Type your question here...",
        adminAlertTitle: isTurkish ? "Yöneticiye Ulaşın" : "Contact Administrator",
        adminAlertDesc: isTurkish
            ? "Maalesef bu sorunun cevabını bilmiyorum. Destek almak için lütfen doğrudan yöneticiyle sohbet başlatın."
            : "I'm sorry, I don't know the answer to that question. To get help, please start a direct conversation with the administrator.",
        adminBtnText: isTurkish ? "Yönetici Sohbetini Aç" : "Open Admin Chatbox",
        connectionError: isTurkish
            ? "Bağlantı hatası. İnternet bağlantınızı kontrol edip tekrar deneyin."
            : "Connection error. Please check your internet connection and try again.",
        genericError: isTurkish
            ? "Bir hata oluştu. Lütfen daha sonra tekrar deneyin."
            : "An error occurred. Please try again later.",
    };

    chatbotInput.placeholder = strings.placeholder;
    appendBotMessage(strings.welcome);

    chatbotFab.addEventListener('click', () => {
        const isOpen = chatbotWindow.classList.contains('open');
        if (isOpen) {
            chatbotWindow.classList.remove('open');
        } else {
            chatbotWindow.classList.add('open');
            chatbotInput.focus();
            chatbotFab.className = 'cm-chatbot-fab';
        }
    });

    chatbotClose.addEventListener('click', () => {
        chatbotWindow.classList.remove('open');
    });

    chatbotForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isSubmitting) return;

        const messageText = chatbotInput.value.trim();
        if (!messageText) return;

        isSubmitting = true;
        chatbotForm.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');

        appendUserMessage(messageText);
        chatbotInput.value = '';

        const typingEl = showTypingIndicator();

        try {
            const data = await sendChatMessage(messageText);
            removeTypingIndicator(typingEl);

            if (!data) {
                appendBotMessage(strings.connectionError);
                return;
            }

            if (data.success) {
                if (data.unknown) {
                    appendAdminEscalationCard(data.admin_id || adminId);
                } else {
                    appendBotMessage(data.response);
                    pushHistoryTurn('user', messageText);
                    pushHistoryTurn('model', data.response);
                }
            } else if (data.error === 'rate_limit') {
                appendBotMessage(`⚠️ ${data.response || strings.genericError}`);
            } else {
                appendBotMessage(strings.genericError);
            }
        } catch (err) {
            removeTypingIndicator(typingEl);
            console.error('Chatbot error:', err);
            appendBotMessage(strings.connectionError);
        } finally {
            isSubmitting = false;
            chatbotForm.querySelector('button[type="submit"]')?.removeAttribute('disabled');
            chatbotInput.focus();
        }
    });

    async function sendChatMessage(messageText) {
        const payload = {
            message: messageText,
            history: conversationHistory,
            locale,
            site_base_url: siteBaseUrl,
        };

        const supabase = window.CampusMarketSupabase;
        if (supabase && typeof supabase.functions?.invoke === 'function') {
            const { data, error } = await supabase.functions.invoke('chatbot', { body: payload });
            if (!error && data) {
                return data;
            }
            console.warn('Edge chatbot failed, falling back to PHP:', error);
        }

        const response = await fetch(`${siteBaseUrl}pages/api_chatbot.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            return null;
        }

        return response.json();
    }

    function loadHistory() {
        try {
            const raw = sessionStorage.getItem(HISTORY_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    function saveHistory() {
        try {
            sessionStorage.setItem(HISTORY_KEY, JSON.stringify(conversationHistory.slice(-20)));
        } catch {
            // ignore quota errors
        }
    }

    function pushHistoryTurn(role, text) {
        conversationHistory.push({ role, parts: [{ text }] });
        if (conversationHistory.length > 20) {
            conversationHistory = conversationHistory.slice(-20);
        }
        saveHistory();
    }

    function appendUserMessage(text) {
        const bubble = document.createElement('div');
        bubble.className = 'cm-chat-bubble user';
        bubble.textContent = text;
        chatbotMessages.appendChild(bubble);
        scrollToBottom();
    }

    function appendBotMessage(markdownText) {
        const bubble = document.createElement('div');
        bubble.className = 'cm-chat-bubble bot';
        bubble.innerHTML = parseSimpleMarkdown(markdownText);
        chatbotMessages.appendChild(bubble);
        scrollToBottom();
    }

    function appendAdminEscalationCard(targetAdminId) {
        const card = document.createElement('div');
        card.className = 'cm-fallback-card';
        const chatboxUrl = `${siteBaseUrl}pages/messages.php?other_user_id=${targetAdminId}&product_id=0`;

        card.innerHTML = `
            <div class="cm-fallback-title">
                <span>⚠️</span> ${strings.adminAlertTitle}
            </div>
            <div class="cm-fallback-desc">
                ${strings.adminAlertDesc}
            </div>
            <a href="${chatboxUrl}" class="cm-fallback-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 16px; height: 16px; flex-shrink: 0;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10z"></path></svg>
                <span>${strings.adminBtnText}</span>
            </a>
        `;
        chatbotMessages.appendChild(card);
        scrollToBottom();
    }

    function showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'cm-typing-indicator';
        indicator.innerHTML = `
            <div class="cm-typing-dot"></div>
            <div class="cm-typing-dot"></div>
            <div class="cm-typing-dot"></div>
        `;
        chatbotMessages.appendChild(indicator);
        scrollToBottom();
        return indicator;
    }

    function removeTypingIndicator(el) {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function scrollToBottom() {
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    function parseSimpleMarkdown(text) {
        let escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        return escaped.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    }
});
