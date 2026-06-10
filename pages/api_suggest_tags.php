<?php
// pages/api_suggest_tags.php
// AJAX endpoint: given a product title + description, returns AI-suggested tag IDs.
// Requires: POST, active login, valid CSRF token.

require_once '../includes/bootstrap.php';
require_once '../includes/ai_moderator.php';

header('Content-Type: application/json');

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

// CSRF — uses the project's existing JSON-safe CSRF check
verifyCsrfTokenJson();

// Input
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if (strlen($title) < 2) {
    echo json_encode(['tags' => [], 'note' => 'Title too short to suggest tags.']);
    exit;
}

// Ask AI (text-only — no images needed for suggestions)
$aiResult = aiModerateListing($title, $description, []);

// Fetch all master tags from DB
$masterTags = $pdo->query("SELECT id, name, slug FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($masterTags)) {
    echo json_encode([
        'tags'    => [],
        'ai_tags' => $aiResult['tags'] ?? [],
        'passed'  => $aiResult['passed'] ?? false,
        'note'    => 'No tags in the system. Ask an admin to restore default tags under Admin → Tags.',
    ]);
    exit;
}

$suggestedIds = [];

if (!empty($aiResult['tags']) && !empty($masterTags)) {
    // Normalise AI tag names for fuzzy matching
    $aiTagsLower = array_map('strtolower', $aiResult['tags']);

    foreach ($masterTags as $mt) {
        $nameLower = strtolower($mt['name']);
        $slugLower = strtolower($mt['slug']);

        foreach ($aiTagsLower as $at) {
            // Match if the AI tag is contained in the master tag name/slug, or vice-versa
            if (
                str_contains($at, $nameLower) ||
                str_contains($nameLower, $at) ||
                str_contains($at, $slugLower) ||
                str_contains($slugLower, $at) ||
                similar_text($at, $nameLower) / max(strlen($at), strlen($nameLower)) > 0.75
            ) {
                $suggestedIds[] = (int)$mt['id'];
                break;
            }
        }
    }
}

echo json_encode([
    'tags'      => array_unique($suggestedIds),
    'ai_tags'   => $aiResult['tags'],     // raw AI output (useful for debugging)
    'passed'    => $aiResult['passed'],
    'note'      => empty($suggestedIds)
        ? (empty($aiResult['tags']) ? 'No strong matches found — select manually.' : 'AI suggested: ' . implode(', ', $aiResult['tags']) . ' — no matching tags in the system yet.')
        : '',
]);
exit;
