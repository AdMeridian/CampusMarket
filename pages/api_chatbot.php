<?php
// pages/api_chatbot.php
// Backend API for CampusMarket Chatbot
// Implements rate-limiting, conversation memory, expanded FAQs, friendly chit-chat, and Gemini fallback.

// Disable warning/error output pollution to keep JSON responses pristine on live servers
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';

// Safe default admin ID in case database query fails early
$adminId = 1;

try {
    // Fetch First Admin ID dynamically for escalation path
    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminId = (int)($adminStmt->fetchColumn() ?: 1);

    // ─── 1. Rate Limiting (20 messages per user per hour in session) ───
    if (!isset($_SESSION['chatbot_messages_timestamps'])) {
        $_SESSION['chatbot_messages_timestamps'] = [];
    }
    $now = time();
    // Filter out timestamps older than 1 hour (3600 seconds)
    $_SESSION['chatbot_messages_timestamps'] = array_filter(
        $_SESSION['chatbot_messages_timestamps'],
        function($timestamp) use ($now) {
            return ($now - $timestamp) < 3600;
        }
    );

    if (count($_SESSION['chatbot_messages_timestamps']) >= 20) {
        echo json_encode([
            'success' => false,
            'error' => 'rate_limit',
            'response' => i18nGetLocale() === 'tr' 
                ? 'Saatlik mesaj sınırına ulaştınız (Maksimum 20 mesaj/saat). Lütfen daha sonra tekrar deneyin.'
                : 'You have reached your hourly message limit (Maximum 20 messages/hour). Please try again later.'
        ]);
        exit;
    }

    // Log current message timestamp
    $_SESSION['chatbot_messages_timestamps'][] = $now;

// Decode input payload
$userMessage = isset($_POST['message']) ? trim($_POST['message']) : '';
if (empty($userMessage)) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $userMessage = isset($jsonInput['message']) ? trim($jsonInput['message']) : '';
}

if (empty($userMessage)) {
    echo json_encode([
        'success' => false,
        'error' => 'Message is empty'
    ]);
    exit;
}

// ─── 2. Smart Keyword Matching & Helper ───
// Helper function to check if message matches a keyword with smart word-boundary rules for short queries.
if (!function_exists('matchKeywordHelper')) {
    function matchKeywordHelper($lowerMsg, $keyword) {
        $len = mb_strlen($keyword, 'UTF-8');
        if ($len <= 3) {
            // Enforce word boundaries for short words (<= 3 chars) to avoid false positives (e.g. "hi" inside "this", "al" inside "kalem")
            $cleanMsg = ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lowerMsg)) . ' ';
            return mb_strpos($cleanMsg, ' ' . $keyword . ' ') !== false;
        }
        return mb_strpos($lowerMsg, $keyword) !== false;
    }
}

$detectedLang = i18nGetLocale() === 'tr' ? 'tr' : 'en';
$trKeywords = [
    'merhaba', 'selam', 'nasil', 'nedir', 'neler', 'urun', 'ürün', 'satis', 'satış', 'ekle', 'ilan', 'kural', 
    'yasak', 'bulusma', 'buluşma', 'güvenlik', 'guvenlik', 'ödeme', 'odeme', 'nakit', 'destek', 'iletişim', 
    'iletisim', 'yönetici', 'yonetici', 'nasıl', 'yardım', 'yardim', 'olur', 'mi', 'mı', 'mu', 'mü', 'siteniz',
    'durum', 'seçenek', 'liste', 'sepet', 'istek', 'kutusu', 'şifre', 'posta', 'pwa', 'mobil', 'app', 'uygulama'
];
$lowerMessage = mb_strtolower($userMessage, 'UTF-8');
foreach ($trKeywords as $word) {
    if (matchKeywordHelper($lowerMessage, $word)) {
        $detectedLang = 'tr';
        break;
    }
}

