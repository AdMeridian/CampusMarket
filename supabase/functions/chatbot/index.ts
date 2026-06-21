import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";
import { matchFaq } from "./faqs.ts";
import { callGemini } from "./gemini.ts";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

type HistoryTurn = { role: string; parts: { text: string }[] };

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, "Content-Type": "application/json" },
  });
}

async function getAdminId(): Promise<number> {
  const url = Deno.env.get("SUPABASE_URL");
  const key = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!url || !key) return 1;

  try {
    const supabase = createClient(url, key);
    const { data } = await supabase
      .from("users")
      .select("id")
      .eq("role", "admin")
      .order("id", { ascending: true })
      .limit(1)
      .maybeSingle();
    return data?.id ?? 1;
  } catch {
    return 1;
  }
}

function clientIp(req: Request): string {
  const forwarded = req.headers.get("x-forwarded-for") ?? "";
  const ip = forwarded.split(",")[0]?.trim() || req.headers.get("x-real-ip") || "unknown";
  return ip.slice(0, 45);
}

async function rateLimitAllow(req: Request): Promise<boolean> {
  const url = Deno.env.get("SUPABASE_URL");
  const key = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!url || !key) return true;

  const bucket = `chatbot:ip:${clientIp(req)}`.replace(/[^a-zA-Z0-9:_-]/g, "").slice(0, 120);
  if (!bucket) return true;

  try {
    const supabase = createClient(url, key);
    const { data, error } = await supabase.rpc("chatbot_rate_limit_allow", {
      p_bucket: bucket,
      p_max_hits: 40,
      p_window_seconds: 3600,
    });
    if (error) {
      console.error("rate limit rpc error", error.message);
      return true;
    }
    return data === true;
  } catch (e) {
    console.error("rate limit error", e);
    return true;
  }
}

Deno.serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    return json({ success: false, error: "method_not_allowed" }, 405);
  }

  const adminId = await getAdminId();

  try {
    if (!(await rateLimitAllow(req))) {
      return json({
        success: false,
        error: "rate_limit",
        response: "Too many requests. Please try again later.",
        admin_id: adminId,
      });
    }

    const payload = await req.json();
    const message = typeof payload?.message === "string" ? payload.message.trim() : "";
    const locale = typeof payload?.locale === "string" ? payload.locale : "en";
    const siteBaseUrl = typeof payload?.site_base_url === "string" ? payload.site_base_url : "";
    const history: HistoryTurn[] = Array.isArray(payload?.history) ? payload.history : [];

    if (!message) {
      return json({ success: false, error: "empty_message" }, 400);
    }

    const faqAnswer = matchFaq(message, locale, siteBaseUrl);
    if (faqAnswer) {
      return json({ success: true, response: faqAnswer, unknown: false, admin_id: adminId });
    }

    const apiKey = Deno.env.get("CHATBOT_GEMINI_API_KEY") ?? Deno.env.get("GEMINI_API_KEY");
    if (!apiKey) {
      return json({ success: true, response: "UNKNOWN", unknown: true, admin_id: adminId });
    }

    const lang = locale === "tr" ? "tr" : "en";
    const aiText = await callGemini(apiKey, message, history, siteBaseUrl, lang);
    const normalized = aiText.trim();

    if (!normalized || normalized.toUpperCase() === "UNKNOWN") {
      return json({ success: true, response: "UNKNOWN", unknown: true, admin_id: adminId });
    }

    return json({ success: true, response: normalized, unknown: false, admin_id: adminId });
  } catch (e) {
    console.error("chatbot handler error", e);
    return json({ success: true, response: "UNKNOWN", unknown: true, admin_id: adminId });
  }
});
