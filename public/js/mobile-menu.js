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
            navLinks.classList.toggle('active');
            
            // Change icon if needed
            const svg = mobileMenuBtn.querySelector('svg');
            if (navLinks.classList.contains('active')) {
                svg.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
            } else {
                svg.innerHTML = '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>';
            }
        });
    }

    // Connect mobile theme toggle to the existing theme toggle logic
    if (themeToggleMobile) {
        themeToggleMobile.addEventListener('click', function() {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.click();
            }
        });
    }

    // Handle user dropdown on click (desktop only; mobile panel is always visible)
    if (userDropdownBtns.length) {
        userDropdownBtns.forEach((btn) => {
            btn.addEventListener('click', function(event) {
                if (window.matchMedia('(max-width: 1023px)').matches) {
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

    function closeMobileNav() {
        if (!navLinks || !mobileMenuBtn) return;
        navLinks.classList.remove('active');
        const svg = mobileMenuBtn.querySelector('svg');
        if (svg) {
            svg.innerHTML = '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>';
        }
    }

    if (navLinks) {
        navLinks.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', function() {
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    closeMobileNav();
                }
            });
        });
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navLinks && mobileMenuBtn && !navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target) && navLinks.classList.contains('active')) {
            closeMobileNav();
        }

        // Close user dropdown(s) if clicking outside
        document.querySelectorAll('.user-dropdown.active').forEach((openDropdown) => {
            if (!openDropdown.contains(event.target)) {
                openDropdown.classList.remove('active');
                const openBtn = openDropdown.querySelector('.user-dropdown-btn');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Close dropdown with Escape
    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;

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
