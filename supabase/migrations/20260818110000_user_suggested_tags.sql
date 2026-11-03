-- Migration: 20260818110000_user_suggested_tags.sql
-- Description: Allow users to suggest/create tags during listing creation with metadata for admin auditing

ALTER TABLE public.tags
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS created_by BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_tags_status ON public.tags(status);
CREATE INDEX IF NOT EXISTS idx_tags_created_by ON public.tags(created_by);
