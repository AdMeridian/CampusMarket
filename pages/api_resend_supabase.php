<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isSupabaseConfigured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Supabase is not configured']);
    exit;
}

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function authHeader(): string {
    $value = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    return is_string($value) ? trim($value) : '';
}

function getSupabaseUser(string $jwt): ?array {
    $url = rtrim(supabaseUrl(), '/') . '/auth/v1/user';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $jwt,
            'apikey: ' . supabaseAnonKey(),
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if ($status < 200 || $status >= 300) {
        error_log("[api_resend_supabase] auth failed status={$status} err={$err} body={$body}");
        return null;
    }

    $decoded = json_decode((string) $body, true);
    return is_array($decoded) ? $decoded : null;
}

$authorization = authHeader();
if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing bearer token']);
    exit;
}

$supabaseUser = getSupabaseUser(trim($m[1]));
if (!$supabaseUser || empty($supabaseUser['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid Supabase session']);
    exit;
}

$input = jsonInput();
$to = trim((string) ($input['to'] ?? ''));
$subject = trim((string) ($input['subject'] ?? ''));
$html = (string) ($input['html'] ?? '');

if ($to === '' || $subject === '' || $html === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'to, subject and html are required']);
    exit;
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid recipient email']);
    exit;
}

if (strlen($subject) > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Subject too long']);
    exit;
}

$authEmail = strtolower(trim((string) $supabaseUser['email']));
if (strtolower($to) !== $authEmail) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Recipient must match authenticated Supabase user email']);
    exit;
}

$result = sendEmail($to, $subject, $html);
if (!$result['ok']) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send email',
        'details' => $result['error'] ?? null,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'messageId' => $result['response']['id'] ?? null,
    'to' => $to,
]);
