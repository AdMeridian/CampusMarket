-- Migration: Add reply_to_message_id to messages table for chat quoting & replies
ALTER TABLE public.messages
    ADD COLUMN IF NOT EXISTS reply_to_message_id BIGINT NULL REFERENCES public.messages(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_messages_reply_to_message_id ON public.messages(reply_to_message_id);
