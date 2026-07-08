-- Support off-platform "mark as sold" in deal tracking and admin reports.

ALTER TABLE public.deal_confirmations
    ADD COLUMN IF NOT EXISTS sale_source VARCHAR(20) NOT NULL DEFAULT 'chat';

ALTER TABLE public.deal_confirmations
    ALTER COLUMN buyer_id DROP NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_deal_manual_per_product
    ON public.deal_confirmations (product_id, seller_id)
    WHERE sale_source = 'manual' AND status = 'completed';

-- Backfill completed deals for products already marked sold.
INSERT INTO public.deal_confirmations (product_id, buyer_id, seller_id, seller_confirmed_at, status, sale_source)
SELECT
    p.id,
    NULL,
    p.user_id,
    COALESCE(p.updated_at, p.created_at, NOW()),
    'completed',
    'manual'
FROM public.products p
WHERE p.status = 'sold'
  AND NOT EXISTS (
      SELECT 1
      FROM public.deal_confirmations dc
      WHERE dc.product_id = p.id
        AND dc.status = 'completed'
  );

DROP VIEW IF EXISTS public.v_completed_deals;

CREATE VIEW public.v_completed_deals AS
SELECT
    dc.id,
    dc.product_id,
    p.title AS product_title,
    p.price AS product_price,
    buyer.username AS buyer_username,
    seller.username AS seller_username,
    dc.buyer_confirmed_at,
    dc.seller_confirmed_at,
    dc.sale_source,
    dc.created_at
FROM public.deal_confirmations dc
JOIN public.products p ON dc.product_id = p.id
JOIN public.users seller ON dc.seller_id = seller.id
LEFT JOIN public.users buyer ON dc.buyer_id = buyer.id
WHERE dc.status = 'completed'
ORDER BY dc.seller_confirmed_at DESC;
