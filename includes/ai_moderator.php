<?php
// includes/ai_moderator.php
// AI Listing Guard using Gemini API to evaluate listing quality and generate tags.

function aiModeratorEnv(string $key): ?string {
    $value = getenv($key);
    if ($value !== false && trim((string)$value) !== '') {
        return trim((string)$value);
    }
    if (isset($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') {
        return trim((string)$_ENV[$key]);
    }
    if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
        return trim((string)$_SERVER[$key]);
    }
    return null;
}

function aiModeratorApiKey(): ?string {
    foreach (['GEMINI_API_KEY', 'CHATBOT_GEMINI_API_KEY'] as $name) {
        $value = aiModeratorEnv($name);
        if ($value !== null) {
            return $value;
        }
    }
    return null;
}

function aiModeratorOpenRouterKey(): ?string {
    return aiModeratorEnv('OPEN_ROUTER_API_KEY');
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
        'mode' => (string)($result['mode'] ?? 'vision'),
    ];
}

function aiModeratorFailure(string $reason, string $mode = 'error'): array {
    error_log('[ai_moderator] ' . $reason);
    return [
        'passed' => false,
        'is_blurry' => false,
        'confidence' => 0.0,
        'tags' => [],
        'reason' => $reason,
        'mode' => $mode,
    ];
}

function aiModeratorLooksLikeAvailabilityConcern(string $text): bool {
    $text = strtolower(trim($text));
    if ($text === '') {
        return false;
    }

    $patterns = [
        'does not exist',
        "doesn't exist",
        'not out yet',
        'not yet released',
        'not released yet',
        'not available yet',
        'unreleased',
        'future release',
        'coming soon',
        'not available',
    ];

    foreach ($patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function aiModeratorApplyAvailabilityOverride(array $result): array {
    $reason = trim((string)($result['reason'] ?? ''));
    if (!aiModeratorLooksLikeAvailabilityConcern($reason)) {
        return $result;
    }

    $result['passed'] = true;
    $result['confidence'] = max((float)($result['confidence'] ?? 0), 0.72);
    $result['reason'] = 'Listing reviewed for marketplace policy; availability timing was not treated as a moderation issue.';

    return $result;
}

function aiModeratorHasVerificationProvider(): bool {
    return aiModeratorEnv('SERPER_API_KEY') !== null
        || aiModeratorEnv('SERPAPI_KEY') !== null
        || aiModeratorEnv('BRAVE_API_KEY') !== null;
}

function aiModeratorVerifyProductAvailability(string $title, string $description): ?array {
    $query = trim($title . ' ' . $description);
    $query = preg_replace('/\s+/u', ' ', $query) ?? $query;
    if ($query === '') {
        return null;
    }

    $serperKey = aiModeratorEnv('SERPER_API_KEY') ?: aiModeratorEnv('SERPAPI_KEY');
    if ($serperKey !== null) {
        $ch = curl_init('https://google.serper.dev/search');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-KEY: ' . $serperKey,
            ],
            CURLOPT_POSTFIELDS => json_encode(['q' => $query, 'num' => 5]),
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode((string)$response, true);
            $organic = $data['organic'] ?? [];
            if (is_array($organic) && !empty($organic)) {
                return ['found' => true, 'source' => 'serper'];
            }
            return ['found' => false, 'source' => 'serper'];
        }
    }

    $braveKey = aiModeratorEnv('BRAVE_API_KEY');
    if ($braveKey !== null) {
        $url = 'https://api.search.brave.com/res/v1/web/search?q=' . urlencode($query) . '&count=5';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Subscription-Token: ' . $braveKey],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode((string)$response, true);
            $results = $data['web']['results'] ?? [];
            if (is_array($results) && !empty($results)) {
                return ['found' => true, 'source' => 'brave'];
            }
            return ['found' => false, 'source' => 'brave'];
        }
    }

    return null;
}

function aiModeratorApplyExternalVerification(array $result, string $title, string $description): array {
    $reason = trim((string)($result['reason'] ?? ''));
    if (!aiModeratorLooksLikeAvailabilityConcern($reason)) {
        return $result;
    }

    if (!aiModeratorHasVerificationProvider()) {
        return $result;
    }

    $verification = aiModeratorVerifyProductAvailability($title, $description);
    if ($verification === null) {
        return $result;
    }

    if (!empty($verification['found'])) {
        $result['passed'] = true;
        $result['confidence'] = max((float)($result['confidence'] ?? 0), 0.8);
        $result['reason'] = 'Public web results were found for this product, so the availability concern was not treated as a moderation issue.';
        return $result;
    }

    $result['passed'] = false;
    $result['confidence'] = max((float)($result['confidence'] ?? 0), 0.75);
    $result['reason'] = 'No public web results were found for this product; please confirm the listing details before approval.';

    return $result;
}

