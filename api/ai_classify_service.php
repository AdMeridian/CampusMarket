<?php
// api/ai_classify_service.php
// AI-assisted service and skills classification for student providers using Google Gemini

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// 1. Session check: user must be authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'You must be logged in to use the AI assistant.']);
    exit;
}

// 2. CSRF Token verification
verifyCsrfTokenJson();

// 3. Rate limiting (max 10 classifications per minute per user)
require_once __DIR__ . '/../includes/rate_limit.php';
$userId = currentUserId();
$rateCheck = rateLimitAllow($pdo, 'ai_classify_user_' . $userId, 10, 60);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'Rate limit exceeded. Please wait ' . ($rateCheck['retry_after'] ?? 60) . ' seconds before trying again.'
    ]);
    exit;
}

// Read input (supports raw JSON or standard POST)
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
if (!is_array($body)) {
    $body = $_POST;
}

$skillsText = trim((string)($body['skills_text'] ?? $body['description'] ?? ''));
$bioText    = trim((string)($body['bio'] ?? ''));

if (($body['ai_consent'] ?? false) !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Consent is required to send your description to an external AI service.']);
    exit;
}

if (mb_strlen($skillsText) < 4) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please provide a brief description of the skills or services you offer.']);
    exit;
}

// Fetch active service categories from database
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name, slug FROM categories WHERE type = 'service' ORDER BY id ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("[ai_classify] Failed to fetch service categories: " . $e->getMessage());
}

if (empty($categories)) {
    $categories = [
        ['id' => 11, 'name' => 'Tutoring & Education'],
        ['id' => 12, 'name' => 'Cleaning & Domestic Services'],
        ['id' => 13, 'name' => 'Moving & Handyman Services'],
        ['id' => 14, 'name' => 'Design, Photography & Media'],
        ['id' => 15, 'name' => 'Web, Software & Tech Support'],
        ['id' => 16, 'name' => 'Writing, Translation & Admin'],
    ];
}

$categoriesListStr = '';
foreach ($categories as $c) {
    $categoriesListStr .= "- Category ID: {$c['id']}, Name: \"{$c['name']}\"\n";
}

// Get Gemini API Key
$apiKey = '';
foreach (['GEMINI_API_KEY', 'CHATBOT_GEMINI_API_KEY'] as $envKey) {
    $val = appEnv($envKey);
    if (!empty($val) && !str_contains($val, 'your_')) {
        $apiKey = $val;
        break;
    }
}

// Fallback generator if AI is disabled or fails
$buildFallback = function(string $text, array $cats): array {
    $lower = mb_strtolower($text);
    $selectedCat = $cats[0];
    
    // Simple keyword mapping
    if (preg_match('/(code|web|app|program|python|react|tech|fix|pc|laptop|software|setup)/i', $lower)) {
        foreach ($cats as $c) { if (stripos($c['name'], 'tech') !== false || stripos($c['name'], 'software') !== false) { $selectedCat = $c; break; } }
    } elseif (preg_match('/(design|photo|video|logo|edit|camera|draw|art|figma|photoshop)/i', $lower)) {
        foreach ($cats as $c) { if (stripos($c['name'], 'design') !== false || stripos($c['name'], 'photo') !== false) { $selectedCat = $c; break; } }
    } elseif (preg_match('/(tutor|math|physics|exam|teach|lesson|homework|course|calculus)/i', $lower)) {
        foreach ($cats as $c) { if (stripos($c['name'], 'tutor') !== false || stripos($c['name'], 'education') !== false) { $selectedCat = $c; break; } }
    } elseif (preg_match('/(write|essay|translate|proofread|paper|turkish|english)/i', $lower)) {
        foreach ($cats as $c) { if (stripos($c['name'], 'writing') !== false || stripos($c['name'], 'translation') !== false) { $selectedCat = $c; break; } }
    } elseif (preg_match('/(move|haul|furniture|handyman|assemble|dorm|repair)/i', $lower)) {
        foreach ($cats as $c) { if (stripos($c['name'], 'moving') !== false || stripos($c['name'], 'handyman') !== false) { $selectedCat = $c; break; } }
    }

    // Extract basic tags
    $words = preg_split('/[\s,\.\-]+/', $text);
    $skills = [];
    foreach ($words as $w) {
        $clean = trim($w);
        if (mb_strlen($clean) >= 3 && !in_array(mb_strtolower($clean), ['and', 'the', 'for', 'with', 'can', 'help', 'you', 'service', 'offer'])) {
            $skills[] = ucfirst(mb_strtolower($clean));
            if (count($skills) >= 5) break;
        }
    }
    if (empty($skills)) {
        $skills = ['Campus Services', 'Student Skills'];
    }

    $title = mb_substr($text, 0, 60);
    if (mb_strlen($title) < 15) {
        $title = "Campus " . $selectedCat['name'] . " Assistance";
    }

    return [
        'skills' => array_values(array_unique($skills)),
        'suggested_additions' => [],
        'category_id' => (int)$selectedCat['id'],
        'category_name' => $selectedCat['name'],
        'title' => $title,
        'description' => $text,
        'pricing_model' => 'hourly',
        'suggested_price' => 200,
        'delivery_days' => null,
        'suggested_bio' => 'Student provider offering reliable ' . strtolower($selectedCat['name']) . ' on campus.',
        'engine' => 'fallback'
    ];
};

