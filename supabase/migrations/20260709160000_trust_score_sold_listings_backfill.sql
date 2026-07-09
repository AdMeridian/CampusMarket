-- Restore sold status for listings completed via mark-as-sold but left as deleted (legacy flow).

UPDATE public.products p
SET status = 'sold',
    deleted_at = NULL,
    updated_at = COALESCE(p.updated_at, NOW())
WHERE p.status = 'deleted'
  AND EXISTS (
      SELECT 1
      FROM public.deal_confirmations dc
      WHERE dc.product_id = p.id
        AND dc.seller_id = p.user_id
        AND dc.status = 'completed'
  );

-- Record manual deals for sold listings that never got a deal_confirmation row.
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
