/**
 * CampusMarket — Client-side i18n Helper
 * Loaded from PHP-injected window.__i18n data.
 */
(function() {
    'use strict';

    const strings = window.__i18n || {};
    const locale = window.__locale || 'en';
    const languages = window.__languages || { en: 'English', tr: 'Türkçe' };

    /**
     * Translate a key with optional parameter interpolation.
     * @param {string} key - Dot-notation key (e.g., 'chat.translated_from')
     * @param {Object} params - Replacement parameters (e.g., {lang: 'Turkish'})
     * @returns {string}
     */
    function __(key, params) {
        let str = strings[key] || key;
        if (params) {
            Object.keys(params).forEach(function(name) {
                str = str.replace(new RegExp('\\{' + name + '\\}', 'g'), params[name]);
            });
        }
        return str;
    }

    /**
     * Get the human-readable name of a language code.
     */
    function getLangName(code) {
        return languages[code] || code;
    }

    /**
     * Get the current locale code.
     */
    function getLocale() {
        return locale;
    }

    /**
     * Change the user's preferred language.
     * POSTs to the API and reloads the page on success.
     */
    function setLanguage(langCode) {
        const formData = new FormData();
        formData.append('action', 'set_language');
        formData.append('language', langCode);
        formData.append('csrf_token', window.__csrfToken || '');

        fetch(window.__baseUrl + 'pages/api_messages.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                console.error('Language change failed:', data.error);
            }
        })
        .catch(err => {
            console.error('Language change error:', err);
        });
    }

    // Export globally
    window.CampusMarketI18n = {
        __: __,
        getLangName: getLangName,
        getLocale: getLocale,
        setLanguage: setLanguage,
        strings: strings,
        languages: languages
    };

    // Convenience global
    window.__ = __;
})();
