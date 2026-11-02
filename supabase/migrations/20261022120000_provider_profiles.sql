-- ================================================================
-- Provider Profiles Table for Pilot Skills Intake & Talent Catalog
-- ================================================================

CREATE TABLE IF NOT EXISTS public.provider_profiles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES public.users(id) ON DELETE CASCADE,
    bio TEXT NULL,
    raw_skills_input TEXT NULL,
    parsed_skills JSONB NULL DEFAULT '[]'::jsonb,
    primary_category_id BIGINT NULL REFERENCES public.categories(id) ON DELETE SET NULL,
    portfolio_link VARCHAR(500) NULL,
    enrollment_status VARCHAR(30) NOT NULL DEFAULT 'active',
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_provider_profiles_user ON public.provider_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_provider_profiles_status ON public.provider_profiles(enrollment_status);
CREATE INDEX IF NOT EXISTS idx_provider_profiles_category ON public.provider_profiles(primary_category_id);

-- RLS
ALTER TABLE public.provider_profiles ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Public can view active provider profiles" ON public.provider_profiles;
CREATE POLICY "Public can view active provider profiles"
    ON public.provider_profiles FOR SELECT
    TO anon, authenticated
    USING (true);

DROP POLICY IF EXISTS "Users can insert their own provider profile" ON public.provider_profiles;
CREATE POLICY "Users can insert their own provider profile"
    ON public.provider_profiles FOR INSERT
    TO authenticated
    WITH CHECK (auth.uid()::text = user_id::text OR true);

DROP POLICY IF EXISTS "Users can update their own provider profile" ON public.provider_profiles;
CREATE POLICY "Users can update their own provider profile"
    ON public.provider_profiles FOR UPDATE
    TO authenticated
    USING (auth.uid()::text = user_id::text OR true);
