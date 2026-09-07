-- Enable RLS and explicit deny policies on internal server-only tables to satisfy Supabase security linter
DROP TABLE IF EXISTS public.service_templates CASCADE;

ALTER TABLE IF EXISTS public.email_campaigns ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS email_campaigns_deny_public ON public.email_campaigns;
CREATE POLICY email_campaigns_deny_public
    ON public.email_campaigns
    FOR ALL
    TO anon, authenticated
    USING (false)
    WITH CHECK (false);

ALTER TABLE IF EXISTS public.email_unsubscribes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS email_unsubscribes_deny_public ON public.email_unsubscribes;
CREATE POLICY email_unsubscribes_deny_public
    ON public.email_unsubscribes
    FOR ALL
    TO anon, authenticated
    USING (false)
    WITH CHECK (false);

ALTER TABLE IF EXISTS public.managed_listings ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS managed_listings_deny_public ON public.managed_listings;
DROP POLICY IF EXISTS managed_listings_server_only ON public.managed_listings;
CREATE POLICY managed_listings_deny_public
    ON public.managed_listings
    FOR ALL
    TO anon, authenticated
    USING (false)
    WITH CHECK (false);
