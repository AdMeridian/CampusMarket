CREATE TABLE IF NOT EXISTS public.service_job_posts (
    id BIGSERIAL PRIMARY KEY,
    poster_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES public.categories(id) ON DELETE RESTRICT,
    organization_name VARCHAR(120) NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    location_town VARCHAR(32) NOT NULL,
    budget_max NUMERIC(10, 2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open'
        CHECK (status IN ('open', 'closed')),
    expires_at TIMESTAMPTZ NOT NULL DEFAULT (NOW() + INTERVAL '30 days'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (char_length(title) BETWEEN 8 AND 120),
    CHECK (char_length(description) BETWEEN 20 AND 5000),
    CHECK (budget_max IS NULL OR budget_max > 0)
);

CREATE INDEX IF NOT EXISTS idx_service_job_posts_open_created
    ON public.service_job_posts(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_service_job_posts_category
    ON public.service_job_posts(category_id, status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_service_job_posts_poster_created
    ON public.service_job_posts(poster_id, created_at DESC);

ALTER TABLE public.service_job_posts ENABLE ROW LEVEL SECURITY;

REVOKE ALL PRIVILEGES ON TABLE public.service_job_posts FROM PUBLIC, anon, authenticated;
REVOKE ALL PRIVILEGES ON SEQUENCE public.service_job_posts_id_seq FROM PUBLIC, anon, authenticated;

GRANT SELECT (
    id,
    poster_id,
    category_id,
    organization_name,
    title,
    description,
    location_town,
    budget_max,
    status,
    expires_at,
    created_at
) ON TABLE public.service_job_posts TO anon, authenticated;
GRANT INSERT (
    poster_id,
    category_id,
    title,
    description,
    location_town,
    budget_max,
    expires_at
) ON TABLE public.service_job_posts TO authenticated;
GRANT UPDATE (status) ON TABLE public.service_job_posts TO authenticated;
GRANT USAGE ON SEQUENCE public.service_job_posts_id_seq TO authenticated;

CREATE POLICY service_job_posts_read_open
    ON public.service_job_posts FOR SELECT
    TO anon, authenticated
    USING (status = 'open' AND expires_at > NOW());

CREATE POLICY service_job_posts_read_own
    ON public.service_job_posts FOR SELECT
    TO authenticated
    USING (poster_id = (SELECT public.current_app_user_id()));

CREATE POLICY service_job_posts_insert_own
    ON public.service_job_posts FOR INSERT
    TO authenticated
    WITH CHECK (
        poster_id = (SELECT public.current_app_user_id())
        AND status = 'open'
        AND expires_at > NOW()
        AND expires_at <= NOW() + INTERVAL '30 days'
    );

CREATE POLICY service_job_posts_close_own
    ON public.service_job_posts FOR UPDATE
    TO authenticated
    USING (poster_id = (SELECT public.current_app_user_id()))
    WITH CHECK (poster_id = (SELECT public.current_app_user_id()) AND status = 'closed');