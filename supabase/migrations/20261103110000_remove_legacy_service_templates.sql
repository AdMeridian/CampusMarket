-- Lock down legacy service_templates table if it exists
ALTER TABLE IF EXISTS public.service_templates ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies 
        WHERE schemaname = 'public' 
          AND tablename = 'service_templates' 
          AND policyname = 'service_templates_deny_all'
    ) THEN
        CREATE POLICY "service_templates_deny_all" ON public.service_templates FOR ALL TO public USING (false);
    END IF;
END $$;
