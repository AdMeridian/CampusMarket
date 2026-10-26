-- Restrict provider profile access to public, published service providers.
ALTER TABLE public.provider_profiles ENABLE ROW LEVEL SECURITY;

REVOKE ALL PRIVILEGES ON TABLE public.provider_profiles FROM PUBLIC, anon, authenticated;
REVOKE ALL PRIVILEGES ON SEQUENCE public.provider_profiles_id_seq FROM PUBLIC, anon, authenticated;
GRANT USAGE ON SEQUENCE public.provider_profiles_id_seq TO authenticated;

GRANT SELECT (
    id,
    user_id,
    bio,
    parsed_skills,
    primary_category_id,
    portfolio_link,
    created_at,
    updated_at
) ON TABLE public.provider_profiles TO anon, authenticated;

GRANT INSERT (
    user_id,
    bio,
    raw_skills_input,
    parsed_skills,
    primary_category_id,
    portfolio_link
) ON TABLE public.provider_profiles TO authenticated;

GRANT UPDATE (
    bio,
    raw_skills_input,
    parsed_skills,
    primary_category_id,
    portfolio_link
) ON TABLE public.provider_profiles TO authenticated;

DROP POLICY IF EXISTS "Public can view active provider profiles" ON public.provider_profiles;
DROP POLICY IF EXISTS "Users can insert their own provider profile" ON public.provider_profiles;
DROP POLICY IF EXISTS "Users can update their own provider profile" ON public.provider_profiles;
DROP POLICY IF EXISTS provider_profiles_read_published ON public.provider_profiles;
DROP POLICY IF EXISTS provider_profiles_read_own ON public.provider_profiles;
DROP POLICY IF EXISTS provider_profiles_insert_own ON public.provider_profiles;
DROP POLICY IF EXISTS provider_profiles_update_own ON public.provider_profiles;

CREATE POLICY provider_profiles_read_published
ON public.provider_profiles
FOR SELECT
TO anon, authenticated
USING (
    enrollment_status = 'pilot'
    AND is_verified
    AND EXISTS (
        SELECT 1
        FROM public.products AS service_listing
        WHERE service_listing.user_id = provider_profiles.user_id
          AND service_listing.listing_type = 'service'
          AND service_listing.status = 'active'
          AND (
              service_listing.service_expires_at IS NULL
              OR service_listing.service_expires_at > NOW()
          )
    )
);

CREATE POLICY provider_profiles_read_own
ON public.provider_profiles
FOR SELECT
TO authenticated
USING (user_id = (SELECT public.current_app_user_id()));

CREATE POLICY provider_profiles_insert_own
ON public.provider_profiles
FOR INSERT
TO authenticated
WITH CHECK (user_id = (SELECT public.current_app_user_id()));


CREATE POLICY provider_profiles_update_own
ON public.provider_profiles
FOR UPDATE
TO authenticated
USING (user_id = (SELECT public.current_app_user_id()))
WITH CHECK (user_id = (SELECT public.current_app_user_id()));