// ─── 3. Expanded FAQ Database (Instant Keyword Matching & Greetings) ───
$faqs = [
    // --- Greetings & Chit-Chat (Evaluated First for instant friendly conversation!) ---
    [
        'keywords' => ['hi', 'hello', 'hey', 'greetings', 'merhaba', 'selam', 'selamlar'],
        'answers' => [
            'en' => "Hello! 👋 I'm here and ready to help. How can I assist you with CampusMarket today?",
            'tr' => "Merhaba! 👋 Buradayım ve yardıma hazırım. Bugün CampusMarket ile ilgili size nasıl yardımcı olabilirim?"
        ]
    ],
    [
        'keywords' => ['how are you', 'how is it going', 'how are you doing', 'nasılsın', 'nasilsin', 'nasıl gidiyor', 'nasil gidiyor', 'ne haber', 'naber'],
        'answers' => [
            'en' => "I'm doing great, thank you for asking! 😊 Ready to help you navigate CampusMarket. What's on your mind?",
            'tr' => "Çok iyiyim, sorduğunuz için teşekkürler! 😊 CampusMarket'te size yardımcı olmaya hazırım. Aklınızda ne var?"
        ]
    ],
    [
        'keywords' => ['who are you', 'what is your name', 'kimsin', 'adın ne', 'adin ne', 'sen kimsin'],
        'answers' => [
            'en' => "I am the CampusMarket AI Assistant, your friendly guide for buying, selling, and staying safe on campus! 🚀",
            'tr' => "Ben CampusMarket Yapay Zeka Asistanıyım; kampüste güvenli alışveriş, ilan verme ve kurallar konusunda dost canlısı rehberinizim! 🚀"
        ]
    ],
    [
        'keywords' => ['what can you do', 'how can you help', 'help me', 'ne yapabilirsin', 'nasıl yardımcı olabilirsin', 'nasil yardimci olabilirsin', 'yardım et', 'yardim et'],
        'answers' => [
            'en' => "I can guide you on listing items, safe meeting locations on campus, community rules, payment methods, wishlists, and site promotions! Ask me anything about CampusMarket.",
            'tr' => "Ürün listeleme, kampüsteki güvenli buluşma noktaları, topluluk kuralları, ödeme yöntemleri, istek listesi ve ilan öne çıkarma konularında size rehberlik edebilirim! CampusMarket hakkında her şeyi sorabilirsiniz."
        ]
    ],
    // --- General FAQs ---
    [
        'keywords' => ['post', 'sell', 'list', 'create', 'ilan', 'satmak', 'satış', 'satis', 'eklemek', 'ekle', 'ürün', 'urun'],
        'answers' => [
            'en' => "You can list an item by clicking the \"Post an Item\" button in the menu or navigating directly to the [create listing page](" . BASE_URL . "pages/create_listing.php). Fill in the title, description, category, and price, then upload up to 5 clear photos.",
            'tr' => "Menüdeki \"Ürün Paylaş\" butonuna tıklayarak veya doğrudan [ilan oluşturma sayfasına](" . BASE_URL . "pages/create_listing.php) giderek bir ürün listeleyebilirsiniz. Başlığı, açıklamayı, kategoriyi ve fiyatı doldurun, ardından 5 adede kadar net fotoğraf yükleyin."
        ]
    ],
    [
        'keywords' => ['meeting', 'point', 'safety', 'safe', 'place', 'buluşma', 'bulusma', 'güvenlik', 'guvenlik', 'nokta', 'yer', 'neresi'],
        'answers' => [
            'en' => "For your safety, we recommend meeting in well-lit public campus areas. Great options include the Student Union Building, Campus Library Main Lobby, Campus Security Office, or on-campus coffee shops. Read more details on our [safety guidelines page](" . BASE_URL . "pages/safety.php).",
            'tr' => "Güvenliğiniz için kampüsteki halka açık, iyi aydınlatılmış yerlerde buluşmanızı öneririz. Önerilen yerler: Öğrenci Birliği Binası, Kampüs Kütüphanesi Girişi, Kampüs Güvenlik Ofisi veya kampüs içi kafelerdir. Detaylar için [güvenlik kılavuzumuz sayfasını](" . BASE_URL . "pages/safety.php) inceleyebilirsiniz."
        ]
    ],
    [
        'keywords' => ['rules', 'community', 'forbidden', 'prohibited', 'weapons', 'drugs', 'illegal', 'kurallar', 'yasak', 'kural', 'topluluk', 'silah', 'uyuşturucu'],
        'answers' => [
            'en' => "To keep our community safe, hate speech, bullying, weapons, illegal substances, and university policy violations are strictly prohibited. Always write accurate descriptions and honor your buying/selling commitments. Review the full code of conduct on our [community rules page](" . BASE_URL . "pages/rules.php).",
            'tr' => "Topluluğumuzu güvenli tutmak için hakaret, zorbalık, silahlar, yasa dışı maddeler ve üniversite politikası ihlalleri kesinlikle yasaktır. İlanlarınızda her zaman doğru açıklamalar yapın ve alım/satım anlaşmalarınıza sadık kalın. Detaylar için [topluluk kuralları sayfamızı](" . BASE_URL . "pages/rules.php) inceleyebilirsiniz."
        ]
    ],
    [
        'keywords' => ['payment', 'pay', 'cash', 'zelle', 'venmo', 'stripe', 'ödeme', 'odeme', 'nakit', 'para', 'nasıl öderim', 'nasil oderim', 'kart'],
        'answers' => [
            'en' => "Transactions are completed in-person during the exchange on campus. We strongly recommend cash or secure digital transfers (Zelle, Venmo, bank transfers). Never send money in advance! Stripe credit card processing is used exclusively for site promotions on our [promotions page](" . BASE_URL . "pages/promotions.php).",
            'tr' => "Ödemeler, kampüsteki yüz yüze takas esnasında yapılır. Nakit veya güvenli dijital transferleri (Zelle, Venmo, IBAN transferi) kullanmanızı tavsiye ederiz. Asla önceden para göndermeyin! Kredi kartı ödemeleri yalnızca [promosyonlar sayfamızda](" . BASE_URL . "pages/promotions.php) ilanları öne çıkarmak için kullanılır."
        ]
    ],
    [
        'keywords' => ['contact', 'support', 'admin', 'report', 'help', 'destek', 'iletişim', 'iletisim', 'yönetici', 'yonetici', 'rapor', 'şikayet', 'sikayet', 'yardım', 'yardim'],
        'answers' => [
            'en' => "You can submit reports on our [issue reporting page](" . BASE_URL . "pages/report.php) or start a direct message with the admin by visiting your [inbox page](" . BASE_URL . "pages/inbox.php) and selecting the support option.",
            'tr' => "Herhangi bir konuyu bildirmek için [sorun bildirme sayfamızı](" . BASE_URL . "pages/report.php) kullanabilir veya [gelen kutusu sayfanızı](" . BASE_URL . "pages/inbox.php) ziyaret ederek doğrudan yöneticiyle destek sohbeti başlatabilirsiniz."
        ]
    ],
    [
        'keywords' => ['promote', 'featured', 'promotions', 'stripe', 'boost', 'promosyon', 'öne çıkar', 'öne çikar', 'reklam', 'vitrin'],
        'answers' => [
            'en' => "Boost your listing's visibility by making it featured! Head to the [promotions page](" . BASE_URL . "pages/promotions.php) to pick an advertising plan, securely handled via Stripe checkout integration.",
            'tr' => "İlanınızın görünürlüğünü artırmak için onu öne çıkarabilirsiniz! Stripe ödeme altyapısıyla güvenli şekilde reklam vermek için [promosyonlar sayfamızı](" . BASE_URL . "pages/promotions.php) ziyaret ederek bir tanıtım planı seçin."
        ]
    ],
    [
        'keywords' => ['wishlist', 'favorite', 'save', 'later', 'istek', 'favori', 'kaydet', 'kaydetmek'],
        'answers' => [
            'en' => "Click the \"Save for Later\" button on any listing to add it to your wishlist. You can manage saved items anytime on the [wishlist page](" . BASE_URL . "pages/wishlist.php).",
            'tr' => "Herhangi bir ilanda \"Sonra Kaydet\" butonuna tıklayarak ürünü istek listenize ekleyebilirsiniz. Kaydedilen ürünleri [istek listesi sayfasından](file:///d:/xampp/htdocs/CampusMarket/pages/wishlist.php) istediğiniz zaman yönetebilirsiniz."
        ]
    ],
    [
        'keywords' => ['delete', 'remove', 'edit', 'change', 'sil', 'silmek', 'düzenle', 'duzenle', 'değiştir', 'degistir'],
        'answers' => [
            'en' => "You can update price, add discounts, swap photos, or delete your own active listings directly on the product's details page. Deleted items go to the [recycle bin page](" . BASE_URL . "pages/recycle_bin.php) where you can restore them.",
            'tr' => "Ürün detay sayfasında kendi aktif ilanınızın fiyatını güncelleyebilir, indirim ekleyebilir, fotoğrafları değiştirebilir veya ilanı silebilirsiniz. Silinen ilanlar, onları geri yükleyebileceğiniz [geri dönüşüm kutusu sayfasına](" . BASE_URL . "pages/recycle_bin.php) gider."
        ]
    ],
    [
        'keywords' => ['order', 'buy', 'transaction', 'deal', 'sipariş', 'siparis', 'almak', 'al', 'satın', 'satin', 'anlaşma', 'anlasma'],
        'answers' => [
            'en' => "Go to a product page and click \"Message Seller\" to open a chat. Once ready, you can propose an order from the chat screen. If the transaction completes, the seller confirms the deal to delist the item.",
            'tr' => "Bir ürün sayfasına gidip \"Satıcıya Mesaj Gönder\" butonuna tıklayarak sohbeti başlatın. Hazır olduğunuzda sohbet ekranından sipariş teklif edebilirsiniz. Satış bittiğinde satıcı anlaşmayı onaylayarak ürünü yayından kaldırır."
        ]
    ],
    [
        'keywords' => ['trusted', 'score', 'rating', 'star', 'reviews', 'güvenilir', 'guvenilir', 'puan', 'yıldız', 'yildiz', 'değerlendirme', 'degerlendirme'],
        'answers' => [
            'en' => "Trusted Sellers are users with high average ratings from verified campus transactions. You can review their score and student feedback directly on their public profile page.",
            'tr' => "Güvenilir Satıcılar, doğrulanmış kampüs işlemlerinden yüksek ortalama puan alan kullanıcılardır. Puanlarını ve öğrencilerin geri bildirimlerini doğrudan satıcının profil sayfasından inceleyebilirsiniz."
        ]
    ],
    [
        'keywords' => ['pwa', 'mobile', 'app', 'install', 'uygulama', 'yükle', 'yukle', 'indir', 'telefon'],
        'answers' => [
            'en' => "CampusMarket is a Progressive Web App (PWA). You can install it on your iOS or Android device directly from your browser by selecting \"Add to Home Screen\" for a full-screen, native experience.",
            'tr' => "CampusMarket bir Progresif Web Uygulamasıdır (PWA). Tarayıcınızdan \"Ana Ekrana Ekle\" seçeneğini seçerek tam ekran ve yerel bir mobil uygulama deneyimi için doğrudan iOS veya Android cihazınıza yükleyebilirsiniz."
        ]
    ],
    [
        'keywords' => ['condition', 'quality', 'new', 'used', 'poor', 'durum', 'durumu', 'yeni', 'kullanılmış', 'yıpranmış', 'yipyip'],
        'answers' => [
            'en' => "Items are categorized into 4 condition types: New (unopened), Like New (excellent), Used (minor wear, functional), or Poor (defects or significant wear). Details are listed on each product card.",
            'tr' => "Ürünler 4 durum tipinde sınıflandırılır: Yeni (açılmamış), Yeni Gibi (mükemmel), Kullanılmış (hafif yıpranmış, işlevsel) veya Yıpranmış (belirgin kusurları olan)."
        ]
    ],
    [
        'keywords' => ['email', 'verify', 'resend', 'token', 'eposta', 'e-posta', 'doğrula', 'dogrula', 'gönder', 'gonder'],
        'answers' => [
            'en' => "Email verification is required during registration. If you didn't receive the verification email, you can request a new token on our [email verification page](" . BASE_URL . "pages/verify_email.php).",
            'tr' => "Kayıt sırasında e-posta doğrulaması gereklidir. Doğrulama e-postasını almadıysanız [e-posta doğrulama sayfamızdan](" . BASE_URL . "pages/verify_email.php) yeni bir doğrulama bağlantısı talep edebilirsiniz."
        ]
    ]
];

