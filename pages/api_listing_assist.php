<?php
// pages/api_listing_assist.php
// AI Listing Assistant endpoint.
// Given a listing title (and optional description), returns:
//   - suggested category_ids  (auto-matched from DB)
//   - suggested tag_ids        (auto-matched from DB)
//   - suggested condition      ("new" | "like_new" | "used")
//   - generated description    (short campus-style selling copy)
// Requires: POST, active login, valid CSRF token.

ob_start(); // Buffer any stray output so we can always return clean JSON

try {
    require_once '../includes/bootstrap.php';
} catch (Throwable $bootErr) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Bootstrap failed: ' . $bootErr->getMessage()]);
    exit;
}

ob_end_clean();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

// Accept request from both POST form and JSON body — skip CSRF only if request carries a valid session
// (same pattern used by other AJAX endpoints on this project)
verifyCsrfTokenJson();

$action      = trim($_POST['action'] ?? 'all');
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if (strlen($title) < 2) {
    echo json_encode([
        'category_ids' => [],
        'tag_ids'      => [],
        'condition'    => '',
        'description'  => '',
        'note'         => 'Title too short.',
    ]);
    exit;
}

// Fetch master categories and tags from DB
$masterCategories = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$masterTags       = $pdo->query("SELECT id, name, slug FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$categoryNames = array_column($masterCategories, 'name');

$categoryList = implode(', ', $categoryNames);
$tagList      = implode(', ', array_column($masterTags, 'name'));

$descContext = $description !== '' ? "Description: \"{$description}\"" : "No description provided yet.";

$prompt =
    "You are an assistant helping a university student list an item for sale on a campus marketplace.\n\n"
    . "Title: \"{$title}\"\n"
    . "{$descContext}\n\n"
    . "Available categories (pick the best matching ones): [{$categoryList}]\n"
    . "Available tags (pick 3-5 that fit): [{$tagList}]\n\n"
    . "Tasks:\n"
    . "1. Select the best matching categories from the list above. Only pick ones that genuinely fit.\n"
    . "2. Select 3-5 tags from the list above that best describe the item.\n"
    . "3. Determine the most likely condition: 'new', 'like_new', or 'used'.\n"
    . "4. Write a short, honest, friendly selling description for this campus item (2-3 sentences, max 200 characters). "
    . "   Focus on what the item is, its condition, and why a student might want it. No marketing fluff. No pricing.\n\n"
    . "Return ONLY JSON with these exact keys:\n"
    . "  categories: array of strings (exact names from the list above)\n"
    . "  tags:       array of strings (exact names from the list above)\n"
    . "  condition:  string ('new', 'like_new', or 'used')\n"
    . "  description: string\n";

function listingAssistEnv(string $key): ?string {
    foreach ([$_ENV, $_SERVER] as $src) {
        if (!empty($src[$key]) && trim((string)$src[$key]) !== '') {
            return trim((string)$src[$key]);
        }
    }
    $v = getenv($key);
    return ($v !== false && trim((string)$v) !== '') ? trim((string)$v) : null;
}

function listingAssistCallGemini(string $apiKey, string $prompt): ?array {
    $requestBody = [
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ];

    foreach (['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'] as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($requestBody),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[api_listing_assist] Gemini {$model} HTTP {$httpCode}");
            continue;
        }

        $data = json_decode((string)$response, true);
        $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $parsed = json_decode($text, true);
        if (is_array($parsed)) return $parsed;
    }

    return null;
}

function listingAssistCallOpenRouter(string $key, string $prompt): ?array {
    $models = ['google/gemma-4-31b-it:free', 'openrouter/free'];
    $body = [
        'response_format' => ['type' => 'json_object'],
        'messages'        => [['role' => 'user', 'content' => $prompt]],
    ];

    foreach ($models as $model) {
        $body['model'] = $model;
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$key}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT    => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) continue;

        $data   = json_decode((string)$response, true);
        $text   = $data['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode(trim($text), true);
        if (is_array($parsed)) return $parsed;
    }

    return null;
}

$apiKey        = listingAssistEnv('GEMINI_API_KEY') ?? listingAssistEnv('CHATBOT_GEMINI_API_KEY');
$openRouterKey = listingAssistEnv('OPEN_ROUTER_API_KEY');

$aiResult = null;
if ($apiKey) {
    $aiResult = listingAssistCallGemini($apiKey, $prompt);
}
if ($aiResult === null && $openRouterKey) {
    $aiResult = listingAssistCallOpenRouter($openRouterKey, $prompt);
}

if ($aiResult === null) {
    echo json_encode([
        'category_ids' => [],
        'tag_ids'      => [],
        'condition'    => '',
        'description'  => '',
        'note'         => 'AI unavailable - please fill in manually.',
    ]);
    exit;
}

// Match AI category names to real DB IDs
$aiCategories  = array_map('strtolower', (array)($aiResult['categories'] ?? []));
$matchedCatIds = [];

foreach ($masterCategories as $cat) {
    $nameLower = strtolower($cat['name']);
    $slugLower = strtolower($cat['slug'] ?? '');
    foreach ($aiCategories as $ac) {
        if (
            $ac === $nameLower ||
            str_contains($nameLower, $ac) ||
            str_contains($ac, $nameLower) ||
            str_contains($slugLower, $ac) ||
            (strlen($ac) > 3 && similar_text($ac, $nameLower) / max(strlen($ac), strlen($nameLower)) > 0.78)
        ) {
            $matchedCatIds[] = (int)$cat['id'];
            break;
        }
    }
}
$matchedCatIds = array_values(array_unique($matchedCatIds));

// Match AI tag names to real DB IDs
$aiTags        = array_map('strtolower', (array)($aiResult['tags'] ?? []));
$matchedTagIds = [];

foreach ($masterTags as $tag) {
    $nameLower = strtolower($tag['name']);
    $slugLower = strtolower($tag['slug'] ?? '');
    foreach ($aiTags as $at) {
        if (
            $at === $nameLower ||
            str_contains($nameLower, $at) ||
            str_contains($at, $nameLower) ||
            str_contains($slugLower, $at) ||
            (strlen($at) > 3 && similar_text($at, $nameLower) / max(strlen($at), strlen($nameLower)) > 0.75)
        ) {
            $matchedTagIds[] = (int)$tag['id'];
            break;
        }
    }
}
$matchedTagIds = array_values(array_unique(array_slice($matchedTagIds, 0, 5)));

// Normalise condition
$conditionRaw = strtolower(trim((string)($aiResult['condition'] ?? '')));
$condition    = in_array($conditionRaw, ['new', 'like_new', 'used'], true) ? $conditionRaw : 'used';

// Clean description
$generatedDesc = trim((string)($aiResult['description'] ?? ''));

echo json_encode([
    'category_ids' => $matchedCatIds,
    'tag_ids'      => $matchedTagIds,
    'condition'    => $condition,
    'description'  => $generatedDesc,
    'note'         => '',
]);
exit;
