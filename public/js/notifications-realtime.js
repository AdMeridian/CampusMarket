(function () {
    const userMeta = document.querySelector('meta[name="user-id"]');
    const baseUrl = window.__baseUrl || '/';
    const normalizePath = (p) => baseUrl.replace(/\/+$/, '') + '/' + String(p || '').replace(/^\/+/, '');

    let refreshTimer = null;
    let pollingIntervalId = null;
    let lastMessages = null;
    let lastNotifs = null;
    const syncChannel = ('BroadcastChannel' in window) ? new BroadcastChannel('campusmarket-counts') : null;

    async function refreshCounts() {
        try {
            const response = await fetch(normalizePath('pages/api_counts.php'), {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.success) {
                updateBadges(data.unreadMessages, data.unreadNotifs);
            }
        } catch (err) {
            console.error('Failed to fetch unread counts:', err);
        }
    }

    function scheduleRefresh(delayMs) {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshCounts, delayMs || 150);
    }

    function startPolling(intervalMs) {
        if (pollingIntervalId) clearInterval(pollingIntervalId);
        pollingIntervalId = setInterval(refreshCounts, intervalMs);
    }

    function setBadgeCount(elements, count, badgeClass) {
        elements.forEach((el) => {
            if (el.classList.contains('badge')) {
                if (count > 0) {
                    el.textContent = String(count);
                    el.className = 'badge ' + badgeClass;
                    el.style.display = '';
                } else {
                    el.remove();
                }
                return;
            }
            let badge = el.querySelector('.badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    el.appendChild(badge);
                }
                badge.className = 'badge ' + badgeClass;
                badge.textContent = String(count);
            } else if (badge) {
                badge.remove();
            }
        });
    }

    function updateBadges(messages, notifs) {
        lastMessages = messages;
        lastNotifs = notifs;
        const totalInbox = (messages || 0) + (notifs || 0);
        const inboxNavBadgeClass = (messages || 0) > 0 ? 'badge-primary' : 'badge-accent';

        // 1. Navigation bar & mobile bottom bar inbox indicator
        setBadgeCount(
            document.querySelectorAll('[data-nav-badge="inbox"]'),
            totalInbox,
            inboxNavBadgeClass
        );

        // 2. Direct notifications badge if present in header
        setBadgeCount(
            document.querySelectorAll('[data-nav-badge="notifications"]'),
            notifs,
            'badge-accent'
        );

        // 3. Tab pills inside Inbox / Activity pages:
        // Messages tab strictly reflects unread direct messages
        setBadgeCount(
            document.querySelectorAll('[data-inbox-tab="messages"]'),
            messages || 0,
            'badge-primary'
        );

        // Activity tab strictly reflects unread activity notifications
        setBadgeCount(
            document.querySelectorAll('[data-inbox-tab="activity"]'),
            notifs || 0,
            'badge-accent'
        );

        window.dispatchEvent(new CustomEvent('campusmarket:notifications-updated', {
            detail: { messages, notifs },
        }));

        if (syncChannel) {
            syncChannel.postMessage({ messages, notifs });
        }
    }

    function bumpBadge(type) {
        if (type === 'messages') {
            updateBadges((lastMessages ?? 0) + 1, lastNotifs ?? 0);
        } else {
            updateBadges(lastMessages ?? 0, (lastNotifs ?? 0) + 1);
        }
        scheduleRefresh(100);
    }

    function showBrowserNotification(title, body, url) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (!document.hidden && document.hasFocus && document.hasFocus()) return;

        const options = {
            body: body || 'You have a new update on CampusMarket.',
            icon: normalizePath('public/images/logo.png'),
            badge: normalizePath('public/images/logo.png'),
            data: { url: url || normalizePath('pages/notifications.php') },
        };

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then((reg) => {
                if (reg && typeof reg.showNotification === 'function') {
                    reg.showNotification(title, options);
                } else {
                    const n = new Notification(title, options);
                    n.onclick = () => window.open(options.data.url, '_blank');
                }
            });
            return;
        }

        const n = new Notification(title, options);
        n.onclick = () => window.open(options.data.url, '_blank');
    }

    window.CampusMarketEnableBrowserNotifications = async function () {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return true;
        const result = await Notification.requestPermission();
        return result === 'granted';
    };

    function applyPollingStrategy() {
        startPolling(document.hidden ? 15000 : 5000);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshCounts();
        applyPollingStrategy();
    });

    window.addEventListener('focus', refreshCounts);

    if (syncChannel) {
        syncChannel.onmessage = (event) => {
            const messages = Number(event?.data?.messages);
            const notifs = Number(event?.data?.notifs);
            if (!Number.isNaN(messages) && !Number.isNaN(notifs)) {
                updateBadges(messages, notifs);
            }
        };
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event?.data?.type === 'COUNTS_REFRESH') {
                scheduleRefresh(50);
            }
        });
    }

    async function initRealtime() {
        refreshCounts();
        applyPollingStrategy();

        const currentUserId = userMeta ? parseInt(userMeta.content, 10) : 0;
        if (!currentUserId) return;

        const supabase = window.CampusMarketSupabase;
        if (!supabase) return;

        if (window.CampusMarketSupabaseReady) {
            await window.CampusMarketSupabaseReady;
        }

        supabase
            .channel('messages-unread-' + currentUserId)
            .on('postgres_changes', {
                event: 'INSERT',
                schema: 'public',
                table: 'messages',
                filter: 'receiver_id=eq.' + currentUserId,
            }, (payload) => {
                bumpBadge('messages');
                showBrowserNotification(
                    'New message',
                    payload?.new?.body || 'You received a new message.',
                    normalizePath('pages/inbox.php')
                );
            })
            .subscribe((status) => {
                if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT' || status === 'CLOSED') {
                    startPolling(8000);
                }
            });

        supabase
            .channel('notifications-unread-' + currentUserId)
            .on('postgres_changes', {
                event: 'INSERT',
                schema: 'public',
                table: 'notifications',
                filter: 'user_id=eq.' + currentUserId,
            }, (payload) => {
                bumpBadge('notifications');
                const row = payload?.new || {};
                const params = new URLSearchParams({
                    type: row.type || 'system',
                    title: row.title || '',
                    ref: String(row.reference_id || ''),
                });
                fetch(normalizePath('pages/api_notification_url.php?' + params.toString()), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                    .then((response) => response.ok ? response.json() : null)
                    .then((data) => {
                        showBrowserNotification(
                            row.title || 'New notification',
                            row.body || 'You have a new activity update.',
                            data?.url || normalizePath('pages/notifications.php')
                        );
                    })
                    .catch(() => {
                        showBrowserNotification(
                            row.title || 'New notification',
                            row.body || 'You have a new activity update.',
                            normalizePath('pages/notifications.php')
                        );
                    });
            })
            .subscribe((status) => {
                if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT' || status === 'CLOSED') {
                    startPolling(8000);
                }
            });

        supabase
            .channel('sync-read-status-' + currentUserId)
            .on('postgres_changes', { event: 'UPDATE', schema: 'public', table: 'messages' }, () => scheduleRefresh(150))
            .on('postgres_changes', { event: 'UPDATE', schema: 'public', table: 'notifications' }, () => scheduleRefresh(150))
            .subscribe();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRealtime);
    } else {
        initRealtime();
    }
})();
