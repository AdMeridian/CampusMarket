# Supabase Safe Mode

This project now supports an optional Supabase link without replacing the existing MySQL/PDO backend.

## What Safe Mode Means

- Primary app database remains MySQL (`config/db.php`).
- Existing login/session/messages flow remains unchanged.
- Supabase client is only initialized when keys are configured.

## Setup

1. Copy `.env.example` to `.env`.
2. Fill in:
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
3. Reload the app.

If keys are present, `window.CampusMarketSupabase` is available in browser pages for gradual features such as:

- Supabase Auth social login flows
- Realtime listeners
- Supabase Storage uploads

## Files Added/Changed

- `config/supabase.php` (helper functions)
- `includes/bootstrap.php` (loads .env + Supabase helper)
- `includes/header.php` (injects Supabase meta/script when configured)
- `public/js/supabase-client.js` (initializes Supabase client)
- `.env.example` (environment configuration template)
