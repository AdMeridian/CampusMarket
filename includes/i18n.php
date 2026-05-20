<?php
// ============================================================
// CampusMarket — Internationalization (i18n) Engine
// ============================================================

/**
 * Global locale state — set once during bootstrap, used everywhere.
 */
$_CAMPUS_I18N = [
    'locale'       => DEFAULT_LANGUAGE,
    'strings'      => [],
    'fallback'     => [],
];

/**
 * Initialize the i18n system.
 * Call once during bootstrap after constants are loaded.
 */
function i18nInit(string $locale = ''): void {
    global $_CAMPUS_I18N;

    if ($locale === '' || !array_key_exists($locale, SUPPORTED_LANGUAGES)) {
        $locale = DEFAULT_LANGUAGE;
    }

    $_CAMPUS_I18N['locale'] = $locale;

    // Load requested locale
    $_CAMPUS_I18N['strings'] = i18nLoadFile($locale);

    // Load English fallback if not already English
    if ($locale !== 'en') {
        $_CAMPUS_I18N['fallback'] = i18nLoadFile('en');
    }
}

/**
 * Load a locale JSON file from /public/lang/{locale}.json
 */
function i18nLoadFile(string $locale): array {
    $path = ROOT_PATH . 'public/lang/' . basename($locale) . '.json';
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Get the current locale code (e.g., 'en', 'tr').
 */
function i18nGetLocale(): string {
    global $_CAMPUS_I18N;
    return $_CAMPUS_I18N['locale'];
}

/**
 * Set the current locale.
 */
function i18nSetLocale(string $locale): void {
    global $_CAMPUS_I18N;
    if (array_key_exists($locale, SUPPORTED_LANGUAGES)) {
        i18nInit($locale);
    }
}

/**
 * Translate a key to the current locale's string.
 * Supports parameter interpolation: __('chat.translated_from', ['lang' => 'Turkish'])
 * Falls back to English, then to the key itself.
 *
 * @param string $key    Dot-notation key (e.g., 'nav.browse')
 * @param array  $params Replacement parameters (e.g., ['lang' => 'Turkish'])
 * @return string
 */
function __(?string $key, array $params = []): string {
    if ($key === null || $key === '') {
        return '';
    }

    global $_CAMPUS_I18N;

    // Look up in current locale, then fallback, then use the key itself
    $str = $_CAMPUS_I18N['strings'][$key]
        ?? $_CAMPUS_I18N['fallback'][$key]
        ?? $key;

    // Interpolate parameters: {param_name} → value
    if (!empty($params)) {
        foreach ($params as $name => $value) {
            $str = str_replace('{' . $name . '}', (string)$value, $str);
        }
    }

    return $str;
}

/**
 * Get the human-readable name of a language code.
 */
function getLanguageName(string $code): string {
    return SUPPORTED_LANGUAGES[$code] ?? $code;
}

/**
 * Get all loaded strings for the current locale (used for JS injection).
 */
function i18nGetAllStrings(): array {
    global $_CAMPUS_I18N;
    return $_CAMPUS_I18N['strings'];
}

/**
 * Detect user's preferred language from Accept-Language header.
 * Returns a supported language code or the default.
 */
function i18nDetectFromBrowser(): string {
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (empty($acceptLang)) {
        return DEFAULT_LANGUAGE;
    }

    // Parse Accept-Language header
    $langs = [];
    foreach (explode(',', $acceptLang) as $part) {
        $part = trim($part);
        $bits = explode(';', $part);
        $code = strtolower(trim($bits[0]));
        $q = 1.0;
        if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) {
            $q = (float)$m[1];
        }
        $langs[$code] = $q;
    }

    arsort($langs);

    foreach ($langs as $code => $q) {
        // Check exact match first (e.g., 'tr', 'en')
        $short = substr($code, 0, 2);
        if (array_key_exists($short, SUPPORTED_LANGUAGES)) {
            return $short;
        }
    }

    return DEFAULT_LANGUAGE;
}

/**
 * Translate a category name dynamically based on predefined mappings.
 */
function translateCategory(string $name): string {
    $clean = strtolower($name);
    $clean = str_replace('&', 'and', $clean);
    $clean = preg_replace('/[^a-z0-9\s]/', '', $clean);
    $clean = preg_replace('/\s+/', '_', trim($clean));
    
    $map = [
        'electronics_and_accessories' => 'category.electronics_accessories',
        'electronics_accessories' => 'category.electronics_accessories',
        'books_and_study_materials' => 'category.books_study_materials',
        'books_study_materials' => 'category.books_study_materials',
        'furniture' => 'category.furniture',
        'clothing_and_fashion' => 'category.clothing_fashion',
        'clothing_fashion' => 'category.clothing_fashion',
        'kitchen_essentials' => 'category.kitchen_essentials',
        'health_and_personal_care' => 'category.health_personal_care',
        'health_personal_care' => 'category.health_personal_care',
        'food_and_beverages' => 'category.food_beverages',
        'food_beverages' => 'category.food_beverages',
        'stationery_and_study_supplies' => 'category.stationery_study_supplies',
        'stationery_study_supplies' => 'category.stationery_study_supplies',
        'dorms_and_living_essentials' => 'category.dorm_living_essentials',
        'dorm_and_living_essentials' => 'category.dorm_living_essentials',
        'dorm_living_essentials' => 'category.dorm_living_essentials',
        'transportation' => 'category.transportation',
        'transportation_bikes_and_scooters' => 'category.transportation',
        'transportation_bikes_scooters' => 'category.transportation',
    ];
    
    $key = $map[$clean] ?? 'category.' . $clean;
    $translated = __($key);
    
    if ($translated === $key) {
        return $name;
    }
    return $translated;
}
