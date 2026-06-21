import { detectLang, matchKeyword } from "./match.ts";

type Faq = {
  keywords: string[];
  answers: { en: string; tr: string };
};

function faqs(base: string): Faq[] {
  const b = base.endsWith("/") ? base : `${base}/`;
  return [
    {
      keywords: ["hi", "hello", "hey", "greetings", "merhaba", "selam", "selamlar"],
      answers: {
        en: "Hello! 👋 I'm here and ready to help. How can I assist you with CampusMarket today?",
        tr: "Merhaba! 👋 Buradayım ve yardıma hazırım. Bugün CampusMarket ile ilgili size nasıl yardımcı olabilirim?",
      },
    },
    {
      keywords: ["how are you", "how is it going", "how are you doing", "nasılsın", "nasilsin", "nasıl gidiyor", "nasil gidiyor", "ne haber", "naber"],
      answers: {
        en: "I'm doing great, thank you for asking! 😊 Ready to help you navigate CampusMarket. What's on your mind?",
        tr: "Çok iyiyim, sorduğunuz için teşekkürler! 😊 CampusMarket'te size yardımcı olmaya hazırım. Aklınızda ne var?",
      },
    },
    {
      keywords: ["who are you", "what is your name", "kimsin", "adın ne", "adin ne", "sen kimsin"],
      answers: {
        en: "I am the CampusMarket AI Assistant, your friendly guide for buying, selling, and staying safe on campus! 🚀",
        tr: "Ben CampusMarket Yapay Zeka Asistanıyım; kampüste güvenli alışveriş, ilan verme ve kurallar konusunda dost canlısı rehberinizim! 🚀",
      },
    },
    {
      keywords: ["what can you do", "how can you help", "help me", "ne yapabilirsin", "nasıl yardımcı olabilirsin", "nasil yardimci olabilirsin", "yardım et", "yardim et"],
      answers: {
        en: "I can guide you on listing items, safe meeting locations on campus, community rules, payment methods, wishlists, and site promotions! Ask me anything about CampusMarket.",
        tr: "Ürün listeleme, kampüsteki güvenli buluşma noktaları, topluluk kuralları, ödeme yöntemleri, istek listesi ve ilan öne çıkarma konularında size rehberlik edebilirim! CampusMarket hakkında her şeyi sorabilirsiniz.",
      },
    },
    {
      keywords: ["post", "sell", "list", "create", "ilan", "satmak", "satış", "satis", "eklemek", "ekle", "ürün", "urun"],
      answers: {
        en: `You can list an item via the [create listing page](${b}pages/create_listing.php). Fill in the title, description, category, and price, then upload up to 5 clear photos.`,
        tr: `Menüdeki "Ürün Paylaş" butonuna tıklayarak veya [ilan oluşturma sayfasına](${b}pages/create_listing.php) giderek bir ürün listeleyebilirsiniz.`,
      },
    },
    {
      keywords: ["meeting", "point", "safety", "safe", "place", "buluşma", "bulusma", "güvenlik", "guvenlik", "nokta", "yer", "neresi"],
      answers: {
        en: `Meet in well-lit public campus areas. See our [safety guidelines page](${b}pages/safety.php).`,
        tr: `Güvenliğiniz için kampüsteki halka açık, iyi aydınlatılmış yerlerde buluşun. [Güvenlik kılavuzumuzu](${b}pages/safety.php) inceleyin.`,
      },
    },
    {
      keywords: ["rules", "community", "forbidden", "prohibited", "weapons", "drugs", "illegal", "kurallar", "yasak", "kural", "topluluk", "silah", "uyuşturucu"],
      answers: {
        en: `Review the full code of conduct on our [community rules page](${b}pages/rules.php).`,
        tr: `Detaylar için [topluluk kuralları sayfamızı](${b}pages/rules.php) inceleyin.`,
      },
    },
    {
      keywords: ["payment", "pay", "cash", "zelle", "venmo", "stripe", "ödeme", "odeme", "nakit", "para", "nasıl öderim", "nasil oderim", "kart"],
      answers: {
        en: `Payments happen in person on campus. Stripe is only for [promotions](${b}pages/promotions.php). Never pay in advance.`,
        tr: `Ödemeler kampüste yüz yüze yapılır. Kart ödemeleri yalnızca [promosyonlar](${b}pages/promotions.php) içindir.`,
      },
    },
    {
      keywords: ["contact", "support", "admin", "report", "help", "destek", "iletişim", "iletisim", "yönetici", "yonetici", "rapor", "şikayet", "sikayet", "yardım", "yardim"],
      answers: {
        en: `Use the [report page](${b}pages/report.php) or message admin via your [inbox](${b}pages/inbox.php).`,
        tr: `[Sorun bildirme sayfamızı](${b}pages/report.php) kullanın veya [gelen kutunuzdan](${b}pages/inbox.php) destek başlatın.`,
      },
    },
    {
      keywords: ["promote", "featured", "promotions", "stripe", "boost", "promosyon", "öne çıkar", "öne çikar", "reklam", "vitrin"],
      answers: {
        en: `Feature listings on the [promotions page](${b}pages/promotions.php).`,
        tr: `İlanları [promosyonlar sayfasından](${b}pages/promotions.php) öne çıkarabilirsiniz.`,
      },
    },
    {
      keywords: ["wishlist", "favorite", "save", "later", "istek", "favori", "kaydet", "kaydetmek"],
      answers: {
        en: `Manage saved items on the [wishlist page](${b}pages/wishlist.php).`,
        tr: `Kaydedilen ürünleri [istek listesi sayfasından](${b}pages/wishlist.php) yönetebilirsiniz.`,
      },
    },
    {
      keywords: ["order", "buy", "transaction", "deal", "sipariş", "siparis", "almak", "satın", "satin", "anlaşma", "anlasma"],
      answers: {
        en: "Open a product page and click \"Message Seller\" to chat and propose an order.",
        tr: "Ürün sayfasından \"Satıcıya Mesaj Gönder\" ile sohbet başlatıp sipariş teklif edebilirsiniz.",
      },
    },
  ];
}

export function matchFaq(message: string, locale: string, siteBaseUrl: string): string | null {
  const lower = message.toLowerCase();
  const lang = detectLang(message, locale);
  const list = faqs(siteBaseUrl || "/");

  for (const faq of list) {
    for (const keyword of faq.keywords) {
      if (matchKeyword(lower, keyword)) {
        return faq.answers[lang];
      }
    }
  }
  return null;
}
