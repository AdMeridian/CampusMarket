-- Migration: Add optional whatsapp_number column to service_job_posts (PostgreSQL / Supabase)
-- Allows posters to opt in to direct WhatsApp contact for urgent requests.
ALTER TABLE public.service_job_posts
ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(30) NULL;

