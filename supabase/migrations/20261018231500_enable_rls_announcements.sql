-- Migration: Enable Row Level Security (RLS) on public.announcements to satisfy Supabase security linter
ALTER TABLE IF EXISTS public.announcements ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS announcements_read_active ON public.announcements;
CREATE POLICY announcements_read_active
    ON public.announcements
    FOR SELECT
    TO anon, authenticated
    USING (
        is_active = TRUE
        AND (starts_at IS NULL OR starts_at <= NOW())
        AND (ends_at IS NULL OR ends_at >= NOW())
    );