if (empty($apiKey)) {
    $fallbackResult = $buildFallback($skillsText, $categories);
    echo json_encode(['ok' => true, 'data' => $fallbackResult]);
    exit;
}

// Build Gemini Prompt
$prompt = <<<PROMPT
You are an intelligent campus marketplace assistant. A university student wants to register as a service provider on our campus platform.
They provided the following description of their skills, experience, or service offering:

---
User's Description:
"{$skillsText}"
---

Below is our list of available service categories in the database:
{$categoriesListStr}

Analyze the user's input and produce a structured JSON object with the following exact keys:
1. "skills": An array of 3 to 6 concise, standardized skill tags (e.g. ["Python", "Web Scraping", "Automation"]). Strictly reflect only skills/services directly mentioned in the user's description.
2. "suggested_additions": An array of 0 to 4 related skills, course topics, or tools that the provider might also be proficient in based on context, but did NOT explicitly state. Keep this strictly separate from "skills" so the student can review and manually choose whether to add them.
3. "category_id": The integer ID of the best matching category from the list above.
4. "category_name": The exact name of the selected category.
5. "title": A clear, professional, appealing listing title suitable for campus buyers (max 70 characters).
6. "description": An engaging, professional 2-3 sentence description summarizing what the provider delivers for fellow students. IMPORTANT: Base this description strictly on what the user actually stated. Do NOT invent, assume, or hallucinate specific courses, textbooks, or subject areas (e.g., do NOT invent "Linear Algebra", "Calculus III", or specific tools) unless explicitly mentioned by the student.
7. "pricing_model": Either "hourly" (for tutoring, consulting, moving help, ongoing tech support) or "flat" (for logos, photo shoots, specific fixed projects).
8. "suggested_price": A fair, student-friendly price in Turkish Lira (TRY) as a number (typically between 100 and 600 TRY).
9. "delivery_days": An integer representing expected turnaround days (1 to 5) if flat pricing, or null if hourly.
10. "suggested_bio": A clean, 1-2 sentence profile bio describing the student provider based strictly on their input.

Return ONLY valid JSON matching this schema.
PROMPT;

$models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];
$aiData = null;

$openRouterKey = appEnv('OPEN_ROUTER_API_KEY');

// 1. Try Gemini if API key is present
if (!empty($apiKey)) {
    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $postBody = json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json']
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $postBody,
            CURLOPT_TIMEOUT        => 12,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $decoded = json_decode($response, true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsed = json_decode($text, true);
            if (is_array($parsed) && !empty($parsed['skills'])) {
                $parsed['engine'] = $model;
                $aiData = $parsed;
                break;
            }
        } else {
            error_log("[ai_classify] Gemini {$model} failed HTTP {$httpCode}: " . substr((string)$response, 0, 160));
        }
    }
}

// 2. Try OpenRouter if Gemini did not succeed and OpenRouter key is set
if ($aiData === null && !empty($openRouterKey) && !str_contains($openRouterKey, 'your_')) {
    $orModels = ['google/gemma-2-9b-it:free', 'mistralai/mistral-7b-instruct:free', 'openrouter/auto'];
    foreach ($orModels as $orModel) {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$openRouterKey}",
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'model' => $orModel,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object']
            ]),
            CURLOPT_TIMEOUT        => 15
        ]);
        $orRes = curl_exec($ch);
        $orCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($orCode === 200 && !empty($orRes)) {
            $orDec = json_decode($orRes, true);
            $content = $orDec['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($content, true);
            if (is_array($parsed) && !empty($parsed['skills'])) {
                $parsed['engine'] = 'openrouter/' . $orModel;
                $aiData = $parsed;
                break;
            }
        }
    }
}


if ($aiData !== null) {
    // Validate / sanitize category_id
    $validCatIds = array_column($categories, 'id');
    if (!in_array((int)$aiData['category_id'], $validCatIds, true)) {
        $aiData['category_id'] = (int)($categories[0]['id'] ?? 11);
        $aiData['category_name'] = $categories[0]['name'] ?? 'General Services';
    }
    echo json_encode(['ok' => true, 'data' => $aiData]);
} else {
    // Graceful fallback
    $fallbackResult = $buildFallback($skillsText, $categories);
    echo json_encode(['ok' => true, 'data' => $fallbackResult]);
}
