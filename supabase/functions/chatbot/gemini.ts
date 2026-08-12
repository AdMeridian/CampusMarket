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
    `Site base URL: ${b}\n\n` +
    `## Conversation awareness (CRITICAL)\n` +
    `Before composing your reply, carefully read the full conversation history above.\n` +
    `- Identify what you have ALREADY told the user in this conversation.\n` +
    `- Identify the user's TRUE intent in their latest message: are they asking something new, reporting a problem, asking for clarification, or expressing frustration?\n` +
    `- NEVER repeat or re-summarise information you already provided unless the user explicitly asks you to repeat it.\n` +
    `- If the user reports an error, a problem, or says something is not working AFTER you gave them instructions, do NOT repeat those instructions. Instead, acknowledge the issue, ask what specific error or symptom they are seeing, and offer targeted help or suggest contacting an admin.\n` +
    `- If the user's message is a follow-up (e.g. "it didn't work", "I still get an error", "that's not what I meant"), treat it as a continuation — respond to their actual new concern, not the original topic.\n\n` +
    `## Response style\n` +
    `Answer concisely (2-4 sentences). Use markdown links [text](url) when referencing site pages.\n` +
    `Vary your phrasing and sentence structure — never open with the same line twice in a conversation.\n` +
    `For off-topic questions completely unrelated to CampusMarket, respond with exactly: UNKNOWN\n\n` +
    `## Key site pages\n` +
    `- Create listing: ${b}pages/create_listing.php\n` +
    `- Safety guidelines: ${b}pages/safety.php\n` +
    `- Community rules: ${b}pages/rules.php\n` +
    `- Promotions / featured listings: ${b}pages/promotions.php\n` +
    `- Wishlist: ${b}pages/wishlist.php\n` +
    `- Inbox / messages: ${b}pages/inbox.php\n` +
    `- Report a problem: ${b}pages/report.php\n\n` +
    `## CampusMarket facts\n` +
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
          temperature: 0.7,
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
