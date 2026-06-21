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
    `You are a friendly campus marketplace chatbot for CampusMarket. Respond in ${lang === "tr" ? "Turkish" : "English"}.\n` +
    `Site base URL: ${b}\n` +
    "Answer CampusMarket questions briefly (2-3 sentences). Use markdown links [text](url) when linking site pages.\n" +
    "For off-topic questions or things you cannot answer, respond with exactly: UNKNOWN";

  const contents = [
    ...history.slice(-20),
    { role: "user", parts: [{ text: userMessage }] },
  ];

  const model = "gemini-2.0-flash";
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
