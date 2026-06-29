-- Town-based geotags for listings (North Cyprus districts).

ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS location_town varchar(32) NULL;

UPDATE public.products
SET location_town = 'other'
WHERE location_town IS NULL;

CREATE INDEX IF NOT EXISTS idx_products_location_town ON public.products (location_town);

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS home_town varchar(32) NULL;
