<?php
// includes/web_push.php

/**
 * Best-effort web push trigger.
 * Uses Vercel serverless function (/api/web-push/send) to send pushes.
 * Safe to call from request path; it fails silently on local/dev.
 */
function triggerWebPushBestEffort(int $userId, string $title, string $body, string $url = '/pages/notifications.php'): void {
    $internalKey = getenv('INTERNAL_PUSH_KEY') ?: '';
    if (!$internalKey) return;
    if (!function_exists('curl_init')) return;

    $base = rtrim(BASE_URL, '/');
    $endpoint = $base . '/api/web-push/send';

    $payload = json_encode([
        'userId' => $userId,
        'title' => $title,
        'body' => $body,
        'url' => $url,
    ]);

    if (!$payload) return;

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
            CURLOPT_TIMEOUT => 2,
        ]);
        curl_exec($ch);
    } catch (Throwable $e) {
        // Intentionally swallow; push is best-effort.
        return;
    }
}

