-- Supabase Database Linter Fixes
-- Resolves rls_disabled_in_public and rls_enabled_no_policy for:
-- 1. service_templates
-- 2. email_campaigns
-- 3. email_unsubscribes
-- 4. managed_listings

-- 1. Drop unused legacy service_templates table if it exists on the instance
DO $$ 
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'service_templates'
    ) THEN
        ALTER TABLE public.service_templates ENABLE ROW LEVEL SECURITY;
        DROP TABLE public.service_templates CASCADE;
    END IF;
END $$;

-- 2. Enable Row Level Security & explicit policy on email_campaigns
DO $$ 
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'email_campaigns'
    ) THEN
        ALTER TABLE public.email_campaigns ENABLE ROW LEVEL SECURITY;
        REVOKE ALL ON TABLE public.email_campaigns FROM anon, authenticated;
        
        DROP POLICY IF EXISTS email_campaigns_server_only ON public.email_campaigns;
        CREATE POLICY email_campaigns_server_only
            ON public.email_campaigns
            FOR ALL
            TO authenticated, anon
            USING (false)
            WITH CHECK (false);
    END IF;
END $$;

-- 3. Enable Row Level Security & explicit policy on email_unsubscribes
DO $$ 
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'email_unsubscribes'
    ) THEN
        ALTER TABLE public.email_unsubscribes ENABLE ROW LEVEL SECURITY;
        REVOKE ALL ON TABLE public.email_unsubscribes FROM anon, authenticated;

        DROP POLICY IF EXISTS email_unsubscribes_server_only ON public.email_unsubscribes;
        CREATE POLICY email_unsubscribes_server_only
            ON public.email_unsubscribes
            FOR ALL
            TO authenticated, anon
            USING (false)
            WITH CHECK (false);
    END IF;
END $$;

-- 4. Add explicit server-only policy on managed_listings to resolve rls_enabled_no_policy
DO $$ 
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'managed_listings'
    ) THEN
        ALTER TABLE public.managed_listings ENABLE ROW LEVEL SECURITY;
        REVOKE ALL ON TABLE public.managed_listings FROM anon, authenticated;

        DROP POLICY IF EXISTS managed_listings_server_only ON public.managed_listings;
        CREATE POLICY managed_listings_server_only
            ON public.managed_listings
            FOR ALL
            TO authenticated, anon
            USING (false)
            WITH CHECK (false);
    END IF;
END $$;
