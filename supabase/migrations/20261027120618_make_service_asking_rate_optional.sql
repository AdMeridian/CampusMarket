ALTER TABLE public.products
    ALTER COLUMN price DROP NOT NULL;

ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_price_required_for_non_service;

ALTER TABLE public.products
    ADD CONSTRAINT products_price_required_for_non_service
    CHECK (listing_type = 'service' OR price IS NOT NULL);