-- Fix RLS for PHP server-side writes: current_app_user_id() now falls back to
-- the app.current_user_id session variable set by PHP via set_config().
-- This lets Supabase RLS work correctly for both:
--   1. Supabase JS client (JWT path via auth.jwt())
--   2. PHP PDO server-side writes (session variable path)

CREATE OR REPLACE FUNCTION public.current_app_user_id()
RETURNS bigint
LANGUAGE sql
STABLE
SET search_path = public
AS $$
  SELECT COALESCE(
    -- 1. Supabase JWT path (used by JS client)
    NULLIF(trim(both from coalesce(auth.jwt() -> 'app_metadata' ->> 'user_id', '')), '')::bigint,
    -- 2. PHP server-side path (set via: SELECT set_config('app.current_user_id', '<id>', false))
    NULLIF(trim(both from coalesce(current_setting('app.current_user_id', true), '')), '')::bigint
  );
$$;
