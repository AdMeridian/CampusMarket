<?php
/**
 * Pending order expiry: 24h seller reminder, auto-cancel after TTL.
 */

if (!function_exists('pendingOrderTtlDays')) {
    function pendingOrderTtlDays(): int {
        $days = (int) (getenv('PENDING_ORDER_TTL_DAYS') ?: (defined('PENDING_ORDER_TTL_DAYS') ? PENDING_ORDER_TTL_DAYS : 7));
        return max(1, min(30, $days));
    }
}

if (!function_exists('pendingOrderReminderHours')) {
    function pendingOrderReminderHours(): int {
        $hours = (int) (getenv('PENDING_ORDER_REMINDER_HOURS') ?: (defined('PENDING_ORDER_REMINDER_HOURS') ? PENDING_ORDER_REMINDER_HOURS : 24));
        return max(1, min(168, $hours));
    }
}

if (!function_exists('pendingOrderExpiresAtExpression')) {
    /** SQL expression for expires_at on insert (Postgres). */
    function pendingOrderExpiresAtExpression(): string {
        $days = pendingOrderTtlDays();
        return "NOW() + INTERVAL '{$days} days'";
    }
}

if (!function_exists('processPendingOrderExpiry')) {
    /**
     * Send seller reminders and auto-cancel expired pending orders.
     *
     * @return array{reminders_sent:int,orders_cancelled:int}
     */
    function processPendingOrderExpiry(PDO $pdo): array {
        $remindersSent = 0;
        $ordersCancelled = 0;
        $reminderHours = pendingOrderReminderHours();
        $isPostgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        $reminderSql = $isPostgres
            ? "
                SELECT o.id, o.buyer_id, o.product_id, p.user_id AS seller_id, p.title AS product_title
                FROM orders o
                JOIN products p ON p.id = o.product_id
                WHERE o.status = 'pending'
                  AND o.expiry_reminder_sent_at IS NULL
                  AND o.expires_at IS NOT NULL
                  AND o.expires_at > NOW()
                  AND o.expires_at <= NOW() + INTERVAL '{$reminderHours} hours'
            "
            : "
                SELECT o.id, o.buyer_id, o.product_id, p.user_id AS seller_id, p.title AS product_title
                FROM orders o
                JOIN products p ON p.id = o.product_id
                WHERE o.status = 'pending'
                  AND o.expiry_reminder_sent_at IS NULL
                  AND o.expires_at IS NOT NULL
                  AND o.expires_at > NOW()
                  AND o.expires_at <= DATE_ADD(NOW(), INTERVAL {$reminderHours} HOUR)
            ";

        $expiredSql = "
            SELECT o.id, o.buyer_id, o.product_id, p.user_id AS seller_id, p.title AS product_title
            FROM orders o
            JOIN products p ON p.id = o.product_id
            WHERE o.status = 'pending'
              AND o.expires_at IS NOT NULL
              AND o.expires_at <= NOW()
        ";

        $reminderStmt = $pdo->query($reminderSql);
        $reminders = $reminderStmt ? $reminderStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($reminders as $row) {
            $orderId = (int) $row['id'];
            $sellerId = (int) $row['seller_id'];
            $title = (string) $row['product_title'];

            try {
                $pdo->beginTransaction();

                $lock = $pdo->prepare("
                    SELECT id FROM orders
                    WHERE id = :id AND status = 'pending' AND expiry_reminder_sent_at IS NULL
                    FOR UPDATE
                ");
                $lock->execute([':id' => $orderId]);
                if (!$lock->fetchColumn()) {
                    $pdo->rollBack();
                    continue;
                }

                $pdo->prepare("UPDATE orders SET expiry_reminder_sent_at = NOW(), updated_at = NOW() WHERE id = :id")
                    ->execute([':id' => $orderId]);

                createNotification(
                    $pdo,
                    $sellerId,
                    'order',
                    __('orders.expiry_reminder_title'),
                    __('orders.expiry_reminder_body', ['title' => $title, 'hours' => $reminderHours]),
                    $orderId
                );

                $pdo->commit();
                $remindersSent++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[order_expiry] reminder failed for order ' . $orderId . ': ' . $e->getMessage());
            }
        }

        $expiredStmt = $pdo->query($expiredSql);
        $expired = $expiredStmt ? $expiredStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($expired as $row) {
            $orderId = (int) $row['id'];
            $buyerId = (int) $row['buyer_id'];
            $sellerId = (int) $row['seller_id'];
            $productId = (int) $row['product_id'];
            $title = (string) $row['product_title'];
            $days = pendingOrderTtlDays();

            try {
                $pdo->beginTransaction();

                $lock = $pdo->prepare("
                    SELECT id FROM orders
                    WHERE id = :id AND status = 'pending'
                    FOR UPDATE
                ");
                $lock->execute([':id' => $orderId]);
                if (!$lock->fetchColumn()) {
                    $pdo->rollBack();
                    continue;
                }

                $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :id")
                    ->execute([':id' => $orderId]);

                $pdo->prepare("DELETE FROM deal_confirmations WHERE product_id = :pid AND buyer_id = :bid")
                    ->execute([':pid' => $productId, ':bid' => $buyerId]);

                createNotification(
                    $pdo,
                    $buyerId,
                    'system',
                    __('orders.expired_buyer_title'),
                    __('orders.expired_buyer_body', ['title' => $title, 'days' => $days]),
                    $orderId
                );

                createNotification(
                    $pdo,
                    $sellerId,
                    'system',
                    __('orders.expired_seller_title'),
                    __('orders.expired_seller_body', ['title' => $title, 'days' => $days]),
                    $orderId
                );

                $pdo->commit();
                $ordersCancelled++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[order_expiry] cancel failed for order ' . $orderId . ': ' . $e->getMessage());
            }
        }

        return [
            'reminders_sent' => $remindersSent,
            'orders_cancelled' => $ordersCancelled,
        ];
    }
}
