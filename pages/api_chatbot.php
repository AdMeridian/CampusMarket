<?php
// pages/api_chatbot.php — thin proxy to Supabase Edge Function (Gemini + FAQs)

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/chatbot_edge.php';

$adminId = 1;

try {
    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminId = (int)($adminStmt->fetchColumn() ?: 1);

    $ipLimit = rateLimitAllow($pdo, 'chatbot:ip:' . clientIpAddress(), 40, 3600);
    if (!$ipLimit['allowed']) {
        echo json_encode([
            'success' => false,
            'error' => 'rate_limit',
            'response' => i18nGetLocale() === 'tr'
                ? 'Çok fazla istek gönderildi. Lütfen bir süre sonra tekrar deneyin.'
                : 'Too many requests from your network. Please try again later.',
            'admin_id' => $adminId,
        ]);
        exit;
    }

    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if (!is_array($jsonInput)) {
        $jsonInput = [];
    }

    $userMessage = trim((string)($jsonInput['message'] ?? $_POST['message'] ?? ''));
    if ($userMessage === '') {
        echo json_encode(['success' => false, 'error' => 'Message is empty']);
        exit;
    }

    $history = isset($jsonInput['history']) && is_array($jsonInput['history']) ? $jsonInput['history'] : [];
    $locale = (string)($jsonInput['locale'] ?? i18nGetLocale());
    $siteBaseUrl = (string)($jsonInput['site_base_url'] ?? BASE_URL);

    $edgeResult = chatbotInvokeEdge($userMessage, $history, $locale, $siteBaseUrl);
    if (is_array($edgeResult)) {
        if (!isset($edgeResult['admin_id'])) {
            $edgeResult['admin_id'] = $adminId;
        }
        echo json_encode($edgeResult);
        exit;
    }

    echo json_encode([
        'success' => true,
        'response' => 'UNKNOWN',
        'unknown' => true,
        'admin_id' => $adminId,
    ]);
} catch (Throwable $e) {
    error_log('[api_chatbot] ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'response' => 'UNKNOWN',
        'unknown' => true,
        'admin_id' => $adminId,
    ]);
}
exit;
