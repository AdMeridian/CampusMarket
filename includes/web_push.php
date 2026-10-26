<?php
// includes/web_push.php

/**
 * Queue a web push to run after the HTTP response is sent (avoids serverless cutoff).
 */
function triggerWebPushBestEffort(int $userId, string $title, string $body, string $url = '/pages/notifications.php'): void {
    $internalKey = getenv('INTERNAL_PUSH_KEY') ?: '';
    if (!$internalKey) {
        error_log('[web-push] Cannot send push: INTERNAL_PUSH_KEY is not configured in environment.');
        return;
    }

    if (!function_exists('curl_init')) {
        error_log('[web-push] Cannot send push: cURL extension not available.');
        return;
    }

    $base = rtrim(BASE_URL, '/');
    $endpoint = $base . '/api/web-push/send';

    $payload = json_encode([
        'userId' => $userId,
        'title' => $title,
        'body' => $body,
        'url' => $url,
    ]);

    if (!$payload) {
        return;
    }

    // In serverless environments (Vercel/Lambda), dispatch synchronously with a short timeout.
    // register_shutdown_function is cut off/frozen by Lambda as soon as the HTTP response terminates.
    try {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Internal-Push-Key: ' . $internalKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => !defined('IS_LOCALHOST') || !IS_LOCALHOST,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[web-push] cURL error calling ' . $endpoint . ': ' . $curlErr);
        } elseif ($httpCode >= 400) {
            error_log('[web-push] HTTP error ' . $httpCode . ' from ' . $endpoint . ': ' . substr((string)$response, 0, 300));
        } else {
            error_log('[web-push] Push successfully dispatched to ' . $endpoint . ' for userId=' . $userId . ' (HTTP ' . $httpCode . ')');
        }
    } catch (Throwable $e) {
        error_log('[web-push] Exception dispatching push: ' . $e->getMessage());
    }
}

/**
 * Sync local marketplace user id into Supabase Auth app_metadata for Realtime RLS.
 */
function syncSupabaseAppUserMetadata(PDO $pdo, int $localUserId, string $role = 'user'): void {
    if ($localUserId <= 0 || supabaseServiceRoleKey() === '') {
        return;
    }

    $emailStmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $emailStmt->execute([$localUserId]);
    $email = $emailStmt->fetchColumn();
    if (!$email) {
        return;
    }

    $authResponse = supabaseAdminRequest('GET', 'admin/users?per_page=1000');
    if (!$authResponse['ok'] || empty($authResponse['data']['users'])) {
        return;
    }

    $supabaseUserUuid = null;
    foreach ($authResponse['data']['users'] as $su) {
        if (isset($su['email']) && strtolower((string)$su['email']) === strtolower((string)$email)) {
            $supabaseUserUuid = $su['id'] ?? null;
            break;
        }
    }

    if (!$supabaseUserUuid) {
        return;
    }

    $existingMeta = [];
    foreach ($authResponse['data']['users'] as $su) {
        if (($su['id'] ?? '') === $supabaseUserUuid) {
            $existingMeta = is_array($su['app_metadata'] ?? null) ? $su['app_metadata'] : [];
            break;
        }
    }

    $nextMeta = array_merge($existingMeta, [
        'user_id' => (string)$localUserId,
        'role' => $role,
    ]);

    if (($existingMeta['user_id'] ?? null) === (string)$localUserId && ($existingMeta['role'] ?? null) === $role) {
        return;
    }

    supabaseAdminRequest('PUT', 'admin/users/' . $supabaseUserUuid, [
        'app_metadata' => $nextMeta,
    ]);
}
