-- Add custom location column for products and users
ALTER TABLE public.products ADD COLUMN IF NOT EXISTS custom_location VARCHAR(100) NULL;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS custom_home_town VARCHAR(100) NULL;
