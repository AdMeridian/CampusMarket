<?php
/**
 * Invoke the Supabase chatbot Edge Function from PHP (fallback path).
 */

if (!function_exists('chatbotInvokeEdge')) {
    /**
     * @param array<int, array{role:string,parts:array<int,array{text:string}>}> $history
     * @return array<string, mixed>|null
     */
    function chatbotInvokeEdge(string $message, array $history, string $locale, string $siteBaseUrl): ?array {
        $baseUrl = rtrim(supabaseUrl(), '/');
        $serviceKey = supabaseServiceRoleKey();
        if ($baseUrl === '' || $serviceKey === '') {
            return null;
        }

        $url = $baseUrl . '/functions/v1/chatbot';
        $payload = json_encode([
            'message' => $message,
            'history' => $history,
            'locale' => $locale,
            'site_base_url' => $siteBaseUrl,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return null;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $serviceKey,
                    'apikey: ' . $serviceKey,
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 28,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && is_string($response)) {
                $decoded = json_decode($response, true);
                return is_array($decoded) ? $decoded : null;
            }
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $serviceKey,
                    'apikey: ' . $serviceKey,
                ]),
                'content' => $payload,
                'timeout' => 28,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
