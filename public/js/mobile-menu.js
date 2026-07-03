/**
 * mobile-menu.js
 * Handles the mobile menu toggle and theme toggle on mobile.
 */
function initMobileMenu() {
    if (document.body.dataset.mobileMenuInitialized === 'true') return;
    document.body.dataset.mobileMenuInitialized = 'true';

    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const navLinks = document.getElementById('nav-links');
    const mobileMenuCloseBtn = document.getElementById('mobile-menu-close');
    const themeToggleMobile = document.getElementById('theme-toggle-mobile');
    const mobileMq = window.matchMedia('(max-width: 1023px)');

    function isMobileNav() {
        return mobileMq.matches;
    }

    function setMobileNavOpen(isOpen) {
        if (!navLinks) return;

        navLinks.classList.toggle('active', isOpen);

        if (isMobileNav()) {
            navLinks.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.style.overflow = isOpen ? 'hidden' : '';
        } else {
            navLinks.removeAttribute('aria-hidden');
            document.body.style.overflow = '';
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            const svg = mobileMenuBtn.querySelector('svg');
            if (svg) {
                svg.innerHTML = isOpen
                    ? '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'
                    : '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>';
            }
        }
    }

    function closeMobileNav() {
        setMobileNavOpen(false);
    }

    function openMobileNav() {
        setMobileNavOpen(true);
    }

    function syncMobileNavState() {
        if (!navLinks) return;

        if (isMobileNav()) {
            const isOpen = navLinks.classList.contains('active');
            navLinks.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            if (mobileMenuBtn) {
                mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        } else {
            navLinks.classList.remove('active');
            navLinks.removeAttribute('aria-hidden');
            document.body.style.overflow = '';
            if (mobileMenuBtn) {
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
            }
        }
    }

    syncMobileNavState();
    if (typeof mobileMq.addEventListener === 'function') {
        mobileMq.addEventListener('change', syncMobileNavState);
    } else if (typeof mobileMq.addListener === 'function') {
        mobileMq.addListener(syncMobileNavState);
    }

    if (mobileMenuCloseBtn && navLinks && mobileMenuBtn) {
        mobileMenuCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeMobileNav();
        });
    }

    const userDropdownBtns = document.querySelectorAll('.user-dropdown-btn');

    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (navLinks.classList.contains('active')) {
                closeMobileNav();
            } else {
                openMobileNav();
            }
        });
    }

    if (themeToggleMobile) {
        themeToggleMobile.addEventListener('click', function() {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.click();
            }
        });
    }

    if (userDropdownBtns.length) {
        userDropdownBtns.forEach((btn) => {
            btn.addEventListener('click', function(event) {
                if (isMobileNav()) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const dropdown = this.closest('.user-dropdown');
                if (!dropdown) return;

                const isOpen = dropdown.classList.contains('active');
                document.querySelectorAll('.user-dropdown.active').forEach((openDropdown) => {
                    openDropdown.classList.remove('active');
                    const openBtn = openDropdown.querySelector('.user-dropdown-btn');
                    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
                });

                if (!isOpen) {
                    dropdown.classList.add('active');
                    this.setAttribute('aria-expanded', 'true');
                } else {
                    dropdown.classList.remove('active');
                    this.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    if (navLinks) {
        navLinks.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', function() {
                if (isMobileNav()) {
                    closeMobileNav();
                }
            });
        });
    }

    document.addEventListener('click', function(event) {
        if (navLinks && mobileMenuBtn && !navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target) && navLinks.classList.contains('active')) {
            closeMobileNav();
        }

        document.querySelectorAll('.user-dropdown.active').forEach((openDropdown) => {
            if (!openDropdown.contains(event.target)) {
                openDropdown.classList.remove('active');
                const openBtn = openDropdown.querySelector('.user-dropdown-btn');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;

        if (navLinks && navLinks.classList.contains('active') && isMobileNav()) {
            closeMobileNav();
            return;
        }

        document.querySelectorAll('.user-dropdown.active').forEach((openDropdown) => {
            openDropdown.classList.remove('active');
            const openBtn = openDropdown.querySelector('.user-dropdown-btn');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenu);
} else {
    initMobileMenu();
}
