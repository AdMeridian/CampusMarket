-- Allow GBP as a per-listing currency for UK-priced listings.

ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_price_currency_check;

ALTER TABLE public.products
    ADD CONSTRAINT products_price_currency_check
    CHECK (price_currency IN ('TRY', 'USD', 'GBP'));
