<?php
// includes/ai_moderator.php
// AI Listing Guard using Gemini API to evaluate listing quality and generate tags.

function aiModerateListing(string $title, string $description, array $imagesData = []): array {
    // Load API keys from environment
    $apiKey = getenv('GEMINI_API_KEY');
    $openRouterKey = getenv('OPEN_ROUTER_API_KEY');

    if (!$apiKey && !$openRouterKey) {
        // No API key, fallback to manual review
        return [
            'passed' => false,
            'is_blurry' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => 'No AI API keys configured; manual moderation required.'
        ];
    }

    // Build prompt for Gemini
    $prompt = "You are an AI moderator for a student marketplace. Evaluate the listing with the given title, description, and attached images.\nCheck for the following:\n1. Image Quality: Are any of the images very blurry, extremely low resolution, or completely unreadable? If so, set 'is_blurry' to true.\n2. Content Match: Do the images match the title and description? (e.g., if it's a Lenovo laptop, does it actually look like one?)\n3. Trustworthiness: Is the listing generally trustworthy?\n\nReturn a JSON object with the following keys:\n- passed: true if the listing is trustworthy and images match, false otherwise.\n- is_blurry: true if ANY image is too blurry/unreadable, false otherwise.\n- confidence: a number between 0 and 1 indicating confidence level.\n- tags: an array of 3-5 concise, single-word tags that accurately describe the product.\n- reason: short explanation of your decision (if is_blurry is true, mention the blurry image).\n\nTitle: \"{$title}\"\nDescription: \"{$description}\"";

    $parts = [['text' => $prompt]];
    foreach ($imagesData as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $img['mime'],
                'data' => $img['base64']
            ]
        ];
    }

    $requestBody = [
        'contents' => [
            ['role' => 'user', 'parts' => $parts]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json'
        ]
    ];

    $useOpenRouter = false;
    $httpCode = 0;
    $response = '';

    if ($apiKey) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $useOpenRouter = true;
        }
    } else {
        $useOpenRouter = true;
    }

    if ($useOpenRouter && $openRouterKey) {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt]
                ]
            ]
        ];

        foreach ($imagesData as $img) {
            $messages[0]['content'][] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$img['mime']};base64,{$img['base64']}"
                ]
            ];
        }

        $orRequestBody = [
            'model' => 'google/gemini-1.5-flash',
            'response_format' => ['type' => 'json_object'],
            'messages' => $messages
        ];

        $orUrl = "https://openrouter.ai/api/v1/chat/completions";
        $ch = curl_init($orUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$openRouterKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orRequestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if ($httpCode !== 200) {
        // API error, fallback to manual moderation
        return [
            'passed' => false,
            'is_blurry' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => "AI API error (HTTP {$httpCode})"
        ];
    }

    $data = json_decode($response, true);
    
    // Parse response format depending on which API succeeded
    $text = '';
    if (isset($data['candidates'])) {
        // Gemini API format
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } elseif (isset($data['choices'])) {
        // OpenRouter format
        $text = $data['choices'][0]['message']['content'] ?? '';
    }

    // Attempt to decode JSON from the AI response
    $result = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
        // If the AI didn't output JSON, try a simple heuristic fallback
        $result = [
            'passed' => false,
            'is_blurry' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => 'AI response not JSON formatted.'
        ];
    }
    // Ensure required keys exist
    $result = array_merge([
        'passed' => false,
        'is_blurry' => false,
        'confidence' => 0.0,
        'tags' => [],
        'reason' => ''
    ], $result);
    return $result;
}
?>