// Perform local FAQ matching
foreach ($faqs as $faq) {
    foreach ($faq['keywords'] as $keyword) {
        if (matchKeywordHelper($lowerMessage, $keyword)) {
            echo json_encode([
                'success' => true,
                'response' => $faq['answers'][$detectedLang],
                'unknown' => false,
                'admin_id' => $adminId
            ]);
            exit;
        }
    }
}

// ─── 4. Conversation Memory Setup (Last 10 turns = 20 messages max) ───
if (!isset($_SESSION['chatbot_history'])) {
    $_SESSION['chatbot_history'] = [];
}
// Truncate memory to keep only last 20 messages (10 turns)
if (count($_SESSION['chatbot_history']) > 20) {
    $_SESSION['chatbot_history'] = array_slice($_SESSION['chatbot_history'], -20);
}

// ─── 5. Gemini AI Fallback Call ───
$apiKey = getenv('CHATBOT_GEMINI_API_KEY') ?: getenv('GEMINI_API_KEY');
if (!$apiKey) {
    echo json_encode([
        'success' => true,
        'response' => 'UNKNOWN',
        'unknown' => true,
        'admin_id' => $adminId
    ]);
    exit;
}

// Build highly detailed system instruction for Gemini 2.5 Flash
$systemInstruction = "You are a friendly, premium campus marketplace chatbot for the site 'CampusMarket'. You speak English and Turkish.\n\n" .
                     "Platform Specific Knowledge:\n" .
                     "- Listing items: Users create them at the [create listing page](" . BASE_URL . "pages/create_listing.php). Requires title, description, category, price, up to 5 photos.\n" .
                     "- Safety: Meet in well-lit public spots on campus (Student Union Building, Campus Library lobby, Campus Security Office). Check the items thoroughly. Never pay in advance.\n" .
                     "- Payments: Cash, Venmo, Zelle, or bank transfers during the trade. Stripe is used solely for site promotions.\n" .
                     "- Rules: Be respectful. Weapons, illegal drugs, recalled items, counterfeits, and university policy violations are strictly forbidden. Honor deals.\n" .
                     "- Support / Contact Admin: Users can report issues at [issue reporting page](" . BASE_URL . "pages/report.php) or message administrators via their [inbox page](" . BASE_URL . "pages/inbox.php).\n" .
                     "- Promotions: Users can feature listings on the [promotions page](" . BASE_URL . "pages/promotions.php) via safe Stripe payments.\n" .
                     "- Wishlists: Managed at the [wishlist page](" . BASE_URL . "pages/wishlist.php).\n" .
                     "- Deletions: Managed at the [recycle bin page](" . BASE_URL . "pages/recycle_bin.php).\n" .
                     "- App Installation: It is a PWA; select 'Add to Home Screen' in your browser.\n\n" .
                     "Rules of conduct for you:\n" .
                     "1. Identify the user's prompt language (English or Turkish) and respond naturally in that exact language.\n" .
                     "2. Friendly Greetings / Chit-Chat ARE FULLY ALLOWED. Respond warmly and politely to prompts like 'hi', 'merhaba', 'how are you', 'nasılsın', 'who are you', 'greetings', 'help me'. Keep answers social, brief, and supportive.\n" .
                     "3. If they ask a valid question about CampusMarket, explain in 2-3 brief, helpful sentences. Use markdown links ONLY if referring to the page links listed above. Format them strictly as [Page Description](URL).\n" .
                     "4. CRITICAL RULES OF FALLBACK:\n" .
                     "   - If they ask general questions unrelated to CampusMarket (e.g. 'write code for me', 'recipe for lasagna', 'weather in Paris', 'who won the world cup') OR if they ask about something not in your knowledge base (e.g. server issues, database seeds, Stripe API errors), you MUST respond with EXACTLY and ONLY the word: UNKNOWN\n" .
                     "   - Do not add punctuation, formatting, or friendly greetings when returning UNKNOWN. Just output the raw word UNKNOWN.\n" .
                     "5. Utilize the multi-turn context dynamically to answer follow-up questions (e.g., if they ask 'is it free?' right after talking about listing items, answer in that context).";

