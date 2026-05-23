-- Web push subscriptions (for background browser notifications)
CREATE TABLE IF NOT EXISTS public.web_push_subscriptions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL,
    auth TEXT NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, endpoint)
);

CREATE INDEX IF NOT EXISTS idx_web_push_subscriptions_user_id
    ON public.web_push_subscriptions(user_id);

-- Rate-limit log for off-site email alerts (prevents spam on rapid chats)
CREATE TABLE IF NOT EXISTS public.message_email_alerts (
    id BIGSERIAL PRIMARY KEY,
    receiver_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    product_id BIGINT NULL REFERENCES public.products(id) ON DELETE SET NULL,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_message_email_alerts_lookup
    ON public.message_email_alerts(receiver_id, sender_id, product_id, sent_at DESC);

-- Keep updated_at in sync for subscriptions
CREATE OR REPLACE FUNCTION public.set_web_push_subscriptions_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
SET search_path = public
AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_set_web_push_subscriptions_updated_at ON public.web_push_subscriptions;
CREATE TRIGGER trg_set_web_push_subscriptions_updated_at
BEFORE UPDATE ON public.web_push_subscriptions
FOR EACH ROW
EXECUTE FUNCTION public.set_web_push_subscriptions_updated_at();

ALTER TABLE public.web_push_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.message_email_alerts ENABLE ROW LEVEL SECURITY;

-- Server-side only tables (deny direct client access)
DROP POLICY IF EXISTS web_push_subscriptions_no_client_access ON public.web_push_subscriptions;
CREATE POLICY web_push_subscriptions_no_client_access
ON public.web_push_subscriptions
FOR ALL
TO authenticated
USING (false)
WITH CHECK (false);

DROP POLICY IF EXISTS message_email_alerts_no_client_access ON public.message_email_alerts;
CREATE POLICY message_email_alerts_no_client_access
ON public.message_email_alerts
FOR ALL
TO authenticated
USING (false)
WITH CHECK (false);
