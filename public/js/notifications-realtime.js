(function() {
    // Check for Supabase and User ID
    const supabase = window.CampusMarketSupabase;
    const userMeta = document.querySelector('meta[name="user-id"]');
    const baseUrl = window.__baseUrl || '/';
    const normalizePath = (p) => baseUrl.replace(/\/+$/, '') + '/' + p.replace(/^\/+/, '');
    let refreshTimer = null;
    let pollingIntervalId = null;

    // Polling fallback (works even if Supabase is not configured on the server).
    async function refreshCounts() {
        try {
            const response = await fetch(normalizePath('pages/api_counts.php'), { cache: 'no-store' });
            const data = await response.json();
            if (data.success) {
                updateBadges(data.unreadMessages, data.unreadNotifs);
            }
        } catch (err) {
            console.error('Failed to fetch unread counts:', err);
        }
    }

    function scheduleRefresh() {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshCounts, 120);
    }

    function startPolling(intervalMs) {
        if (pollingIntervalId) clearInterval(pollingIntervalId);
        pollingIntervalId = setInterval(refreshCounts, intervalMs);
    }

    function updateBadges(messages, notifs) {
        // Update header badges
        const msgLinks = document.querySelectorAll('a[href*="inbox.php"]');
        const notifLinks = document.querySelectorAll('a[href*="notifications.php"]');

        msgLinks.forEach(link => {
            let badge = link.querySelector('.badge');
            if (messages > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge badge-primary';
                    link.appendChild(badge);
                }
                badge.textContent = messages;
            } else if (badge) {
                badge.remove();
            }
        });

        notifLinks.forEach(link => {
            let badge = link.querySelector('.badge');
            if (notifs > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge badge-accent';
                    link.appendChild(badge);
                }
                badge.textContent = notifs;
            } else if (badge) {
                badge.remove();
            }
        });

        // Trigger a custom event for pages like notifications.php to refresh their lists if needed
        window.dispatchEvent(new CustomEvent('campusmarket:notifications-updated', {
            detail: { messages, notifs }
        }));
    }

    function showBrowserNotification(title, body, url) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        // Show when tab is hidden OR app is not focused (mobile/desktop).
        if (!document.hidden && document.hasFocus && document.hasFocus()) return;

        const options = {
            body: body || 'You have a new update on CampusMarket.',
            icon: normalizePath('public/images/logo.png'),
            badge: normalizePath('public/images/logo.png'),
            data: { url: url || normalizePath('pages/notifications.php') }
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

    // Manual opt-in hook (call from a button in UI when needed).
    window.CampusMarketEnableBrowserNotifications = async function() {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return true;
        const result = await Notification.requestPermission();
        return result === 'granted';
    };

    // ── Refresh immediately when user switches back to the tab ───────────────
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshCounts();
    });

    // ── Refresh on window focus (e.g. alt-tab back to browser) ──────────────
    window.addEventListener('focus', refreshCounts);

    // ── Initial fetch on load ────────────────────────────────────────────────
    refreshCounts();

    // If Supabase is missing, keep the UI fresh with polling only.
    if (!supabase || !userMeta) {
        startPolling(15000);
        return;
    }

    const currentUserId = parseInt(userMeta.content);
    if (!currentUserId) return;

    console.log('Realtime notifications initialized for user:', currentUserId);

    // ── Reliable polling always runs alongside Supabase realtime ────────────
    // Realtime events can be missed due to network blips, reconnects, or
    // Supabase table replication not being enabled — polling guarantees
    // the badge stays accurate even when realtime delivery fails.
    startPolling(30000);

    // ── Supabase Realtime subscriptions ─────────────────────────────────────

    // Subscribe to messages table (INSERT = new incoming message)
    supabase
        .channel('messages-unread')
        .on('postgres_changes', {
            event: 'INSERT',
            schema: 'public',
            table: 'messages',
            filter: `receiver_id=eq.${currentUserId}`
        }, (payload) => {
            scheduleRefresh();
            showBrowserNotification(
                'New message',
                payload?.new?.body || 'You received a new message.',
                normalizePath('pages/inbox.php')
            );
        })
        .subscribe((status) => {
            // If realtime fails for messages, speed up polling to compensate
            if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT' || status === 'CLOSED') {
                console.warn('Messages realtime channel error:', status, '— falling back to 10s poll');
                startPolling(10000);
            }
        });

    // Subscribe to notifications table (INSERT = new notification)
    supabase
        .channel('notifications-unread')
        .on('postgres_changes', {
            event: 'INSERT',
            schema: 'public',
            table: 'notifications',
            filter: `user_id=eq.${currentUserId}`
        }, (payload) => {
            scheduleRefresh();
            showBrowserNotification(
                payload?.new?.title || 'New notification',
                payload?.new?.body || 'You have a new activity update.',
                normalizePath('pages/notifications.php')
            );
        })
        .subscribe((status) => {
            if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT' || status === 'CLOSED') {
                console.warn('Notifications realtime channel error:', status, '— falling back to 10s poll');
                startPolling(10000);
            }
        });

    // Subscribe to UPDATE events (e.g. marked-as-read in another tab)
    supabase
        .channel('sync-read-status')
        .on('postgres_changes', {
            event: 'UPDATE',
            schema: 'public',
            table: 'messages'
        }, () => scheduleRefresh())
        .on('postgres_changes', {
            event: 'UPDATE',
            schema: 'public',
            table: 'notifications'
        }, () => scheduleRefresh())
        .subscribe();

})();
