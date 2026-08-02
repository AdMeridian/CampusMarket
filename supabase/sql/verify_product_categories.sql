-- Verify `product_categories` existence and sample rows
-- 1) Table exists
SELECT EXISTS (
  SELECT 1 FROM information_schema.tables
  WHERE table_schema = 'public' AND table_name = 'product_categories'
) AS table_exists;

-- 2) Row count
SELECT COUNT(*) AS row_count FROM public.product_categories;

-- 3) Recent rows
SELECT id, product_id, category_id, is_primary
FROM public.product_categories
ORDER BY id DESC
LIMIT 50;

-- 4) Sample join: product with categories (show product title if available)
SELECT p.id AS product_id, p.title AS product_title, pc.category_id, c.name AS category_name, pc.is_primary
FROM public.product_categories pc
LEFT JOIN public.products p ON p.id = pc.product_id
LEFT JOIN public.categories c ON c.id = pc.category_id
ORDER BY p.id DESC
LIMIT 50;
