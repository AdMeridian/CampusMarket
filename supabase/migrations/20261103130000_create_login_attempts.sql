-- Migration: 20261103130000_create_login_attempts.sql
-- Application-level account rate limiting & brute force protection

CREATE TABLE IF NOT EXISTS public.login_attempts (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_email_time ON public.login_attempts(email, attempted_at DESC);

-- Enable RLS so table is shielded from PostgREST/anon/authenticated client queries
ALTER TABLE public.login_attempts ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies 
        WHERE schemaname = 'public' 
          AND tablename = 'login_attempts' 
          AND policyname = 'login_attempts_server_only'
    ) THEN
        CREATE POLICY "login_attempts_server_only" ON public.login_attempts FOR ALL TO authenticated, anon USING (false) WITH CHECK (false);
    END IF;
END $$;
