-- Handmade / art listing support: condition values, category, and tags.

ALTER TYPE product_condition ADD VALUE IF NOT EXISTS 'handmade';
ALTER TYPE product_condition ADD VALUE IF NOT EXISTS 'made_to_order';
ALTER TYPE product_condition ADD VALUE IF NOT EXISTS 'one_of_a_kind';

INSERT INTO categories (name, slug)
SELECT 'Arts, Crafts & DIY', 'arts-crafts-diy'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'arts-crafts-diy');

INSERT INTO tags (name, slug)
SELECT v.name, v.slug
FROM (VALUES
    ('Handmade', 'handmade'),
    ('Art', 'art'),
    ('DIY', 'diy'),
    ('Custom', 'custom'),
    ('Digital Art', 'digital-art')
) AS v(name, slug)
WHERE NOT EXISTS (SELECT 1 FROM tags t WHERE t.slug = v.slug);
