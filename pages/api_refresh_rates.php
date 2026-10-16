<?php
/**
 * CampusMarket — Exchange Rate Refresher
 * Fetches live TRY-based rates from open.er-api.com and caches in DB.
 * Can be called programmatically, via cron, or via admin.
 */

if (!function_exists('refreshExchangeRates')) {
    /**
     * Refresh exchange rates from open.er-api.com and update the database.
     *
     * @param PDO $pdo
     * @param bool $force If true, bypasses any check and forces rate refresh.
     * @return array
     */
    function refreshExchangeRates(PDO $pdo, bool $force = false): array {
        try {
            $apiUrl = 'https://open.er-api.com/v6/latest/TRY';
            $response = false;

            if (function_exists('curl_init')) {
                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_USERAGENT      => 'CampusMarket/1.0 (CurrencyUpdater)',
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    $response = false;
                }
            }

            if (!$response) {
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 8,
                        'user_agent' => 'CampusMarket/1.0 (CurrencyUpdater)'
                    ]
                ]);
                $response = @file_get_contents($apiUrl, false, $ctx);
            }

            if (!$response) {
                return ['success' => false, 'error' => 'Failed to reach exchange rate API'];
            }

            $data = json_decode($response, true);
            if (!is_array($data) || ($data['result'] ?? '') !== 'success' || empty($data['rates'])) {
                return ['success' => false, 'error' => 'Invalid API response format'];
            }

            $rates = $data['rates'];
            $nextUpdate = (int)($data['time_next_update_unix'] ?? (time() + 86400));
            // Ensure next update is in the future
            if ($nextUpdate <= time()) {
                $nextUpdate = time() + 86400;
            }

            $supportedCurrencies = ['USD', 'EUR', 'GBP'];
            $updatedRates = ['TRY' => 1.0];

            $stmt = $pdo->prepare("
                INSERT INTO currency_rates (code, rate_to_try, next_update_unix, updated_at)
                VALUES (:code, :rate, :next_update, NOW())
                ON CONFLICT (code) DO UPDATE
                SET rate_to_try = EXCLUDED.rate_to_try,
                    next_update_unix = EXCLUDED.next_update_unix,
                    updated_at = NOW()
            ");

            // Ensure TRY base is recorded
            $stmt->execute([
                ':code'        => 'TRY',
                ':rate'        => 1.0000,
                ':next_update' => $nextUpdate,
            ]);

            foreach ($supportedCurrencies as $code) {
                if (!isset($rates[$code]) || (float)$rates[$code] <= 0) {
                    continue;
                }
                // API rate is relative to 1 TRY (e.g. 1 TRY = 0.0294 USD)
                // We want rate_to_try = 1 USD = 34.01 TRY
                $foreignRate = (float)$rates[$code];
                $rateToTry = round(1.0 / $foreignRate, 4);

                $stmt->execute([
                    ':code'        => $code,
                    ':rate'        => $rateToTry,
                    ':next_update' => $nextUpdate,
                ]);

                $updatedRates[$code] = $rateToTry;
            }

            return [
                'success'     => true,
                'rates'       => $updatedRates,
                'next_update' => $nextUpdate,
                'timestamp'   => time(),
            ];
        } catch (Throwable $e) {
            error_log('[api_refresh_rates] ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// If accessed directly via HTTP request
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    require_once __DIR__ . '/../includes/bootstrap.php';

    header('Content-Type: application/json; charset=utf-8');

    // Allow execution if admin or authorized cron request or local dev
    $isCron = function_exists('isAuthorizedCronRequest') && isAuthorizedCronRequest();
    $isAdmin = function_exists('isAdmin') && isAdmin();

    if (!$isCron && !$isAdmin && !defined('IS_LOCALHOST')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    $result = refreshExchangeRates($pdo, true);
    if (!$result['success']) {
        http_response_code(500);
    }
    echo json_encode($result);
    exit;
}
