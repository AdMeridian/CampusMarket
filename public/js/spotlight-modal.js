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

    function initSpotlight() {
        const baseUrl = window.__baseUrl || window.__cmBaseUrl || '';
        const apiUrl = (baseUrl.replace(/\/+$/, '') || '') + '/pages/api_promo_spotlight.php';

        fetch(apiUrl, { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.products) && data.products.length > 0) {
                    const currentIds = data.products.map(p => p.id);
                    const seenIds = getSeenIds();
                    const hasNewItems = currentIds.some(id => !seenIds.includes(id));
                    const cooldownActive = isCooldownActive();

                    // If cooldown is active AND there are no new items, do not show
                    if (cooldownActive && !hasNewItems) {
                        return;
                    }

                    renderModal(data.products, currentIds);
                }
            })
            .catch(() => {
                // Silently ignore network failures for marketing modal
            });
    }

    function renderModal(products, productIds) {
        let currentIndex = 0;
        let autoTimer = null;
        const handleDismiss = () => dismissSpotlight(productIds);

        const modal = document.createElement('div');
        modal.id = 'cm-spotlight-modal';
        modal.className = 'spotlight-modal-backdrop';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Featured Deals');

        const slidesHtml = products.map((prod, idx) => {
            const isDiscount = prod.discount_percent > 0;
            const badgeLabel = prod.is_featured
                ? (isDiscount ? `⭐ FEATURED · -${prod.discount_percent}%` : '⭐ FEATURED')
                : `🔥 -${prod.discount_percent}% OFF`;
            const badgeClass = prod.is_featured ? 'spotlight-badge--featured' : 'spotlight-badge--discount';

            return `
                <div class="spotlight-slide ${idx === 0 ? 'is-active' : ''}" data-index="${idx}">
                    <div class="spotlight-slide__media">
                        <img src="${prod.image_url}" alt="${escapeHtml(prod.title)}" loading="lazy" class="spotlight-slide__img">
                        <span class="spotlight-badge ${badgeClass}">${badgeLabel}</span>
                    </div>
                    <div class="spotlight-slide__content">
                        <div class="spotlight-slide__category">${escapeHtml(prod.category_name || 'Marketplace')}</div>
                        <h3 class="spotlight-slide__title">${escapeHtml(prod.title)}</h3>
                        <div class="spotlight-slide__meta">
                            <div class="spotlight-slide__price-wrap">
                                <span class="spotlight-slide__price">${escapeHtml(prod.price_formatted)}</span>
                                ${prod.original_price_formatted ? `<span class="spotlight-slide__original-price">${escapeHtml(prod.original_price_formatted)}</span>` : ''}
                            </div>
                            <span class="spotlight-slide__seller">Seller: @${escapeHtml(prod.seller_name || 'Student')}</span>
                        </div>
                        <a href="${prod.url}" class="btn btn-primary spotlight-slide__cta">
                            <span>View Listing</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            `;
        }).join('');

        const dotsHtml = products.map((_, idx) => `
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
                    ${products.length > 1 ? `
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
                    ${products.length > 1 ? `<div class="spotlight-dots">${dotsHtml}</div>` : '<div></div>'}
                    <button type="button" class="spotlight-ghost-dismiss" id="spotlight-ghost-btn">Maybe later</button>
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
            if (products.length <= 1) return;
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
