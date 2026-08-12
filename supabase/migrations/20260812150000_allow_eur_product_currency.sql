-- Allow EUR alongside TRY, USD, and GBP for product listings.

ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_price_currency_check;

ALTER TABLE public.products
    ADD CONSTRAINT products_price_currency_check
    CHECK (price_currency IN ('TRY', 'USD', 'EUR', 'GBP'));
