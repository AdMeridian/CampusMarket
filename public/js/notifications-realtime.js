(function() {
    // Check for Supabase and User ID
    const supabase = window.CampusMarketSupabase;
    const userMeta = document.querySelector('meta[name="user-id"]');
    if (!supabase || !userMeta) return;

    const currentUserId = parseInt(userMeta.content);
    if (!currentUserId) return;

    const baseUrl = window.__baseUrl || '/';
    const normalizePath = (p) => baseUrl.replace(/\/+$/, '') + '/' + p.replace(/^\/+/, '');
    let refreshTimer = null;

    console.log('Realtime notifications initialized for user:', currentUserId);

    // Function to fetch updated counts and refresh the UI badges
    async function refreshCounts() {
        try {
            const response = await fetch(normalizePath('pages/api_counts.php'));
            const data = await response.json();
            
            if (data.success) {
                updateBadges(data.unreadMessages, data.unreadNotifs);
            }
        } catch (err) {
            console.error('Failed to fetch unread counts:', err);
        }
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

        // Trigger a custom event for pages like inbox.php to refresh their lists if needed
        window.dispatchEvent(new CustomEvent('campusmarket:notifications-updated', { 
            detail: { messages, notifs } 
        }));
    }

    function scheduleRefresh() {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshCounts, 120);
    }

    function showBrowserNotification(title, body, url) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (!document.hidden) return;

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
