-- Fix: security_definer_view lint on public.v_completed_deals
-- The view was recreated in 20260709120000_manual_sale_completion.sql without
-- security_invoker = true, causing it to run as the view owner (SECURITY DEFINER
-- behaviour) instead of the querying user. This means RLS on the underlying
-- tables is evaluated as the owner, bypassing row-level security for callers.
-- Fix: drop and recreate with WITH (security_invoker = true) so RLS is
-- enforced using the permissions of whoever is querying the view.

DROP VIEW IF EXISTS public.v_completed_deals;

CREATE VIEW public.v_completed_deals
WITH (security_invoker = true)
AS
SELECT
    dc.id,
    dc.product_id,
    p.title  AS product_title,
    p.price  AS product_price,
    buyer.username  AS buyer_username,
    seller.username AS seller_username,
    dc.buyer_confirmed_at,
    dc.seller_confirmed_at,
    dc.sale_source,
    dc.created_at
FROM public.deal_confirmations dc
JOIN public.products   p      ON dc.product_id = p.id
JOIN public.users      seller ON dc.seller_id  = seller.id
LEFT JOIN public.users buyer  ON dc.buyer_id   = buyer.id
WHERE dc.status = 'completed'
ORDER BY dc.seller_confirmed_at DESC;

-- Grant the same access that the previous view had (admin-only via RLS on the
-- underlying tables; no direct anon/authenticated grants needed here).
REVOKE ALL ON public.v_completed_deals FROM anon, authenticated;
