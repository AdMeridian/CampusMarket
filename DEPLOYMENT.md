# CampusMarket Deployment Runbook

## Prerequisites

- Vercel project linked to this repository
- Supabase project with migrations applied
- Stripe account (test or live)
- Resend account for transactional email
- Supabase Auth custom SMTP for verification emails

## Environment variables (Vercel)

| Variable | Purpose |
|----------|---------|
| `DATABASE_URL` | Postgres connection string |
| `SUPABASE_URL` | Supabase project URL |
| `SUPABASE_ANON_KEY` | Public anon key |
| `SUPABASE_SERVICE_ROLE_KEY` | Server-side storage/admin (never expose to browser) |
| `BASE_URL` | Production URL, e.g. `https://campusmarketplace.site` |
| `INTERNAL_PUSH_KEY` | Web push sender auth + Stripe fulfill endpoint + session cookie signing fallback |
| `STRIPE_PUBLISHABLE_KEY` | Stripe client key |
| `STRIPE_SECRET_KEY` | Stripe server key |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret (`checkout.session.completed`) |
| `RESEND_API_KEY` | Outbound email (support, message alerts) |
| `WEB_PUSH_PUBLIC_KEY` / `WEB_PUSH_PRIVATE_KEY` | VAPID keys for push notifications |

Optional: `SESSION_STATELESS_SECRET` (if unset, `INTERNAL_PUSH_KEY` is used).

## Database migrations

From the project root with Supabase CLI linked:

```bash
npx supabase db push --linked --yes
```

Migrations live in `supabase/migrations/`.

## Stripe webhook

1. Stripe Dashboard → Developers → Webhooks → Add destination
2. URL: `https://your-domain/api/stripe/webhook`
3. Event: `checkout.session.completed`
4. Payload: **Snapshot** (not thin)
5. Copy signing secret → `STRIPE_WEBHOOK_SECRET` on Vercel

## Deploy

Push to `member-1` or `main`. Vercel auto-deploys.

## Post-deploy smoke test

1. Register with a valid campus email
2. Verify email and log in
3. Create a listing with image upload
4. Message seller → confirm deal handshake in chat
5. Check **My Orders** updates when seller confirms
6. Test promotion/donation payment (test mode) + webhook delivery **200**
7. Enable push notifications from Activity page

## CI

GitHub Actions runs Playwright on push/PR (`.github/workflows/ci.yml`).

```bash
npm run test:ci
```

## Admin audit log

Admin mutations are recorded in `admin_audit_log` when the migration is applied.

```sql
SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 50;
```
