<?php
// includes/ai_moderator.php
// AI Listing Guard using Gemini API to evaluate listing quality and generate tags.

function aiModeratorApiKey(): ?string {
    foreach (['GEMINI_API_KEY', 'CHATBOT_GEMINI_API_KEY'] as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return null;
}

function aiModeratorOpenRouterKey(): ?string {
    $value = getenv('OPEN_ROUTER_API_KEY');
    if ($value !== false && trim((string)$value) !== '') {
        return trim((string)$value);
    }
    return null;
}

function aiModeratorParseJson(string $text): ?array {
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
        $text = trim($matches[1]);
    }
    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function aiModeratorNormalizeResult(array $result): array {
    $passed = $result['passed'] ?? false;
    if (is_string($passed)) {
        $passed = filter_var($passed, FILTER_VALIDATE_BOOLEAN);
    }

    $isBlurry = $result['is_blurry'] ?? false;
    if (is_string($isBlurry)) {
        $isBlurry = filter_var($isBlurry, FILTER_VALIDATE_BOOLEAN);
    }

    $confidence = (float)($result['confidence'] ?? 0);
    if ($confidence > 1) {
        $confidence = $confidence / 100;
    }
    if ($confidence < 0) {
        $confidence = 0.0;
    }
    if ($confidence > 1) {
        $confidence = 1.0;
    }

    $tags = $result['tags'] ?? [];
    if (!is_array($tags)) {
        $tags = [];
    }

    return [
        'passed' => (bool)$passed,
        'is_blurry' => (bool)$isBlurry,
        'confidence' => $confidence,
        'tags' => array_values(array_filter(array_map('strval', $tags))),
        'reason' => trim((string)($result['reason'] ?? '')),
    ];
}

function aiModeratorFailure(string $reason): array {
    error_log('[ai_moderator] ' . $reason);
    return [
        'passed' => false,
        'is_blurry' => false,
        'confidence' => 0.0,
        'tags' => [],
        'reason' => $reason,
    ];
}

function aiModerateListing(string $title, string $description, array $imagesData = []): array {
    $apiKey = aiModeratorApiKey();
    $openRouterKey = aiModeratorOpenRouterKey();

    if (!$apiKey && !$openRouterKey) {
        return aiModeratorFailure('No AI API keys configured; manual moderation required.');
    }

    $prompt = "You are an AI moderator for a student marketplace. Evaluate the listing with the given title, description, and attached images.\nCheck for the following:\n1. Image Quality: Are any of the images very blurry, extremely low resolution, or completely unreadable? If so, set 'is_blurry' to true.\n2. Content Match: Do the images match the title and description? (e.g., if it's a Lenovo laptop, does it actually look like one?)\n3. Prohibited Content & Policy Compliance: Is the listing safe, lawful, and compliant with university campus policies? Do NOT allow weapons, firearms, ammunition, knives, illegal substances, drugs, prescription medications, alcohol, tobacco/vape products, recalled items, adult content, academic dishonesty materials (exams, test banks, graded homework), or anything else that violates university rules. If any of these are detected, 'passed' must be set to false.\n\nReturn ONLY a JSON object with these keys:\n- passed (boolean): true if trustworthy, policy-compliant, and images match.\n- is_blurry (boolean): true if ANY image is too blurry/unreadable.\n- confidence (number 0 to 1): your confidence in the decision.\n- tags (array of 3-5 single-word strings): product tags.\n- reason (string): short explanation.\n\nTitle: \"{$title}\"\nDescription: \"{$description}\"";

    $parts = [['text' => $prompt]];
    foreach ($imagesData as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $img['mime'],
                'data' => $img['base64'],
            ],
        ];
    }

    $requestBody = [
        'contents' => [
            ['role' => 'user', 'parts' => $parts],
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
        ],
    ];

    $models = ['gemini-2.0-flash', 'gemini-1.5-flash'];
    $httpCode = 0;
    $response = '';
    $usedOpenRouter = false;

    if ($apiKey) {
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                break;
            }
            error_log("[ai_moderator] Gemini {$model} failed HTTP {$httpCode}: " . substr((string)$response, 0, 300));
        }
    } else {
        $httpCode = 0;
    }

    if ($httpCode !== 200 && $openRouterKey) {
        $usedOpenRouter = true;
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                ],
            ],
        ];

        foreach ($imagesData as $img) {
            $messages[0]['content'][] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$img['mime']};base64,{$img['base64']}",
                ],
            ];
        }

        $orRequestBody = [
            'model' => 'google/gemini-2.0-flash',
            'response_format' => ['type' => 'json_object'],
            'messages' => $messages,
        ];

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$openRouterKey}",
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orRequestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[ai_moderator] OpenRouter failed HTTP {$httpCode}: " . substr((string)$response, 0, 300));
        }
    }

    if ($httpCode !== 200) {
        return aiModeratorFailure("AI API error (HTTP {$httpCode})");
    }

    $data = json_decode((string)$response, true);
    $text = '';
    if (is_array($data) && isset($data['candidates'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } elseif (is_array($data) && isset($data['choices'])) {
        $text = $data['choices'][0]['message']['content'] ?? '';
    }

    $parsed = aiModeratorParseJson($text);
    if ($parsed === null) {
        return aiModeratorFailure('AI response not JSON formatted.');
    }

    $result = aiModeratorNormalizeResult($parsed);
    if ($usedOpenRouter) {
        error_log('[ai_moderator] used OpenRouter fallback');
    }
    error_log('[ai_moderator] decision passed=' . ($result['passed'] ? '1' : '0') . ' confidence=' . $result['confidence'] . ' reason=' . $result['reason']);

    return $result;
}
