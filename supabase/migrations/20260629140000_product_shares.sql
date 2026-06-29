-- Track when listings are shared outside the app (social, copy link, native share sheet).

CREATE TABLE IF NOT EXISTS public.product_shares (
    id bigserial PRIMARY KEY,
    product_id bigint NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
    user_id bigint NULL REFERENCES public.users(id) ON DELETE SET NULL,
    channel varchar(32) NOT NULL DEFAULT 'other',
    shared_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_product_shares_product_id ON public.product_shares (product_id);
CREATE INDEX IF NOT EXISTS idx_product_shares_shared_at ON public.product_shares (shared_at);

ALTER TABLE public.product_shares ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS product_shares_insert_public ON public.product_shares;
CREATE POLICY product_shares_insert_public ON public.product_shares
    FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS product_shares_select_public ON public.product_shares;
CREATE POLICY product_shares_select_public ON public.product_shares
    FOR SELECT
    USING (true);
