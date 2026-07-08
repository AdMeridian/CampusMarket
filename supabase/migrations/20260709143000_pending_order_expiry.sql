-- Pending purchase requests expire after 7 days; sellers get a reminder 24h before.

ALTER TABLE public.orders
    ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS expiry_reminder_sent_at TIMESTAMPTZ;

UPDATE public.orders
SET expires_at = created_at + INTERVAL '7 days'
WHERE status = 'pending'
  AND expires_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_orders_pending_expires
    ON public.orders (expires_at)
    WHERE status = 'pending';
