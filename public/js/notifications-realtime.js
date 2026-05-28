(function() {
    // Check for Supabase and User ID
    const supabase = window.CampusMarketSupabase;
    const userMeta = document.querySelector('meta[name="user-id"]');
    const baseUrl = window.__baseUrl || '/';
    const normalizePath = (p) => baseUrl.replace(/\/+$/, '') + '/' + p.replace(/^\/+/, '');
    let refreshTimer = null;

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

    // If Supabase is missing, still keep the UI fresh (badges + page auto-refresh hooks).
    if (!supabase || !userMeta) {
        refreshCounts();
        setInterval(refreshCounts, 15000);
        return;
    }

    const currentUserId = parseInt(userMeta.content);
    if (!currentUserId) return;

    console.log('Realtime notifications initialized for user:', currentUserId);

    // Subscribe to messages table
    supabase
        .channel('messages-unread')
        .on('postgres_changes', { 
            event: 'INSERT', 
            schema: 'public', 
            table: 'messages',
            filter: `receiver_id=eq.${currentUserId}`
        }, (payload) => {
            console.log('New message received:', payload.new);
            scheduleRefresh();
            showBrowserNotification(
                'New message',
                payload?.new?.body || 'You received a new message.',
                normalizePath('pages/inbox.php')
            );
        })
        .subscribe();

    // Subscribe to notifications table
    supabase
        .channel('notifications-unread')
        .on('postgres_changes', { 
            event: 'INSERT', 
            schema: 'public', 
            table: 'notifications',
            filter: `user_id=eq.${currentUserId}`
        }, (payload) => {
            console.log('New notification received:', payload.new);
            scheduleRefresh();
            showBrowserNotification(
                payload?.new?.title || 'New notification',
                payload?.new?.body || 'You have a new activity update.',
                normalizePath('pages/notifications.php')
            );
        })
        .subscribe();

    // Also listen for UPDATE (when marked as read in another tab)
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

    // Initial sync for open tabs
    refreshCounts();

})();
