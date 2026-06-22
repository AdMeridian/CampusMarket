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

if (!function_exists('aiModeratorShouldAutoApprove')) {
    function aiModeratorShouldAutoApprove(array $aiResult): bool {
        if (!empty($aiResult['is_blurry'])) {
            return false;
        }
        if (empty($aiResult['passed'])) {
            return false;
        }
        $confidence = (float)($aiResult['confidence'] ?? 0);
        return $confidence >= AI_MODERATION_MIN_CONFIDENCE;
    }
}
