-- ============================================================
-- Seed default tags for CampusMarket
-- These are the 12 core tags the AI moderator and sellers use.
-- ON CONFLICT DO NOTHING makes this migration idempotent.
-- ============================================================

INSERT INTO tags (name, slug) VALUES
  ('Electronics',    'electronics'),
  ('Books',          'books'),
  ('Study Guides',   'study-guides'),
  ('Dorm Decor',     'dorm-decor'),
  ('Furniture',      'furniture'),
  ('Kitchenwear',    'kitchenwear'),
  ('Bikes',          'bikes'),
  ('Scooters',       'scooters'),
  ('Clothing',       'clothing'),
  ('Stationery',     'stationery'),
  ('Snacks',         'snacks'),
  ('Sports & Fitness','sports-fitness')
ON CONFLICT (slug) DO NOTHING;
