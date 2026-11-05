ALTER TABLE public.messages
    ADD COLUMN IF NOT EXISTS service_job_post_id BIGINT NULL
        REFERENCES public.service_job_posts(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_messages_service_job_post_id
    ON public.messages (service_job_post_id);