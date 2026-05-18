-- ============================================================
-- Fix linter warning: function_search_path_mutable
-- ============================================================

ALTER FUNCTION public.set_updated_at()
SET search_path = public, pg_temp;

ALTER FUNCTION public.set_deal_confirmations_updated_at()
SET search_path = public, pg_temp;
