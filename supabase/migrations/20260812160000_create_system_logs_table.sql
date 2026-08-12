-- Create system_logs table for platform-wide error logging and admin monitoring

CREATE TABLE IF NOT EXISTS public.system_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL REFERENCES public.users(id) ON DELETE SET NULL,
    user_email VARCHAR(255) NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'system',
    message TEXT NOT NULL,
    raw_trace TEXT NULL,
    url TEXT NULL,
    request_method VARCHAR(10) NULL DEFAULT 'GET',
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_system_logs_created_at ON public.system_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_logs_category ON public.system_logs(category);
CREATE INDEX IF NOT EXISTS idx_system_logs_user_id ON public.system_logs(user_id);
