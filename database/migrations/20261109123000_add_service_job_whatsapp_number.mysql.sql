-- Migration: Add optional whatsapp_number column to service_job_posts (MySQL)
-- Allows posters to opt in to direct WhatsApp contact for urgent requests.
ALTER TABLE service_job_posts
ADD COLUMN whatsapp_number VARCHAR(30) NULL AFTER budget_max;

