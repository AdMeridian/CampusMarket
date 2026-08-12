import { detectLang, matchKeyword } from "./match.ts";

type Faq = {
  keywords: string[];
  answers: { en: string; tr: string };
};

/** Only greetings and identity are handled as instant canned responses.
 *  Everything else falls through to Gemini for full intent understanding. */
function faqs(): Faq[] {
  return [
    {
      keywords: ["hi", "hello", "hey", "greetings", "merhaba", "selam", "selamlar"],
      answers: {
        en: "Hello! I'm here to help with CampusMarket. What can I assist you with today?",
        tr: "Merhaba! CampusMarket ile ilgili yardımcı olmak için buradayım. Bugün size nasıl yardımcı olabilirim?",
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
        en: "I am the CampusMarket Support assistant — your guide for buying, selling, and staying safe on campus.",
        tr: "Ben CampusMarket Destek asistanıyım; kampüste güvenli alışveriş, ilan verme ve kurallar konusunda rehberinizim.",
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
        return faq.answers[lang];
      }
    }
  }
  return null;
}
