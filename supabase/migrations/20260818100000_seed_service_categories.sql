-- Seed generalized service-type categories for the Services Marketplace.
-- Covers 6 professional & freelance domains (Software, Design, Writing, Marketing, Tutoring, Local).

INSERT INTO public.categories (id, name, slug, type) VALUES
    (11, 'Tutoring & Education',           'tutoring-education', 'service'),
    (12, 'Cleaning & Domestic Services',   'cleaning-domestic',  'service'),
    (13, 'Moving & Handyman Services',     'moving-handyman',    'service'),
    (14, 'Design, Photography & Media',    'design-media',       'service'),
    (15, 'Web, Software & Tech Support',   'software-tech',      'service'),
    (16, 'Writing, Translation & Admin',   'writing-admin',      'service')
ON CONFLICT (id) DO UPDATE SET
    type = 'service',
    name = EXCLUDED.name,
    slug = EXCLUDED.slug;
