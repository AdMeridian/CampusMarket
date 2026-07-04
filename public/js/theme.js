// public/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    function toggleTheme() {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    }

    document.querySelectorAll('#theme-toggle, #theme-toggle-mobile').forEach((btn) => {
        btn.addEventListener('click', toggleTheme);
    });

    // Navbar Scroll Effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.classList.add('navbar--scrolled');
            } else {
                navbar.classList.remove('navbar--scrolled');
            }
        });
    }
});
