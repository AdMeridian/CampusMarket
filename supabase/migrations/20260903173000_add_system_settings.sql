-- ================================================================
-- System Settings Table for Configurable Platform Pricing
-- ================================================================

CREATE TABLE IF NOT EXISTS public.system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Enable RLS
ALTER TABLE public.system_settings ENABLE ROW LEVEL SECURITY;

-- Allow authenticated users to read settings
DO $$
BEGIN
    DROP POLICY IF EXISTS system_settings_read ON public.system_settings;
    CREATE POLICY system_settings_read ON public.system_settings
        FOR SELECT USING (true);
EXCEPTION
    WHEN OTHERS THEN
        NULL;
END $$;

-- Seed default service pricing and duration settings
INSERT INTO public.system_settings (setting_key, setting_value)
VALUES
    ('service_listing_fee', '30.00'),
    ('service_boost_fee', '30.00'),
    ('service_listing_days', '30'),
    ('service_boost_days', '7'),
    ('service_free_trial_enabled', '1')
ON CONFLICT (setting_key) DO NOTHING;
