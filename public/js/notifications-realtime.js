(function() {
    // Check for Supabase and User ID
    const supabase = window.CampusMarketSupabase;
    const userMeta = document.querySelector('meta[name="user-id"]');
    if (!supabase || !userMeta) return;

    const currentUserId = parseInt(userMeta.content);
    if (!currentUserId) return;

    console.log('Realtime notifications initialized for user:', currentUserId);

    // Function to fetch updated counts and refresh the UI badges
    async function refreshCounts() {
        try {
            const response = await fetch('/pages/api_counts.php');
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
            refreshCounts();
            // Optional: Play sound or show browser notification
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
            refreshCounts();
        })
        .subscribe();

    // Also listen for UPDATE (when marked as read in another tab)
    supabase
        .channel('sync-read-status')
        .on('postgres_changes', { 
            event: 'UPDATE', 
            schema: 'public', 
            table: 'messages'
        }, () => refreshCounts())
        .on('postgres_changes', { 
            event: 'UPDATE', 
            schema: 'public', 
            table: 'notifications'
        }, () => refreshCounts())
        .subscribe();

})();
