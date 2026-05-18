-- Add optional featured expiration window for promoted listings.
ALTER TABLE public.products
ADD COLUMN IF NOT EXISTS featured_until TIMESTAMPTZ NULL;
