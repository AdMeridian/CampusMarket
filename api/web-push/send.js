const webpush = require('web-push');
const { createClient } = require('@supabase/supabase-js');

function json(res, status, data) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(data));
}

function isExpiredSubscriptionError(err) {
  if (!err || !err.statusCode) return false;
  return err.statusCode === 410 || err.statusCode === 404;
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    return json(res, 405, { ok: false, error: 'Method not allowed' });
  }

  const internalKey = (process.env.INTERNAL_PUSH_KEY || '').trim();
  const gotKey = (req.headers['x-internal-push-key'] || '').toString().trim();
  if (!internalKey || gotKey !== internalKey) {
    console.error('[web-push] Unauthorized push trigger attempt. Key mismatch or unset.');
    return json(res, 401, { ok: false, error: 'Unauthorized' });
  }

  const supabaseUrl = (process.env.SUPABASE_URL || '').trim();
  const serviceRoleKey = (process.env.SUPABASE_SERVICE_ROLE_KEY || '').trim();
  if (!supabaseUrl || !serviceRoleKey) {
    console.error('[web-push] Missing SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY.');
    return json(res, 500, { ok: false, error: 'Supabase server credentials not configured' });
  }

  const vapidPublicKey = (process.env.WEB_PUSH_PUBLIC_KEY || '').trim();
  const vapidPrivateKey = (process.env.WEB_PUSH_PRIVATE_KEY || '').trim();
  const subject = (process.env.WEB_PUSH_SUBJECT || 'mailto:support@campusmarketplace.site').trim();
  if (!vapidPublicKey || !vapidPrivateKey) {
    console.error('[web-push] Missing WEB_PUSH_PUBLIC_KEY or WEB_PUSH_PRIVATE_KEY.');
    return json(res, 500, { ok: false, error: 'VAPID keys not configured' });
  }

  let body = '';
  await new Promise((resolve) => {
    req.on('data', (chunk) => (body += chunk));
    req.on('end', resolve);
  });

  let payload;
  try {
    payload = JSON.parse(body || '{}');
  } catch (e) {
    return json(res, 400, { ok: false, error: 'Invalid JSON' });
  }

  const userId = Number(payload.userId);
  const title = (payload.title || 'CampusMarket').toString();
  const message = (payload.body || 'You have a new update.').toString();
  const url = (payload.url || '/pages/notifications.php').toString();

  if (!Number.isFinite(userId) || userId <= 0) {
    return json(res, 422, { ok: false, error: 'Missing/invalid userId' });
  }

  const supabase = createClient(supabaseUrl, serviceRoleKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  const { data: subs, error } = await supabase
    .from('web_push_subscriptions')
    .select('endpoint,p256dh,auth')
    .eq('user_id', userId)
    .limit(50);

  if (error) {
    return json(res, 500, { ok: false, error: 'Failed to load subscriptions', details: error.message });
  }

  if (!subs || subs.length === 0) {
    return json(res, 200, { ok: true, sent: 0, failed: 0, removed: 0, note: 'No subscriptions for user' });
  }

  webpush.setVapidDetails(subject, vapidPublicKey, vapidPrivateKey);

  const notificationPayload = JSON.stringify({
    title,
    body: message,
    url,
    icon: '/public/images/logo.png',
    badge: '/public/images/logo.png',
  });

  let sent = 0;
  let failed = 0;
  let removed = 0;

  await Promise.all(
    subs.map(async (s) => {
      const subscription = {
        endpoint: s.endpoint,
        keys: { p256dh: s.p256dh, auth: s.auth },
      };

      const options = {
        TTL: 3600,
        urgency: 'high',
        headers: {
          Urgency: 'high',
        },
      };

      try {
        await webpush.sendNotification(subscription, notificationPayload, options);
        sent += 1;
      } catch (e) {
        failed += 1;
        console.error(`[web-push] sendNotification failed for user ${userId} on ${s.endpoint}: status=${e.statusCode || 'N/A'}, body=${e.body || e.message}`);
        if (isExpiredSubscriptionError(e)) {
          await supabase
            .from('web_push_subscriptions')
            .delete()
            .eq('user_id', userId)
            .eq('endpoint', s.endpoint);
          removed += 1;
        }
      }
    })
  );

  console.log(`[web-push] Dispatched push for user ${userId}: sent=${sent}, failed=${failed}, removed=${removed}`);
  return json(res, 200, { ok: true, sent, failed, removed });
};
