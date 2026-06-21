(function () {
  var env = window.__env || {};
  var url = (env.SUPABASE_URL || "").trim();
  var anonKey = (env.SUPABASE_ANON_KEY || "").trim();

  if (!url || !anonKey || !window.supabase || !window.supabase.createClient) {
    return;
  }

  window.CampusMarketSupabase = window.supabase.createClient(url, anonKey, {
    auth: {
      persistSession: false,
      autoRefreshToken: false,
      detectSessionInUrl: false,
    },
    realtime: {
      params: {
        eventsPerSecond: 10,
      },
    },
  });

  var session = window.__supabaseSession || null;
  if (!session || !session.access_token) {
    window.CampusMarketSupabaseReady = Promise.resolve(false);
    return;
  }

  window.CampusMarketSupabaseReady = window.CampusMarketSupabase.auth
    .setSession({
      access_token: session.access_token,
      refresh_token: "",
    })
    .then(function (result) {
      return !result.error;
    })
    .catch(function () {
      return false;
    });
})();
