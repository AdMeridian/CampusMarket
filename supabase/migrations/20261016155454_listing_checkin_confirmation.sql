ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS availability_confirmed_at TIMESTAMPTZ;