type HistoryTurn = { role: string; parts: { text: string }[] };

export async function callGemini(
  apiKey: string,
  userMessage: string,
  history: HistoryTurn[],
  siteBaseUrl: string,
  lang: "en" | "tr",
): Promise<string> {
  const b = siteBaseUrl.endsWith("/") ? siteBaseUrl : `${siteBaseUrl}/`;
  const systemInstruction =
    `You are the CampusMarket Support assistant — a friendly campus marketplace helper. Respond in ${lang === "tr" ? "Turkish" : "English"}.\n` +
    `Site base URL: ${b}\n` +
    `Answer CampusMarket questions concisely (2-4 sentences). Use markdown links [text](url) when referencing site pages.\n` +
    `For off-topic questions or things completely unrelated to CampusMarket, respond with exactly: UNKNOWN\n\n` +
    `Key site pages:\n` +
    `- Create listing: ${b}pages/create_listing.php\n` +
    `- Safety guidelines: ${b}pages/safety.php\n` +
    `- Community rules: ${b}pages/rules.php\n` +
    `- Promotions / featured listings: ${b}pages/promotions.php\n` +
    `- Wishlist: ${b}pages/wishlist.php\n` +
    `- Inbox / messages: ${b}pages/inbox.php\n` +
    `- Report a problem: ${b}pages/report.php\n\n` +
    `Important facts about CampusMarket:\n` +
    `- Payments are made in person on campus (cash or agreed method). Stripe/card is only for purchasing listing promotions.\n` +
    `- Never pay in advance or send money before meeting in person.\n` +
    `- Meet buyers/sellers in well-lit, public campus areas.\n` +
    `- To buy: open a product page and click "Message Seller".\n` +
    `- To sell: go to the create listing page, fill in details and upload up to 5 photos.\n` +
    `- Prohibited items include weapons, drugs, and anything illegal.\n` +
    `- For support or to report an issue, use the report page or message an admin via the inbox.`;

  const contents = [
    ...history.slice(-20),
    { role: "user", parts: [{ text: userMessage }] },
  ];

  const model = "gemini-3.1-flash-lite";
  const url =
    `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${encodeURIComponent(apiKey)}`;

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 25000);

  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      signal: controller.signal,
      body: JSON.stringify({
        contents,
        systemInstruction: { parts: [{ text: systemInstruction }] },
        generationConfig: {
          temperature: 0.4,
          maxOutputTokens: 512,
        },
      }),
    });

    if (!res.ok) {
      console.error("Gemini HTTP", res.status, await res.text());
      return "";
    }

    const data = await res.json();
    return (data?.candidates?.[0]?.content?.parts?.[0]?.text ?? "").trim();
  } catch (err) {
    console.error("Gemini error", err);
    return "";
  } finally {
    clearTimeout(timeout);
  }
}
