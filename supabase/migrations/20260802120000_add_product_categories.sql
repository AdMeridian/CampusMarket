-- Add support for multiple categories per product while preserving the primary category field.
CREATE TABLE IF NOT EXISTS public.product_categories (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES public.categories(id) ON DELETE RESTRICT,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE (product_id, category_id)
);

CREATE INDEX IF NOT EXISTS idx_product_categories_category_id ON public.product_categories(category_id);
CREATE INDEX IF NOT EXISTS idx_product_categories_product_id ON public.product_categories(product_id);
