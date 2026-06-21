export function matchKeyword(lowerMsg: string, keyword: string): boolean {
  const len = [...keyword].length;
  if (len <= 3) {
    const cleanMsg = ` ${lowerMsg.replace(/[^\p{L}\p{N}]+/gu, " ").trim()} `;
    return cleanMsg.includes(` ${keyword} `);
  }
  return lowerMsg.includes(keyword);
}

export function detectLang(message: string, locale: string): "en" | "tr" {
  if (locale === "tr") return "tr";
  const lower = message.toLowerCase();
  const trKeywords = [
    "merhaba", "selam", "nasil", "nedir", "urun", "ürün", "satis", "satış", "ekle", "ilan",
    "kural", "yasak", "bulusma", "buluşma", "güvenlik", "guvenlik", "ödeme", "odeme", "nakit",
    "destek", "iletişim", "iletisim", "yönetici", "yonetici", "nasıl", "yardım", "yardim",
  ];
  return trKeywords.some((word) => matchKeyword(lower, word)) ? "tr" : "en";
}
