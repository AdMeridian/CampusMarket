<?php
// ============================================================
// CampusMarket — Translation Service
// Uses Google Cloud Translation API v2
// ============================================================

class TranslationService {

    private string $apiKey;
    private const API_URL = 'https://translation.googleapis.com/language/translate/v2';
    private const DETECT_URL = 'https://translation.googleapis.com/language/translate/v2/detect';

    /** @var array Placeholder map for entity protection */
    private array $placeholders = [];

    public function __construct() {
        $this->apiKey = getenv('GOOGLE_TRANSLATE_API_KEY') ?: '';
    }

    /**
     * Check if translation service is configured.
     */
    public function isConfigured(): bool {
        return $this->apiKey !== '';
    }

    /**
     * Translate text to the target language.
     *
     * @param string      $text       Text to translate
     * @param string      $targetLang Target language code (e.g., 'en', 'tr')
     * @param string|null $sourceLang Source language code (auto-detect if null)
     * @return array ['text' => translated_text, 'source' => detected_source_lang]
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): array {
        if (!$this->isConfigured()) {
            return ['text' => $text, 'source' => $sourceLang ?? 'unknown'];
        }

        if (trim($text) === '') {
            return ['text' => $text, 'source' => $sourceLang ?? 'unknown'];
        }

        // Protect entities before translation
        $protected = $this->protectEntities($text);

        $postData = [
            'q'      => $protected,
            'target' => $targetLang,
            'format' => 'text',
            'key'    => $this->apiKey,
        ];

        if ($sourceLang !== null) {
            $postData['source'] = $sourceLang;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || $response === false) {
            error_log("[TranslationService] API error HTTP $httpCode: $response");
            return ['text' => $text, 'source' => $sourceLang ?? 'unknown'];
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['data']['translations'][0]['translatedText'])) {
            error_log("[TranslationService] Unexpected response: $response");
            return ['text' => $text, 'source' => $sourceLang ?? 'unknown'];
        }

        $translation = $decoded['data']['translations'][0];
        $translatedText = html_entity_decode($translation['translatedText'], ENT_QUOTES, 'UTF-8');

        // Restore protected entities
        $translatedText = $this->restoreEntities($translatedText);

        $detectedSource = $translation['detectedSourceLanguage'] ?? $sourceLang ?? 'unknown';

        return [
            'text'   => $translatedText,
            'source' => $detectedSource,
        ];
    }

    /**
     * Detect the language of a text.
     *
     * @return string Language code (e.g., 'en', 'tr')
     */
    public function detectLanguage(string $text): string {
        if (!$this->isConfigured() || trim($text) === '') {
            return 'unknown';
        }

        $postData = [
            'q'   => mb_substr($text, 0, 500), // Use first 500 chars for detection
            'key' => $this->apiKey,
        ];

        $ch = curl_init(self::DETECT_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || $response === false) {
            return 'unknown';
        }

        $decoded = json_decode($response, true);
        return $decoded['data']['detections'][0][0]['language'] ?? 'unknown';
    }

    /**
     * Translate a message and cache the result in the database.
     *
     * @return array ['translated_text' => ..., 'source_lang' => ...]
     */
    public function translateMessage(int $messageId, string $originalText, string $targetLang, PDO $pdo): array {
        // Check cache first
        $stmt = $pdo->prepare(
            "SELECT translated_text, source_lang FROM message_translations WHERE message_id = :mid AND target_lang = :tl"
        );
        $stmt->execute([':mid' => $messageId, ':tl' => $targetLang]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            return [
                'translated_text' => $cached['translated_text'],
                'source_lang'     => $cached['source_lang'],
            ];
        }

        // Translate via API
        $result = $this->translate($originalText, $targetLang);

        // Cache if translation/detection was successful
        if ($result['source'] !== 'unknown') {
            try {
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'pgsql') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO message_translations (message_id, target_lang, translated_text, source_lang)
                         VALUES (:mid, :tl, :tt, :sl)
                         ON CONFLICT (message_id, target_lang) DO UPDATE SET translated_text = :tt2, source_lang = :sl2"
                    );
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO message_translations (message_id, target_lang, translated_text, source_lang)
                         VALUES (:mid, :tl, :tt, :sl)
                         ON DUPLICATE KEY UPDATE translated_text = :tt2, source_lang = :sl2"
                    );
                }
                $stmt->execute([
                    ':mid' => $messageId,
                    ':tl'  => $targetLang,
                    ':tt'  => $result['text'],
                    ':sl'  => $result['source'],
                    ':tt2' => $result['text'],
                    ':sl2' => $result['source'],
                ]);
            } catch (PDOException $e) {
                error_log("[TranslationService] Cache write error: " . $e->getMessage());
            }
        }

        return [
            'translated_text' => $result['text'],
            'source_lang'     => $result['source'],
        ];
    }

    /**
     * Protect entities that should NOT be translated.
     * Replaces URLs, emails, phone numbers, prices with placeholders.
     */
    private function protectEntities(string $text): string {
        $this->placeholders = [];
        $index = 0;

        // URLs
        $text = preg_replace_callback(
            '/(https?:\/\/[^\s]+)/u',
            function ($m) use (&$index) {
                $ph = "⟦PH{$index}⟧";
                $this->placeholders[$ph] = $m[0];
                $index++;
                return $ph;
            },
            $text
        );

        // Email addresses
        $text = preg_replace_callback(
            '/[\w.+\-]+@[\w\-]+\.[\w.]+/u',
            function ($m) use (&$index) {
                $ph = "⟦PH{$index}⟧";
                $this->placeholders[$ph] = $m[0];
                $index++;
                return $ph;
            },
            $text
        );

        // Prices (₺, $, €)
        $text = preg_replace_callback(
            '/(\d+[\.,]?\d*\s*[₺$€]|[₺$€]\s*\d+[\.,]?\d*|\d+[\.,]?\d*\s*TL)/u',
            function ($m) use (&$index) {
                $ph = "⟦PH{$index}⟧";
                $this->placeholders[$ph] = $m[0];
                $index++;
                return $ph;
            },
            $text
        );

        // Phone numbers (7+ digits with optional +, spaces, dashes, parens)
        $text = preg_replace_callback(
            '/(\+?\d[\d\s\-\(\)]{6,})/u',
            function ($m) use (&$index) {
                $ph = "⟦PH{$index}⟧";
                $this->placeholders[$ph] = $m[0];
                $index++;
                return $ph;
            },
            $text
        );

        // Product IDs (e.g., #12345, ID:12345)
        $text = preg_replace_callback(
            '/(#\d+|ID:\d+)/ui',
            function ($m) use (&$index) {
                $ph = "⟦PH{$index}⟧";
                $this->placeholders[$ph] = $m[0];
                $index++;
                return $ph;
            },
            $text
        );

        return $text;
    }

    /**
     * Restore protected entities after translation.
     */
    private function restoreEntities(string $text): string {
        foreach ($this->placeholders as $ph => $original) {
            $text = str_replace($ph, $original, $text);
        }
        $this->placeholders = [];
        return $text;
    }
}

/**
 * Get a singleton TranslationService instance.
 */
function getTranslationService(): TranslationService {
    static $instance = null;
    if ($instance === null) {
        $instance = new TranslationService();
    }
    return $instance;
}
