import { detectLang, matchKeyword } from "./match.ts";

type Faq = {
  keywords: string[];
  /** Multiple variants per language — one is picked randomly each time. */
  variants: { en: string[]; tr: string[] };
};

function pick(arr: string[]): string {
  return arr[Math.floor(Math.random() * arr.length)];
}

/** Only greetings and identity are handled as instant canned responses.
 *  Multiple variants are defined per entry so the bot never sounds like
 *  a broken record. Everything else falls through to Gemini. */
function faqs(): Faq[] {
  return [
    {
      keywords: ["hi", "hello", "hey", "greetings", "merhaba", "selam", "selamlar"],
      variants: {
        en: [
          "Hello! I'm here to help with CampusMarket. What can I assist you with today?",
          "Hey there! 👋 Got a question about CampusMarket? Fire away!",
          "Hi! Happy to help you with anything CampusMarket-related. What's on your mind?",
          "Hello! What can I help you with on CampusMarket today?",
        ],
        tr: [
          "Merhaba! CampusMarket ile ilgili yardımcı olmak için buradayım. Bugün size nasıl yardımcı olabilirim?",
          "Selam! 👋 CampusMarket hakkında bir sorunuz mu var? Buyurun!",
          "Merhaba! CampusMarket ile ilgili her konuda yardımcı olmaktan memnuniyet duyarım. Aklınızda ne var?",
          "Merhaba! Bugün size nasıl yardımcı olabilirim?",
        ],
      },
    },
    {
      keywords: ["how are you", "how is it going", "how are you doing", "nasılsın", "nasilsin", "nasıl gidiyor", "nasil gidiyor", "ne haber", "naber"],
      variants: {
        en: [
          "I'm doing great, thank you for asking! 😊 Ready to help you navigate CampusMarket. What's on your mind?",
          "All good here! Thanks for asking 😄 How can I help you on CampusMarket today?",
          "Doing well! 🙂 What can I help you with?",
          "Great, thanks! Always here to help with CampusMarket. What do you need?",
        ],
        tr: [
          "Çok iyiyim, sorduğunuz için teşekkürler! 😊 CampusMarket'te size yardımcı olmaya hazırım. Aklınızda ne var?",
          "İyiyim, sağ olun! 😄 CampusMarket'te bugün size nasıl yardımcı olabilirim?",
          "Harika! 🙂 Size nasıl yardımcı olabilirim?",
          "Teşekkürler! CampusMarket konusunda neye ihtiyacınız var?",
        ],
      },
    },
    {
      keywords: ["who are you", "what is your name", "kimsin", "adın ne", "adin ne", "sen kimsin"],
      variants: {
        en: [
          "I am the CampusMarket Support assistant — your guide for buying, selling, and staying safe on campus.",
          "I'm the CampusMarket Support bot! I'm here to help you with listings, safety, payments, and anything else on the platform.",
          "Think of me as your CampusMarket helper — I can answer questions about buying, selling, rules, and more.",
          "I'm the CampusMarket virtual assistant, here to make your campus marketplace experience smooth and safe.",
        ],
        tr: [
          "Ben CampusMarket Destek asistanıyım; kampüste güvenli alışveriş, ilan verme ve kurallar konusunda rehberinizim.",
          "Ben CampusMarket destek botuyum! İlanlar, güvenlik, ödemeler ve platform hakkında her konuda yardımcı olmak için buradayım.",
          "Beni CampusMarket yardımcınız olarak düşünün — alım, satım, kurallar ve daha fazlası hakkında sorularınızı yanıtlayabilirim.",
          "Ben CampusMarket sanal asistanıyım; kampüs pazaryeri deneyiminizi sorunsuz ve güvenli kılmak için buradayım.",
        ],
      },
    },
  ];
}

export function matchFaq(message: string, locale: string, _siteBaseUrl: string): string | null {
  const lower = message.toLowerCase();
  const lang = detectLang(message, locale);

  for (const faq of faqs()) {
    for (const keyword of faq.keywords) {
      if (matchKeyword(lower, keyword)) {
        return pick(faq.variants[lang]);
      }
    }
  }
  return null;
}
