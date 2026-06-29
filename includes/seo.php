<?php
/**
 * SEO helpers — meta tags, canonical URLs, robots, Open Graph, JSON-LD.
 *
 * Pages may set before including header.php:
 *   $pageTitle, $pageDescription, $seoCanonical, $seoNoindex (bool),
 *   $seoOgImage, $seoOgType ('website'|'product'), $seoJsonLd (array),
 *   $seoOgTitle, $seoOgDescription (optional overrides for social previews)
 */

if (!function_exists('seoFullTitle')) {
    function seoFullTitle(?string $pageTitle): string {
        if ($pageTitle === null || trim($pageTitle) === '') {
            return 'CampusMarket';
        }
        $pageTitle = trim($pageTitle);
        if (stripos($pageTitle, 'CampusMarket') !== false) {
            return $pageTitle;
        }
        return $pageTitle . ' - CampusMarket';
    }
}

if (!function_exists('seoDefaultDescription')) {
    function seoDefaultDescription(): string {
        return function_exists('__')
            ? __('seo.default_description')
            : 'Buy and sell used items with verified students and staff on North Cyprus university campuses.';
    }
}

if (!function_exists('seoPageDescription')) {
    function seoPageDescription(): string {
        global $pageDescription;
        $desc = isset($pageDescription) ? trim((string)$pageDescription) : '';
        if ($desc === '') {
            return seoDefaultDescription();
        }
        return mb_strlen($desc) > 160 ? mb_substr($desc, 0, 157) . '...' : $desc;
    }
}

if (!function_exists('seoOpenGraphTitle')) {
    function seoOpenGraphTitle(): string {
        global $seoOgTitle, $pageTitle;
        if (!empty($seoOgTitle)) {
            return trim((string)$seoOgTitle);
        }
        return seoFullTitle($pageTitle ?? null);
    }
}

if (!function_exists('seoOpenGraphDescription')) {
    function seoOpenGraphDescription(): string {
        global $seoOgDescription;
        if (!empty($seoOgDescription)) {
            $desc = trim((string)$seoOgDescription);
        } else {
            $desc = seoPageDescription();
        }
        return mb_strlen($desc) > 200 ? mb_substr($desc, 0, 197) . '...' : $desc;
    }
}

if (!function_exists('seoProductShareDescription')) {
    /**
     * Marketing-focused line for link previews when sharing a listing.
     */
    function seoProductShareDescription(array $product): string {
        $title = trim((string)($product['title'] ?? ''));
        $price = function_exists('formatPrice') && function_exists('getDiscountedPrice')
            ? formatPrice(getDiscountedPrice($product))
            : '';

        $details = [];
        if (!empty($product['location_town'])
            && function_exists('isValidLocationTown')
            && isValidLocationTown($product['location_town'])
            && $product['location_town'] !== 'other'
        ) {
            $details[] = formatLocationTown($product['location_town']);
        }
        if (!empty($product['category_name'])) {
            $details[] = function_exists('translateCategory')
                ? translateCategory($product['category_name'])
                : (string)$product['category_name'];
        }
        if (function_exists('conditionBadge')) {
            $badge = conditionBadge($product['condition'] ?? 'used');
            $details[] = $badge['label'] ?? '';
        }
        $details = array_values(array_filter($details));

        $headline = $title;
        if ($price !== '') {
            $headline .= ' — ' . $price;
        }

        $desc = $headline;
        if ($details !== []) {
            $desc .= ' · ' . implode(' · ', $details);
        }
        $desc .= '. ' . __('seo.share_listing_suffix');

        return mb_strlen($desc) > 200 ? mb_substr($desc, 0, 197) . '...' : $desc;
    }
}

if (!function_exists('seoNormalizePath')) {
    function seoNormalizePath(string $path): string {
        $path = '/' . trim($path, '/');
        if ($path !== '/' && str_ends_with(strtolower($path), '.php')) {
            $path = substr($path, 0, -4);
        }
        return $path === '' ? '/' : $path;
    }
}

