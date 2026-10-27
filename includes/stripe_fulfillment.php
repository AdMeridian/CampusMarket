<?php
/**
 * Stripe Checkout fulfillment helpers.
 */

if (!function_exists('resolvePromotionPayment')) {
    /**
     * Map a posted promotion amount to an allowed tier (server-side source of truth).
     */
    function resolvePromotionPayment(float $amount): ?array {
        $tiers = [
            50.0  => 3,
            100.0 => 6,
            200.0 => 13,
        ];

        foreach ($tiers as $tierAmount => $days) {
            if (abs($amount - $tierAmount) < 0.01) {
                return ['amount' => $tierAmount, 'days' => $days];
            }
        }

        if ($amount >= 15.0 && $amount <= 5000.0) {
            return [
                'amount' => round($amount, 2),
                'days' => max(1, (int) floor($amount / 15)),
            ];
        }

        return null;
    }
}

if (!function_exists('resolveDonationPayment')) {
    function resolveDonationPayment(float $amount): ?float {
        if ($amount < 1.0 || $amount > 10000.0) {
            return null;
        }
        return round($amount, 2);
    }
}

if (!function_exists('fulfillStripeCheckoutSession')) {
    /**
     * Record a paid Stripe Checkout session and apply promotion / service listing side effects.
     *
     * @return array{ok:bool,already_processed?:bool,error?:string,payment_type?:string,product_id?:?int}
     */
    function fulfillStripeCheckoutSession(PDO $pdo, array $session): array {
        if (($session['payment_status'] ?? '') !== 'paid') {
            return ['ok' => false, 'error' => 'Payment not completed.'];
        }

        $sessionId = (string) ($session['id'] ?? '');
        if ($sessionId === '') {
            return ['ok' => false, 'error' => 'Missing session id.'];
        }

        $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $userId = (int) ($meta['user_id'] ?? 0);
        $productId = !empty($meta['product_id']) ? (int) $meta['product_id'] : null;
        $paymentType = sanitize((string) ($meta['payment_type'] ?? 'promotion'));
        $amount = (float) ($meta['amount'] ?? 0);
        $promotionDays = (int) ($meta['promotion_days'] ?? 0);

        if ($userId <= 0 || $amount <= 0) {
            return ['ok' => false, 'error' => 'Invalid payment metadata.'];
        }
        if (!in_array($paymentType, ['promotion', 'donation'], true)) {
            return ['ok' => false, 'error' => 'Unsupported payment type.'];
        }

        $check = $pdo->prepare('SELECT id FROM promotion_payments WHERE transaction_ref = ?');
        $check->execute([$sessionId]);
        if ($check->fetch()) {
            return ['ok' => true, 'already_processed' => true, 'payment_type' => $paymentType, 'product_id' => $productId];
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $pdo->beginTransaction();
        try {
            if ($productId) {
                $productStmt = $pdo->prepare('SELECT user_id, listing_type, status FROM products WHERE id = ? FOR UPDATE');
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product || (int)$product['user_id'] !== $userId) {
                    throw new RuntimeException('Payment product ownership could not be verified.');
                }
                if ($paymentType === 'promotion' && ($product['status'] !== 'active' || $product['listing_type'] === 'service')) {
                    throw new RuntimeException('Listing is not eligible for promotion.');
                }
            }

            $ins = $pdo->prepare("
                INSERT INTO promotion_payments
                    (user_id, product_id, payment_type, payment_method, amount, transaction_ref, status, approved_at, notes)
                VALUES
                    (:uid, :pid, :ptype, 'other', :amount, :tx, 'approved', NOW(), 'Automated Stripe payment')
            ");
            $ins->execute([
                ':uid' => $userId,
                ':pid' => $productId,
                ':ptype' => $paymentType,
                ':amount' => $amount,
                ':tx' => $sessionId,
            ]);

            if ($paymentType === 'promotion' && $productId) {
                $days = $promotionDays > 0 ? $promotionDays : max(1, (int) floor($amount / 15));
                $updSql = ($driver === 'pgsql')
                    ? "UPDATE products
                       SET is_featured = TRUE,
                           discount_set_at = NOW(),
                           featured_until = NOW() + (CAST(? AS text) || ' days')::interval
                       WHERE id = ? AND user_id = ? AND status = 'active'"
                    : "UPDATE products
                       SET is_featured = 1,
                           discount_set_at = NOW(),
                           featured_until = DATE_ADD(NOW(), INTERVAL ? DAY)
                       WHERE id = ? AND user_id = ? AND status = 'active'";

                $upd = $pdo->prepare($updSql);
                $upd->execute([$days, $productId, $userId]);
                if ($upd->rowCount() === 0) {
                    throw new RuntimeException('Listing is not active and cannot be promoted yet.');
                }
            }

            $pdo->commit();
            if ($paymentType === 'promotion' && $productId) {
                triggerFeaturedListingAlert($pdo, (int)$productId);
            }
            return ['ok' => true, 'already_processed' => false, 'payment_type' => $paymentType, 'product_id' => $productId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $duplicateCheck = $pdo->prepare('SELECT id FROM promotion_payments WHERE transaction_ref = ?');
            $duplicateCheck->execute([$sessionId]);
            if ($duplicateCheck->fetchColumn()) {
                return ['ok' => true, 'already_processed' => true, 'payment_type' => $paymentType, 'product_id' => $productId];
            }
            error_log('[stripe_fulfillment] ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('fetchStripeCheckoutSession')) {
    function fetchStripeCheckoutSession(string $sessionId): ?array {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || !defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
            CURLOPT_TIMEOUT => 20,
        ]);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            return null;
        }

        $decoded = json_decode((string) $result, true);
        return is_array($decoded) ? $decoded : null;
    }
}
