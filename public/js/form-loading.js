/**
 * Disables submit buttons and shows loading text while slow forms post.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.js-form-loading').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (form.dataset.submitting === '1') {
                return;
            }
            form.dataset.submitting = '1';

            const btn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!btn || btn.disabled) {
                return;
            }

            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');

            const loadingText =
                form.getAttribute('data-loading-text') ||
                btn.getAttribute('data-loading-text');

            if (loadingText) {
                if (btn.tagName === 'INPUT') {
                    btn.dataset.originalLabel = btn.value;
                    btn.value = loadingText;
                } else {
                    btn.dataset.originalLabel = btn.textContent;
                    btn.textContent = loadingText;
                }
            }
        });
    });
});
