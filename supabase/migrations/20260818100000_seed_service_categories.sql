-- Seed service-type categories for the Services Marketplace feature.
-- seed.sql already has these for local MySQL (IDs 11–14), but no migration
-- has ever pushed them to the live Supabase database.
-- Using ON CONFLICT (id) so this is idempotent and safe to re-run.

INSERT INTO public.categories (id, name, slug, type) VALUES
    (11, 'Tutoring & Academic Help', 'tutoring',    'service'),
    (12, 'Cleaning Services',        'cleaning',    'service'),
    (13, 'Moving & Packing',         'moving',      'service'),
    (14, 'Photography & Media',      'photography', 'service')
ON CONFLICT (id) DO UPDATE SET
    type = 'service',
    name = EXCLUDED.name,
    slug = EXCLUDED.slug;

-- Also ensure any categories that were silently treated as 'product' type
-- but have no products are not confused with service-type categories.
-- (Safe no-op if the type column was already correct.)
