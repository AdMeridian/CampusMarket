-- Allow NULL price for student services where asking rate is negotiable or discussed directly
ALTER TABLE public.products ALTER COLUMN price DROP NOT NULL;
