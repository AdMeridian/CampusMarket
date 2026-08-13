-- Security Hardening: Fix permissive RLS policy product_shares_insert_public on public.product_shares.
-- Ensure WITH CHECK validates that the target product_id exists in public.products.

ALTER TABLE public.product_shares ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS product_shares_insert_public ON public.product_shares;

CREATE POLICY product_shares_insert_public ON public.product_shares
    FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.products p WHERE p.id = product_shares.product_id
        )
    );
