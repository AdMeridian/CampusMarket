-- Per-listing currency (TRY default, USD optional).

ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS price_currency varchar(3) NOT NULL DEFAULT 'TRY';

ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_price_currency_check;

ALTER TABLE public.products
    ADD CONSTRAINT products_price_currency_check
    CHECK (price_currency IN ('TRY', 'USD'));
