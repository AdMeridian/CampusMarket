-- Allow British Pounds (GBP) as an additional listing currency.
-- Existing constraint: products_price_currency_check (TRY, USD)

ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_price_currency_check;

ALTER TABLE public.products
    ADD CONSTRAINT products_price_currency_check
    CHECK (price_currency IN ('TRY', 'USD', 'GBP'));