if (!function_exists('seoCanonicalUrl')) {
    function seoCanonicalUrl(): string {
        global $seoCanonical;
        if (!empty($seoCanonical)) {
            return (string)$seoCanonical;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = seoNormalizePath($path);

        $query = [];
        parse_str(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '', $query);
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content'], $query['fbclid'], $query['gclid']);

        $allowedKeys = ['id', 'category'];
        $filtered = array_intersect_key($query, array_flip($allowedKeys));

        $base = rtrim(BASE_URL, '/') . ($path === '/' ? '/' : $path);
        if ($filtered !== []) {
            return $base . '?' . http_build_query($filtered);
        }
        return $base;
    }
}

if (!function_exists('seoIsPrivatePage')) {
    function seoIsPrivatePage(): bool {
        global $seoNoindex;
        if (isset($seoNoindex)) {
            return (bool)$seoNoindex;
        }

        $script = strtolower($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');

        if (str_contains($script, '/admin/')) {
            return true;
        }

        $privatePages = [
            'login.php', 'register.php', 'forgot_password.php', 'reset_password.php',
            'check_email.php', 'verify_email.php', 'logout.php',
            'inbox.php', 'messages.php', 'notifications.php', 'wishlist.php',
            'my_orders.php', 'my_reports.php', 'edit_profile.php', 'create_listing.php',
            'recycle_bin.php', 'place_order.php', 'promotions.php', 'stripe_success.php',
            'create_stripe_session.php', 'report.php',
        ];

        foreach ($privatePages as $page) {
            if (str_ends_with($script, $page)) {
                return true;
            }
        }

        if (str_ends_with($script, 'profile.php') && (int)($_GET['id'] ?? 0) <= 0) {
            return true;
        }

        return false;
    }
}

if (!function_exists('seoOgImage')) {
    function seoOgImage(): string {
        global $seoOgImage;
        if (!empty($seoOgImage)) {
            return (string)$seoOgImage;
        }
        return rtrim(BASE_URL, '/') . '/public/images/logo.png';
    }
}

if (!function_exists('seoOgType')) {
    function seoOgType(): string {
        global $seoOgType;
        return !empty($seoOgType) ? (string)$seoOgType : 'website';
    }
}

if (!function_exists('seoJsonLdBlocks')) {
    /** @return array<int, array<string, mixed>> */
    function seoJsonLdBlocks(): array {
        global $seoJsonLd;
        if (empty($seoJsonLd)) {
            return [];
        }
        if (isset($seoJsonLd['@context'])) {
            return [$seoJsonLd];
        }
        return is_array($seoJsonLd) ? $seoJsonLd : [];
    }
}

if (!function_exists('seoRenderHeadTags')) {
    function seoRenderHeadTags(): void {
        $title = seoFullTitle($GLOBALS['pageTitle'] ?? null);
        $ogTitle = seoOpenGraphTitle();
        $description = seoPageDescription();
        $ogDescription = seoOpenGraphDescription();
        $canonical = seoCanonicalUrl();
        $noindex = seoIsPrivatePage();
        $ogImage = seoOgImage();
        $ogType = seoOgType();
        $locale = function_exists('i18nGetLocale') ? i18nGetLocale() : 'en';
        $ogLocale = $locale === 'tr' ? 'tr_TR' : 'en_US';

        echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        if ($noindex) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        } else {
            echo '<meta name="robots" content="index, follow">' . "\n";
        }

        echo '<meta property="og:site_name" content="CampusMarket">' . "\n";
        echo '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:locale" content="' . htmlspecialchars($ogLocale, ENT_QUOTES, 'UTF-8') . '">' . "\n";

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '">' . "\n";

        foreach (seoJsonLdBlocks() as $block) {
            $json = json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
            }
        }
    }
}

if (!function_exists('seoProductJsonLd')) {
    function seoProductJsonLd(array $product, string $imageUrl, int $productId): array {
        $price = (float)($product['price'] ?? 0);
        $availability = ($product['status'] ?? '') === 'active'
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string)($product['title'] ?? ''),
            'description' => mb_substr(strip_tags((string)($product['description'] ?? '')), 0, 500),
            'image' => [$imageUrl],
            'offers' => [
                '@type' => 'Offer',
                'url' => rtrim(BASE_URL, '/') . '/pages/product?id=' . $productId,
                'priceCurrency' => defined('APP_CURRENCY') ? APP_CURRENCY : 'TRY',
                'price' => number_format($price, 2, '.', ''),
                'availability' => $availability,
            ],
        ];
    }
}

if (!function_exists('seoWebsiteJsonLd')) {
    function seoWebsiteJsonLd(): array {
        $base = rtrim(BASE_URL, '/');
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'CampusMarket',
            'url' => $base . '/',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $base . '/pages/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
