ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(30) NULL;