function aiModeratorBuildPrompt(string $title, string $description, bool $vision): string {
    $visionNote = $vision
        ? "Image match: photos should reasonably match the title/description. Minor angle/lighting issues are perfectly fine for used campus items."
        : "No images were analyzed — judge title and description only.";

    return "You are an AI moderator for a student campus marketplace where students sell their personal belongings to other students.\n"
        . "Your ONLY job is to catch clear policy violations. Default to APPROVING listings unless there is an obvious, explicit violation.\n\n"
        . "APPROVE (passed=true, confidence 0.85+) for:\n"
        . "- Any electronics, phones, laptops, tablets, gaming gear — regardless of model name or release date.\n"
        . "- Books, textbooks, study materials (but NOT exam answer keys or test banks).\n"
        . "- Furniture, dorm items, clothing, sports gear, bicycles, kitchenware.\n"
        . "- Any item a student might realistically own and want to resell.\n\n"
        . "REJECT (passed=false) ONLY for explicit violations:\n"
        . "- Weapons, ammunition, or dangerous items.\n"
        . "- Illegal drugs, alcohol, tobacco, or vaping products.\n"
        . "- Adult/sexual content.\n"
        . "- Exam answer keys, test banks, or academic dishonesty materials.\n"
        . "- Listings that are obviously and explicitly fraudulent (e.g. 'send me money and I will ship' with no real item).\n\n"
        . "CRITICAL RULES — violations of these will cause errors:\n"
        . "- NEVER reject a listing because a product model sounds new, unfamiliar, or recently released. A student can own and sell any product at any time.\n"
        . "- NEVER assume a listing is a scam just because you are unfamiliar with the product model or release date.\n"
        . "- NEVER flag availability, release timing, or product existence as a moderation issue.\n"
        . "- Do NOT add any moderation note or reason unless the listing actually fails — if passed=true, set reason to an empty string.\n\n"
        . "Image check (only if images provided): {$visionNote}\n"
        . "Only set is_blurry=true if photos are completely unreadable. Minor blur or imperfect lighting is fine.\n\n"
        . "If the listing fails, the reason field must be a short, friendly sentence written directly to the seller "
        . "explaining the specific violation (e.g. 'This item appears to be a prohibited weapon', 'Photo is too blurry to see the item'). "
        . "If the listing passes, leave reason as an empty string.\n\n"
        . "Return ONLY JSON with keys: passed (boolean), is_blurry (boolean), confidence (0-1 number), tags (3-5 single words), reason (string).\n\n"
        . "Title: \"{$title}\"\n"
        . "Description: \"{$description}\"";
}

/** @return array{mime:string,base64:string}|null */
function aiModeratorPrepareImage(string $binary, string $mime): ?array {
    if ($binary === '') {
        return null;
    }

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($binary);
        if ($img !== false) {
            $width = imagesx($img);
            $height = imagesy($img);
            $maxEdge = 1280;

            if ($width > 0 && $height > 0 && ($width > $maxEdge || $height > $maxEdge)) {
                $scale = min($maxEdge / $width, $maxEdge / $height);
                $newW = max(1, (int)round($width * $scale));
                $newH = max(1, (int)round($height * $scale));
                $resized = imagecreatetruecolor($newW, $newH);
                if ($resized !== false) {
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
                    @imagedestroy($img);
                    $img = $resized;
                }
            }

            ob_start();
            imagejpeg($img, null, 82);
            $jpeg = ob_get_clean();
            @imagedestroy($img);

            if ($jpeg !== false && $jpeg !== '') {
                return ['mime' => 'image/jpeg', 'base64' => base64_encode($jpeg)];
            }
        }
    }

    if (strlen($binary) > 3 * 1024 * 1024) {
        error_log('[ai_moderator] image too large and GD unavailable; skipping vision');
        return null;
    }

    return ['mime' => $mime, 'base64' => base64_encode($binary)];
}

function aiModeratorCallGemini(string $apiKey, string $prompt, array $imagesData): array {
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
        'contents' => [['role' => 'user', 'parts' => $parts]],
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ];

    $models = ['gemini-3.1-flash-lite', 'gemini-2.0-flash'];
    $lastCode = 0;
    $lastBody = '';

    foreach ($models as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_TIMEOUT => 25,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            $data = json_decode((string)$response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsed = aiModeratorParseJson($text);
            if ($parsed !== null) {
                $result = aiModeratorNormalizeResult($parsed);
                $result = aiModeratorApplyAvailabilityOverride($result);
                $result['mode'] = empty($imagesData) ? 'text' : 'vision';
                return ['ok' => true, 'result' => $result];
            }
            $lastCode = 200;
            $lastBody = 'AI response not JSON formatted.';
            continue;
        }

        $lastCode = $httpCode;
        $lastBody = substr((string)$response, 0, 300);
        error_log("[ai_moderator] Gemini {$model} failed HTTP {$httpCode}: {$lastBody}");
    }

    return ['ok' => false, 'code' => $lastCode, 'error' => $lastBody];
}

