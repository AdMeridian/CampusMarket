<?php
/**
 * Listing moderation helpers: admin alerts for pending approvals.
 */

if (!function_exists('notifyAdminsPendingListing')) {
    function notifyAdminsPendingListing(PDO $pdo, int $productId, string $title, string $aiReason = ''): void {
        if ($productId <= 0 || trim($title) === '') {
            return;
        }

        $title = trim($title);
        $reasonSnippet = trim($aiReason);
        if (strlen($reasonSnippet) > 200) {
            $reasonSnippet = substr($reasonSnippet, 0, 197) . '...';
        }

        $notifTitle = 'Listing pending approval';
        $notifBody = "New listing \"{$title}\" needs your review before it goes live.";
        if ($reasonSnippet !== '') {
            $notifBody .= ' Moderation note: ' . $reasonSnippet;
        }

        $listingsUrl = rtrim(BASE_URL, '/') . '/admin/listings.php?status=pending_approval';

        try {
            $stmt = $pdo->query("
                SELECT id, username, email, is_verified
                FROM users
                WHERE role = 'admin'
                ORDER BY id ASC
            ");
            $admins = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            require_once __DIR__ . '/mailer.php';

            foreach ($admins as $admin) {
                $adminId = (int)($admin['id'] ?? 0);
                if ($adminId <= 0) {
                    continue;
                }
                createNotification($pdo, $adminId, 'system', $notifTitle, $notifBody, $productId);

                if (!empty($admin['email']) && (bool)($admin['is_verified'] ?? false)) {
                    sendMarketplaceAlertEmail(
                        (string)$admin['email'],
                        (string)($admin['username'] ?? 'Admin'),
                        $notifTitle . ' — CampusMarket',
                        $notifTitle,
                        $notifBody,
                        $listingsUrl,
                        'Review listings'
                    );
                }
            }

            $supportEmail = defined('SUPPORT_EMAIL')
                ? SUPPORT_EMAIL
                : (getenv('SUPPORT_EMAIL') ?: '');
            if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
                $html = '<h2>' . htmlspecialchars($notifTitle) . '</h2>';
                $html .= '<p>' . htmlspecialchars($notifBody) . '</p>';
                $html .= '<p><a href="' . htmlspecialchars($listingsUrl) . '">Open moderation queue</a></p>';
                $html .= '<p>Listing ID: ' . (int)$productId . '</p>';
                sendEmail($supportEmail, $notifTitle . ' — CampusMarket', $html);
            }
        } catch (Throwable $e) {
            error_log('[listing_moderation] notifyAdminsPendingListing failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('normalizeListingTitle')) {
    function normalizeListingTitle(string $title): string {
        $normalized = mb_strtolower(trim($title), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }
}

if (!function_exists('findSellerDuplicateListing')) {
    /**
     * Detect an active/pending listing from the same seller with the same normalized title.
     */
    function findSellerDuplicateListing(PDO $pdo, int $userId, string $title, int $excludeProductId = 0): ?array {
        if ($userId <= 0) {
            return null;
        }
        $normalized = normalizeListingTitle($title);
        if ($normalized === '') {
            return null;
        }

        $sql = "
            SELECT id, title, status
            FROM products
            WHERE user_id = :uid
              AND status IN ('active', 'pending_approval')
              AND LOWER(TRIM(title)) = :norm
        ";
        if ($excludeProductId > 0) {
            $sql .= ' AND id != :exclude';
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $params = [':uid' => $userId, ':norm' => $normalized];
        if ($excludeProductId > 0) {
            $params[':exclude'] = $excludeProductId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('aiModeratorShouldAutoApprove')) {
    function aiModeratorShouldAutoApprove(array $aiResult): bool {
        if (!empty($aiResult['is_blurry']) || !empty($aiResult['is_duplicate'])) {
            return false;
        }
        if (empty($aiResult['passed'])) {
            return false;
        }
        $confidence = (float)($aiResult['confidence'] ?? 0);
        return $confidence >= AI_MODERATION_MIN_CONFIDENCE;
    }
}

if (!function_exists('listingModerationSanitizeSellerNote')) {
    function listingModerationSanitizeSellerNote(string $reason): string {
        $reason = trim($reason);
        if ($reason === '') {
            return '';
        }

        $prefix = 'Text-only moderation (image check unavailable):';
        if (stripos($reason, $prefix) === 0) {
            $reason = trim(substr($reason, strlen($prefix)));
        }

        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 497) . '...';
        }

        return $reason;
    }
}

if (!function_exists('listingModerationDuplicateMessage')) {
    function listingModerationDuplicateMessage(?array $duplicate): string {
        if (!$duplicate) {
            return __('create_listing.duplicate_listing');
        }

        $title = trim((string)($duplicate['title'] ?? ''));
        if ($title !== '') {
            return __('create_listing.duplicate_listing_detail', ['title' => $title]);
        }

        return __('create_listing.duplicate_listing');
    }
}

if (!function_exists('listingModerationBlurryMessage')) {
    function listingModerationBlurryMessage(array $aiResult): string {
        $detail = listingModerationSanitizeSellerNote((string)($aiResult['reason'] ?? ''));
        if ($detail !== '') {
            return __('create_listing.moderation_blurry_with_reason', ['reason' => $detail]);
        }

        return __('create_listing.moderation_blurry');
    }
}

if (!function_exists('listingModerationSellerFacingReason')) {
    function listingModerationSellerFacingReason(array $aiResult): string {
        $raw = listingModerationSanitizeSellerNote((string)($aiResult['reason'] ?? ''));
        $mode = (string)($aiResult['mode'] ?? '');

        if ($mode === 'error') {
            if (
                stripos($raw, 'No AI API keys') !== false
                || stripos($raw, 'AI API error') !== false
                || stripos($raw, 'manual moderation') !== false
            ) {
                return __('create_listing.moderation_manual_review');
            }
        }

        if ($raw !== '') {
            return $raw;
        }

        if (empty($aiResult['passed'])) {
            return __('create_listing.moderation_pending_generic');
        }

        $confidence = (float)($aiResult['confidence'] ?? 0);
        if ($confidence < AI_MODERATION_MIN_CONFIDENCE) {
            return __('create_listing.moderation_low_confidence');
        }

        return __('create_listing.moderation_pending_generic');
    }
}

if (!function_exists('listingModerationSaveNote')) {
    function listingModerationSaveNote(PDO $pdo, int $productId, string $note): void {
        if ($productId <= 0) {
            return;
        }

        $note = trim($note);
        if ($note === '') {
            return;
        }

        try {
            $stmt = $pdo->prepare('UPDATE products SET moderation_note = :note WHERE id = :id');
            $stmt->execute([':note' => $note, ':id' => $productId]);
        } catch (Throwable $e) {
            error_log('[listing_moderation] saveNote failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('notifySellerPendingListing')) {
    function notifySellerPendingListing(PDO $pdo, int $userId, int $productId, string $title, string $sellerNote): void {
        if ($userId <= 0 || $productId <= 0 || trim($title) === '') {
            return;
        }

        $title = trim($title);
        $sellerNote = trim($sellerNote);
        $notifTitle = 'Listing under review';
        $notifBody = __('create_listing.moderation_notif_body', ['title' => $title]);
        if ($sellerNote !== '') {
            $notifBody .= ' ' . __('create_listing.moderation_notif_reason', ['reason' => $sellerNote]);
        }

        try {
            createNotification($pdo, $userId, 'system', $notifTitle, $notifBody, $productId);
        } catch (Throwable $e) {
            error_log('[listing_moderation] notifySellerPendingListing failed: ' . $e->getMessage());
        }
    }
}
