-- Track anonymous PWA installations and recent standalone activity.
CREATE TABLE IF NOT EXISTS public.pwa_installations (
    installation_id TEXT PRIMARY KEY,
    user_id BIGINT NULL REFERENCES public.users(id) ON DELETE SET NULL,
    first_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    install_event_at TIMESTAMPTZ NULL,
    last_standalone_at TIMESTAMPTZ NULL,
    platform VARCHAR(40) NULL,
    display_mode VARCHAR(30) NULL,
    user_agent TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_pwa_installations_last_seen
    ON public.pwa_installations(last_seen_at DESC);
CREATE INDEX IF NOT EXISTS idx_pwa_installations_user_id
    ON public.pwa_installations(user_id);

ALTER TABLE public.pwa_installations ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.pwa_installations FROM anon, authenticated;

DROP POLICY IF EXISTS pwa_installations_no_client_access ON public.pwa_installations;
CREATE POLICY pwa_installations_no_client_access
ON public.pwa_installations
FOR ALL
TO authenticated, anon
USING (false)
WITH CHECK (false);

COMMENT ON TABLE public.pwa_installations IS 'Anonymous PWA installation and standalone activity telemetry; written by the server only.';
