-- Store buyer demand captured from searches with no matching listings.

CREATE TABLE IF NOT EXISTS public.wanted_item_requests (
    id BIGSERIAL PRIMARY KEY,
    requester_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    search_term VARCHAR(200) NOT NULL,
    details TEXT NULL,
    category_id BIGINT NULL REFERENCES public.categories(id) ON DELETE SET NULL,
    location_town VARCHAR(32) NULL,
    budget_max NUMERIC(10, 2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_by_admin_id BIGINT NULL REFERENCES public.users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMPTZ NULL,
    expires_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wanted_item_requests_status ON public.wanted_item_requests(status);
CREATE INDEX IF NOT EXISTS idx_wanted_item_requests_category ON public.wanted_item_requests(category_id);
CREATE INDEX IF NOT EXISTS idx_wanted_item_requests_requester_created ON public.wanted_item_requests(requester_id, created_at DESC);

ALTER TABLE public.wanted_item_requests ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can create their own wanted requests"
    ON public.wanted_item_requests FOR INSERT TO authenticated
    WITH CHECK (requester_id = public.current_app_user_id());

CREATE POLICY "Users can view their own wanted requests"
    ON public.wanted_item_requests FOR SELECT TO authenticated
    USING (requester_id = public.current_app_user_id());