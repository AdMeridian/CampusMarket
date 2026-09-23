/**
 * CampusMarket - In-App Promotional Spotlight Slideshow Modal
 * Displays active discounted and featured listings in a multi-product carousel.
 * Cooldown: 3 days (72 hours), with smart new-item tracking to show when new promotions appear.
 */
(function () {
    const STORAGE_KEY_TIME = 'cm_spotlight_dismissed_at';
    const STORAGE_KEY_SEEN = 'cm_spotlight_seen_ids';
    const COOLDOWN_MS = 3 * 24 * 60 * 60 * 1000; // 3 days (72 hours)

    function isCooldownActive() {
        const last = localStorage.getItem(STORAGE_KEY_TIME);
        if (!last) return false;
        const diff = Date.now() - parseInt(last, 10);
        return diff < COOLDOWN_MS;
    }

    function getSeenIds() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_SEEN);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function dismissSpotlight(productIds) {
        localStorage.setItem(STORAGE_KEY_TIME, Date.now().toString());
        if (Array.isArray(productIds) && productIds.length > 0) {
            localStorage.setItem(STORAGE_KEY_SEEN, JSON.stringify(productIds));
        }
        const modal = document.getElementById('cm-spotlight-modal');
        if (!modal) return;
        modal.classList.remove('is-active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 250);
    }

    // Do not show on admin pages, active chat, or auth pages
    const path = window.location.pathname.toLowerCase();
    if (
        path.includes('/admin/') ||
        path.includes('messages.php') ||
        path.includes('login.php') ||
        path.includes('register.php') ||
        path.includes('reset_password.php') ||
        path.includes('create_listing.php') ||
        path.includes('manage_listing.php')
    ) {
        return;
    }

    document.addEventListener('DOMContentLoaded', initSpotlight);

    function shuffleArray(arr) {
        const copy = [...arr];
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }
        return copy;
    }

    function initSpotlight() {
        const baseUrl = window.__baseUrl || window.__cmBaseUrl || '';
        const apiUrl = (baseUrl.replace(/\/+$/, '') || '') + '/pages/api_promo_spotlight.php';

        fetch(apiUrl, { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.products) && data.products.length > 0) {
                    const seenIds = getSeenIds();
                    
                    // Sort & shuffle: unseen items first (shuffled), followed by seen items (shuffled)
                    const unseen = shuffleArray(data.products.filter(p => !seenIds.includes(p.id)));
                    const seen = shuffleArray(data.products.filter(p => seenIds.includes(p.id)));
                    let orderedItems = [...unseen, ...seen];
                    const realProductIds = orderedItems.map(p => p.id);

                    const hasNewItems = unseen.length > 0;
                    const cooldownActive = isCooldownActive();

                    // If cooldown is active AND there are no new items, do not show
                    if (cooldownActive && !hasNewItems) {
                        return;
                    }

                    // Social media community slide
                    const socials = window.__cmSocials || {};
                    const i18n = window.__cmSpotlightI18n || {};
                    const whatsappUrl = socials.whatsapp || 'https://whatsapp.com/channel/0029VbDXVeGGzzKKn3y8kS0j';
                    const instagramUrl = socials.instagram || 'https://www.instagram.com/campusmarketplace_nc/';

                    const socialSlide = {
                        is_social: true,
                        id: 'social-community',
                        title: i18n.communityTitle || 'Join our campus community on WhatsApp & Instagram!',
                        tag: i18n.communityTag || 'Official Community',
                        desc: i18n.communityDesc || 'Stay connected with announcements, campus drops, and engage with fellow students.',
                        joinWhatsapp: i18n.joinWhatsapp || 'Join WhatsApp Channel',
                        followInstagram: i18n.followInstagram || 'Follow on Instagram',
                        whatsapp_url: whatsappUrl,
                        instagram_url: instagramUrl
                    };

                    // Insert social slide at a random position
                    if (orderedItems.length > 0) {
                        const randomIndex = Math.floor(Math.random() * (orderedItems.length + 1));
                        orderedItems.splice(randomIndex, 0, socialSlide);
                    } else {
                        orderedItems.push(socialSlide);
                    }

                    renderModal(orderedItems, realProductIds);
                }
            })
            .catch(() => {
                // Silently ignore network failures for marketing modal
            });
    }

    function renderModal(items, productIds) {
        let currentIndex = 0;
        let autoTimer = null;
        const handleDismiss = () => dismissSpotlight(productIds);
        const i18n = window.__cmSpotlightI18n || {};

        const modal = document.createElement('div');
        modal.id = 'cm-spotlight-modal';
        modal.className = 'spotlight-modal-backdrop';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Featured Deals');

        const slidesHtml = items.map((item, idx) => {
            if (item.is_social) {
                return `
                    <div class="spotlight-slide spotlight-slide--social ${idx === 0 ? 'is-active' : ''}" data-index="${idx}">
                        <div class="spotlight-slide__media spotlight-slide__media--social">
                            <div class="spotlight-social-hero-art">
                                <div class="spotlight-social-hero-icons">
                                    <div class="spotlight-social-orb spotlight-social-orb--ig">
                                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                    </div>
                                    <div class="spotlight-social-orb-pulse"></div>
                                    <div class="spotlight-social-orb spotlight-social-orb--wa">
                                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.04 14.69 2 12.04 2ZM12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.59 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19.01L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.8 13.47 3.8 11.91C3.81 7.37 7.5 3.67 12.05 3.67ZM8.53 7.33C8.37 7.33 8.1 7.39 7.87 7.64C7.65 7.89 7 8.5 7 9.71C7 10.93 7.89 12.1 8.01 12.26C8.14 12.43 9.74 14.88 12.22 15.94C12.81 16.2 13.27 16.35 13.63 16.47C14.23 16.66 14.77 16.63 15.2 16.57C15.68 16.5 16.68 15.96 16.89 15.38C17.1 14.79 17.1 14.29 17.03 14.19C16.97 14.08 16.8 14.02 16.55 13.9C16.3 13.77 15.08 13.17 14.85 13.09C14.63 13.01 14.46 12.96 14.3 13.21C14.13 13.46 13.65 14.02 13.51 14.19C13.36 14.35 13.22 14.37 12.97 14.25C12.72 14.12 11.92 13.86 10.97 13.02C10.23 12.36 9.73 11.55 9.58 11.3C9.44 11.05 9.56 10.92 9.69 10.79C9.8 10.68 9.94 10.5 10.07 10.35C10.2 10.2 10.24 10.09 10.32 9.93C10.4 9.76 10.36 9.62 10.3 9.49C10.24 9.37 9.74 8.15 9.54 7.64C9.34 7.15 9.13 7.21 8.97 7.21L8.53 7.33Z"/></svg>
                                    </div>
                                </div>
                                <span class="spotlight-badge spotlight-badge--community">👥 Campus Community</span>
                            </div>
                        </div>
                        <div class="spotlight-slide__content spotlight-slide__content--social">
                            <div class="spotlight-slide__category">${escapeHtml(item.tag)}</div>
                            <h3 class="spotlight-slide__title spotlight-slide__title--social">${escapeHtml(item.title)}</h3>
                            <p class="spotlight-slide__desc--social">${escapeHtml(item.desc)}</p>
                            <div class="spotlight-social-btns">
                                <a href="${escapeHtml(item.whatsapp_url)}" target="_blank" rel="noopener noreferrer" class="btn spotlight-social-btn spotlight-social-btn--wa">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.04 14.69 2 12.04 2ZM12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.59 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19.01L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.8 13.47 3.8 11.91C3.81 7.37 7.5 3.67 12.05 3.67ZM8.53 7.33C8.37 7.33 8.1 7.39 7.87 7.64C7.65 7.89 7 8.5 7 9.71C7 10.93 7.89 12.1 8.01 12.26C8.14 12.43 9.74 14.88 12.22 15.94C12.81 16.2 13.27 16.35 13.63 16.47C14.23 16.66 14.77 16.63 15.2 16.57C15.68 16.5 16.68 15.96 16.89 15.38C17.1 14.79 17.1 14.29 17.03 14.19C16.97 14.08 16.8 14.02 16.55 13.9C16.3 13.77 15.08 13.17 14.85 13.09C14.63 13.01 14.46 12.96 14.3 13.21C14.13 13.46 13.65 14.02 13.51 14.19C13.36 14.35 13.22 14.37 12.97 14.25C12.72 14.12 11.92 13.86 10.97 13.02C10.23 12.36 9.73 11.55 9.58 11.3C9.44 11.05 9.56 10.92 9.69 10.79C9.8 10.68 9.94 10.5 10.07 10.35C10.2 10.2 10.24 10.09 10.32 9.93C10.4 9.76 10.36 9.62 10.3 9.49C10.24 9.37 9.74 8.15 9.54 7.64C9.34 7.15 9.13 7.21 8.97 7.21L8.53 7.33Z"/></svg>
                                    <span>${escapeHtml(item.joinWhatsapp)}</span>
                                </a>
                                <a href="${escapeHtml(item.instagram_url)}" target="_blank" rel="noopener noreferrer" class="btn spotlight-social-btn spotlight-social-btn--ig">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                    <span>${escapeHtml(item.followInstagram)}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }

            const isDiscount = item.discount_percent > 0;
            const badgeLabel = item.is_featured
                ? (isDiscount ? `⭐ FEATURED · -${item.discount_percent}%` : '⭐ FEATURED')
                : `🔥 -${item.discount_percent}% OFF`;
            const badgeClass = item.is_featured ? 'spotlight-badge--featured' : 'spotlight-badge--discount';

            return `
                <div class="spotlight-slide ${idx === 0 ? 'is-active' : ''}" data-index="${idx}">
                    <div class="spotlight-slide__media">
                        <img src="${item.image_url}" alt="${escapeHtml(item.title)}" loading="lazy" class="spotlight-slide__img">
                        <span class="spotlight-badge ${badgeClass}">${badgeLabel}</span>
                    </div>
                    <div class="spotlight-slide__content">
                        <div class="spotlight-slide__category">${escapeHtml(item.category_name || 'Marketplace')}</div>
                        <h3 class="spotlight-slide__title">${escapeHtml(item.title)}</h3>
                        <div class="spotlight-slide__meta">
                            <div class="spotlight-slide__price-wrap">
                                <span class="spotlight-slide__price">${escapeHtml(item.price_formatted)}</span>
                                ${item.original_price_formatted ? `<span class="spotlight-slide__original-price">${escapeHtml(item.original_price_formatted)}</span>` : ''}
                            </div>
                            <span class="spotlight-slide__seller">${escapeHtml(i18n.seller || 'Seller')}: @${escapeHtml(item.seller_name || 'Student')}</span>
                        </div>
                        <a href="${item.url}" class="btn btn-primary spotlight-slide__cta">
                            <span>${escapeHtml(i18n.viewListing || 'View Listing')}</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            `;
        }).join('');

        const dotsHtml = items.map((_, idx) => `
            <button type="button" class="spotlight-dot ${idx === 0 ? 'is-active' : ''}" data-index="${idx}" aria-label="Slide ${idx + 1}"></button>
        `).join('');

        modal.innerHTML = `
            <div class="spotlight-modal-card">
                <div class="spotlight-modal-header">
                    <div class="spotlight-modal-tag">
                        <span class="spotlight-sparkle">✨</span>
                        <span>Campus Spotlight & Deals</span>
                    </div>
                    <button type="button" class="spotlight-close-btn" id="spotlight-close-btn" aria-label="Close spotlight">&times;</button>
                </div>

                <div class="spotlight-carousel-wrap" id="spotlight-carousel">
                    ${items.length > 1 ? `
                        <button type="button" class="spotlight-nav-btn spotlight-nav-btn--prev" id="spotlight-prev-btn" aria-label="Previous">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" class="spotlight-nav-btn spotlight-nav-btn--next" id="spotlight-next-btn" aria-label="Next">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    ` : ''}
                    <div class="spotlight-slides-viewport">
                        ${slidesHtml}
                    </div>
                </div>

                <div class="spotlight-modal-footer">
                    ${items.length > 1 ? `<div class="spotlight-dots">${dotsHtml}</div>` : '<div></div>'}
                    <button type="button" class="spotlight-ghost-dismiss" id="spotlight-ghost-btn">${escapeHtml(i18n.maybeLater || 'Maybe later')}</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Animate in after slight delay
        setTimeout(() => {
            modal.style.display = 'flex';
            requestAnimationFrame(() => {
                modal.classList.add('is-active');
            });
        }, 1200);

        // Slide navigation functions
        const slides = modal.querySelectorAll('.spotlight-slide');
        const dots = modal.querySelectorAll('.spotlight-dot');

        function goToSlide(index) {
            if (index < 0) index = slides.length - 1;
            if (index >= slides.length) index = 0;
            currentIndex = index;

            slides.forEach((s, idx) => {
                s.classList.toggle('is-active', idx === currentIndex);
            });
            dots.forEach((d, idx) => {
                d.classList.toggle('is-active', idx === currentIndex);
            });
        }

        function startAutoPlay() {
            if (items.length <= 1) return;
            stopAutoPlay();
            autoTimer = setInterval(() => {
                goToSlide(currentIndex + 1);
            }, 4500);
        }

        function stopAutoPlay() {
            if (autoTimer) clearInterval(autoTimer);
        }

        startAutoPlay();

        // Event listeners
        const card = modal.querySelector('.spotlight-modal-card');
        card.addEventListener('mouseenter', stopAutoPlay);
        card.addEventListener('mouseleave', startAutoPlay);
        card.addEventListener('touchstart', stopAutoPlay, { passive: true });

        const prevBtn = modal.querySelector('#spotlight-prev-btn');
        const nextBtn = modal.querySelector('#spotlight-next-btn');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                goToSlide(currentIndex - 1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                goToSlide(currentIndex + 1);
            });
        }

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const idx = parseInt(dot.getAttribute('data-index'), 10);
                goToSlide(idx);
            });
        });

        // Touch swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        const carousel = modal.querySelector('#spotlight-carousel');
        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            carousel.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
        }

        function handleSwipe() {
            const diff = touchEndX - touchStartX;
            if (Math.abs(diff) > 40) {
                if (diff < 0) {
                    goToSlide(currentIndex + 1); // Swiped left -> Next
                } else {
                    goToSlide(currentIndex - 1); // Swiped right -> Prev
                }
            }
        }

        // Dismiss handlers (require explicit close button, 'Maybe later', or Escape key)
        modal.querySelector('#spotlight-close-btn').addEventListener('click', handleDismiss);
        modal.querySelector('#spotlight-ghost-btn').addEventListener('click', handleDismiss);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                handleDismiss();
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
