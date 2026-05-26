<?php
// includes/ai_moderator.php
// AI Listing Guard using Gemini API to evaluate listing quality and generate tags.

function aiModerateListing(string $title, string $description, int $productId): array {
    // Load Gemini API key from environment
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        // No API key, fallback to manual review
        return [
            'passed' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => 'Gemini API key not configured; manual moderation required.'
        ];
    }

    // Build prompt for Gemini - multimodal check (image omitted for simplicity)
    $prompt = "You are an AI moderator for a student marketplace. Evaluate the listing with the given title and description. Determine if the images (not provided) would likely be clear and match the description. Return a JSON object with the following keys:\n\n- passed: true if the listing is trustworthy, false otherwise.\n- confidence: a number between 0 and 1 indicating confidence level.\n- tags: an array of 3-5 concise, single-word tags that accurately describe the product.\n- reason: short explanation if not passed.\n\nTitle: \"{$title}\"\nDescription: \"{$description}\"";

    $requestBody = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]]
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // API error, fallback to manual moderation
        return [
            'passed' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => "Gemini API error (HTTP {$httpCode})"
        ];
    }

    $data = json_decode($response, true);
    // Extract the first candidate's content
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Attempt to decode JSON from the AI response
    $result = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
        // If the AI didn't output JSON, try a simple heuristic fallback
        $result = [
            'passed' => false,
            'confidence' => 0.0,
            'tags' => [],
            'reason' => 'AI response not JSON formatted.'
        ];
    }
    // Ensure required keys exist
    $result = array_merge([
        'passed' => false,
        'confidence' => 0.0,
        'tags' => [],
        'reason' => ''
    ], $result);
    return $result;
}
?>
