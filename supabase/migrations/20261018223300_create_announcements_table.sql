-- Migration: Create announcements table for platform-wide announcements
CREATE TABLE IF NOT EXISTS public.announcements (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'info',
    cta_text VARCHAR(60) NULL,
    cta_url VARCHAR(255) NULL,
    target_role VARCHAR(20) NOT NULL DEFAULT 'all',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    starts_at TIMESTAMPTZ NULL,
    ends_at TIMESTAMPTZ NULL,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_announcements_active ON public.announcements(is_active, target_role);
CREATE INDEX IF NOT EXISTS idx_announcements_dates ON public.announcements(starts_at, ends_at);

-- Enable Row Level Security (RLS)
ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;

-- Allow anon and authenticated roles to read active announcements
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
