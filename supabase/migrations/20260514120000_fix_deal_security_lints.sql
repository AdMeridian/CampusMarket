-- ============================================================
-- Fix Supabase security lints for deal handshake objects
-- - security_definer_view on public.v_completed_deals
-- - rls_disabled_in_public on public.deal_confirmations
-- ============================================================

-- 1) Ensure the completed deals view runs with caller permissions.
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_views
    WHERE schemaname = 'public'
      AND viewname = 'v_completed_deals'
  ) THEN
    EXECUTE 'ALTER VIEW public.v_completed_deals SET (security_invoker = true)';
  END IF;
END
$$;

-- 2) Enable RLS on deal_confirmations.
ALTER TABLE public.deal_confirmations ENABLE ROW LEVEL SECURITY;

-- 3) Replace policies idempotently.
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'deal_confirmations'
      AND policyname = 'deal_confirmations_select_participants'
  ) THEN
    EXECUTE 'DROP POLICY "deal_confirmations_select_participants" ON public.deal_confirmations';
  END IF;

  IF EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'deal_confirmations'
      AND policyname = 'deal_confirmations_insert_participants'
  ) THEN
    EXECUTE 'DROP POLICY "deal_confirmations_insert_participants" ON public.deal_confirmations';
  END IF;

  IF EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'deal_confirmations'
      AND policyname = 'deal_confirmations_update_participants'
  ) THEN
    EXECUTE 'DROP POLICY "deal_confirmations_update_participants" ON public.deal_confirmations';
  END IF;
END
$$;

CREATE POLICY "deal_confirmations_select_participants"
ON public.deal_confirmations
FOR SELECT
TO authenticated
USING (
  buyer_id::text = auth.uid()::text
  OR seller_id::text = auth.uid()::text
);

CREATE POLICY "deal_confirmations_insert_participants"
ON public.deal_confirmations
FOR INSERT
TO authenticated
WITH CHECK (
  (
    buyer_id::text = auth.uid()::text
    OR seller_id::text = auth.uid()::text
  )
  AND EXISTS (
    SELECT 1
    FROM public.products p
    WHERE p.id = deal_confirmations.product_id
      AND p.user_id = deal_confirmations.seller_id
  )
);

CREATE POLICY "deal_confirmations_update_participants"
ON public.deal_confirmations
FOR UPDATE
TO authenticated
USING (
  buyer_id::text = auth.uid()::text
  OR seller_id::text = auth.uid()::text
)
WITH CHECK (
  (
    buyer_id::text = auth.uid()::text
    OR seller_id::text = auth.uid()::text
  )
  AND EXISTS (
    SELECT 1
    FROM public.products p
    WHERE p.id = deal_confirmations.product_id
      AND p.user_id = deal_confirmations.seller_id
  )
);
