const crypto = require('crypto');

function json(res, status, data) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(data));
}

function readRawBody(req) {
  return new Promise((resolve) => {
    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
    });
    req.on('end', () => resolve(body));
  });
}

function verifyStripeSignature(rawBody, signatureHeader, secret) {
  if (!signatureHeader || !secret) return false;

  const parts = signatureHeader.split(',').reduce((acc, part) => {
    const [key, value] = part.split('=');
    if (key && value) acc[key.trim()] = value.trim();
    return acc;
  }, {});

  const timestamp = parts.t;
  const signature = parts.v1;
  if (!timestamp || !signature) return false;

  const signedPayload = `${timestamp}.${rawBody}`;
  const expected = crypto.createHmac('sha256', secret).update(signedPayload, 'utf8').digest('hex');
  try {
    return crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(signature, 'hex'));
  } catch (_) {
    return false;
  }
}

async function fulfillSession(sessionId) {
  const baseUrl = (process.env.BASE_URL || '').replace(/\/$/, '');
  const internalKey = (process.env.INTERNAL_PUSH_KEY || '').trim();
  if (!baseUrl || !internalKey) {
    throw new Error('BASE_URL or INTERNAL_PUSH_KEY not configured');
  }

  const response = await fetch(`${baseUrl}/pages/api_stripe_fulfill.php`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Internal-Push-Key': internalKey,
    },
    body: JSON.stringify({ session_id: sessionId }),
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.ok) {
    throw new Error(data.error || `Fulfillment failed with status ${response.status}`);
  }
  return data;
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    return json(res, 405, { ok: false, error: 'Method not allowed' });
  }

  const webhookSecret = (process.env.STRIPE_WEBHOOK_SECRET || '').trim();
  if (!webhookSecret) {
    return json(res, 500, { ok: false, error: 'STRIPE_WEBHOOK_SECRET not configured' });
  }

  const rawBody = await readRawBody(req);
  const signature = (req.headers['stripe-signature'] || '').toString();
  if (!verifyStripeSignature(rawBody, signature, webhookSecret)) {
    return json(res, 400, { ok: false, error: 'Invalid Stripe signature' });
  }

  let event;
  try {
    event = JSON.parse(rawBody);
  } catch (_) {
    return json(res, 400, { ok: false, error: 'Invalid JSON payload' });
  }

  if (event.type === 'checkout.session.completed') {
    const sessionId = event.data && event.data.object && event.data.object.id;
    if (!sessionId) {
      return json(res, 422, { ok: false, error: 'Missing session id' });
    }

    try {
      const result = await fulfillSession(sessionId);
      return json(res, 200, { ok: true, fulfilled: true, result });
    } catch (error) {
      return json(res, 500, { ok: false, error: error.message || 'Fulfillment failed' });
    }
  }

  return json(res, 200, { ok: true, ignored: true, type: event.type || 'unknown' });
};
