-- Migration: Create student_email_verifications table for OTP-based seller verification
CREATE TABLE IF NOT EXISTS public.student_email_verifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    student_email VARCHAR(120) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    verified_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_student_verif_user_email ON public.student_email_verifications(user_id, student_email);
CREATE INDEX IF NOT EXISTS idx_student_verif_expires ON public.student_email_verifications(expires_at);

ALTER TABLE public.student_email_verifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view their own student verifications"
    ON public.student_email_verifications FOR SELECT TO authenticated
    USING (user_id = public.current_app_user_id());
