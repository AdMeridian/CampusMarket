-- Add default WhatsApp contact opt-in flag to users table
ALTER TABLE public.users 
    ADD COLUMN IF NOT EXISTS whatsapp_opt_in_default BOOLEAN NOT NULL DEFAULT FALSE;

