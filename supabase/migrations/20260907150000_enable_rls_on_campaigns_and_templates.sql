-- Enable RLS on email_campaigns, email_unsubscribes, and service_templates to resolve Supabase linter error 0013_rls_disabled_in_public
ALTER TABLE IF EXISTS public.service_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS public.email_campaigns ENABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS public.email_unsubscribes ENABLE ROW LEVEL SECURITY;
