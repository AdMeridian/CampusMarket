-- Currency rates cache table for exchange rate conversion (open.er-api.com)

CREATE TABLE IF NOT EXISTS public.currency_rates (
    code             VARCHAR(3)    PRIMARY KEY,
    rate_to_try      NUMERIC(12,4) NOT NULL,
    next_update_unix BIGINT        NOT NULL DEFAULT 0,
    updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

-- Enable RLS and allow read access
ALTER TABLE public.currency_rates ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS currency_rates_read_all ON public.currency_rates;
CREATE POLICY currency_rates_read_all
ON public.currency_rates
FOR SELECT
TO anon, authenticated
USING (true);

-- Seed with baseline rates so platform works immediately
INSERT INTO public.currency_rates (code, rate_to_try, next_update_unix) VALUES
    ('TRY', 1.0,  0),
    ('USD', 34.0, 0),
    ('EUR', 37.0, 0),
    ('GBP', 44.0, 0)
ON CONFLICT (code) DO NOTHING;