function aiModeratorCallOpenRouter(string $openRouterKey, string $prompt, array $imagesData): array {
    $content = [['type' => 'text', 'text' => $prompt]];
    foreach ($imagesData as $img) {
        $content[] = [
            'type' => 'image_url',
            'image_url' => ['url' => "data:{$img['mime']};base64,{$img['base64']}"],
        ];
    }

    // Free OpenRouter models only (rate-limited; no purchased credits required).
    $models = [
        'openrouter/free',
        'google/gemma-4-31b-it:free',
        'google/gemma-4-26b-a4b-it:free',
    ];

    $lastCode = 0;
    $lastBody = '';

    foreach ($models as $model) {
        $orRequestBody = [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'user', 'content' => $content]],
        ];

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$openRouterKey}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($orRequestBody),
            CURLOPT_TIMEOUT => 25,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            $data = json_decode((string)$response, true);
            $text = $data['choices'][0]['message']['content'] ?? '';
            $parsed = aiModeratorParseJson($text);
            if ($parsed === null) {
                $lastCode = 200;
                $lastBody = 'AI response not JSON formatted.';
                continue;
            }

            $result = aiModeratorNormalizeResult($parsed);
            $result = aiModeratorApplyAvailabilityOverride($result);
            $result['mode'] = empty($imagesData) ? 'text' : 'vision';
            return ['ok' => true, 'result' => $result];
        }

        $lastCode = $httpCode;
        $lastBody = substr((string)$response, 0, 300);
        error_log("[ai_moderator] OpenRouter {$model} failed HTTP {$httpCode}: {$lastBody}");
    }

    return ['ok' => false, 'code' => $lastCode, 'error' => $lastBody];
}

function aiModeratorEvaluate(string $title, string $description, array $imagesData): array {
    $apiKey = aiModeratorApiKey();
    $openRouterKey = aiModeratorOpenRouterKey();

    if (!$apiKey && !$openRouterKey) {
        return aiModeratorFailure('No AI API keys configured; manual moderation required.');
    }

    $prompt = aiModeratorBuildPrompt($title, $description, !empty($imagesData));

    if ($apiKey) {
        $gemini = aiModeratorCallGemini($apiKey, $prompt, $imagesData);
        if (!empty($gemini['ok'])) {
            return $gemini['result'];
        }
    }

    if ($openRouterKey) {
        $or = aiModeratorCallOpenRouter($openRouterKey, $prompt, $imagesData);
        if (!empty($or['ok'])) {
            return $or['result'];
        }
    }

    return aiModeratorFailure('AI API error (vision/text request failed)');
}

function aiModerateListing(string $title, string $description, array $imagesData = []): array {
    $preparedImages = [];
    foreach (array_slice($imagesData, 0, 1) as $img) {
        $mime = (string)($img['mime'] ?? 'image/jpeg');
        $raw = isset($img['base64']) ? base64_decode((string)$img['base64'], true) : false;
        if ($raw === false) {
            continue;
        }
        $prepared = aiModeratorPrepareImage($raw, $mime);
        if ($prepared !== null) {
            $preparedImages[] = $prepared;
        }
    }

    $result = aiModeratorEvaluate($title, $description, $preparedImages);
    $result = aiModeratorApplyExternalVerification($result, $title, $description);

    if (empty($result['passed']) && ($result['mode'] ?? '') === 'error' && !empty($preparedImages)) {
        error_log('[ai_moderator] vision failed; retrying text-only');
        $textResult = aiModeratorEvaluate($title, $description, []);
        if (!empty($textResult['passed']) && ($textResult['confidence'] ?? 0) >= AI_MODERATION_MIN_CONFIDENCE) {
            $textResult['reason'] = 'Text-only moderation (image check unavailable): ' . ($textResult['reason'] ?? '');
            $textResult['mode'] = 'text_fallback';
            $result = $textResult;
        }
    }

    error_log('[ai_moderator] decision passed=' . (!empty($result['passed']) ? '1' : '0')
        . ' confidence=' . ($result['confidence'] ?? 0)
        . ' mode=' . ($result['mode'] ?? '?')
        . ' reason=' . ($result['reason'] ?? ''));

    return $result;
}