// Build multi-turn request structure
$requestContents = array_merge(
    $_SESSION['chatbot_history'],
    [['role' => 'user', 'parts' => [['text' => $userMessage]]]]
);

$requestBody = [
    'contents' => $requestContents,
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemInstruction]
        ]
    ]
];

$model = 'gemini-2.5-flash';
$aiText = '';
$httpCode = 0;

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
$response = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Fast fail-safe 4-second timeout to prevent gateway timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    // Fallback to file_get_contents stream context if cURL is not available
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($requestBody),
            'timeout' => 4, // Fast fail-safe 4-second timeout to prevent gateway timeout
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];
    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if (isset($http_response_header)) {
        preg_match('{HTTP\/\S*\s(\d\d\d)}', $http_response_header[0], $matches);
        $httpCode = (int)($matches[1] ?? 0);
    }
}

if ($httpCode === 200 && $response !== false) {
    $data = json_decode($response, true);
    $aiText = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

// Evaluate AI response
if ($httpCode !== 200 || $aiText === '' || stripos($aiText, 'UNKNOWN') !== false) {
    // If API failed, or AI returned UNKNOWN, trigger the escalation path
    echo json_encode([
        'success' => true,
        'response' => 'UNKNOWN',
        'unknown' => true,
        'admin_id' => $adminId
    ]);
} else {
    // Save successful turn to history memory (last 10 turns max)
    $_SESSION['chatbot_history'][] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];
    $_SESSION['chatbot_history'][] = ['role' => 'model', 'parts' => [['text' => $aiText]]];
    
    echo json_encode([
        'success' => true,
        'response' => $aiText,
        'unknown' => false,
        'admin_id' => $adminId
    ]);
}
// Closing try block
} catch (Throwable $e) {
    // Safely output the escalation card if any runtime error or undefined call occurs
    echo json_encode([
        'success' => true,
        'response' => 'UNKNOWN',
        'unknown' => true,
        'admin_id' => $adminId
    ]);
}
exit;
