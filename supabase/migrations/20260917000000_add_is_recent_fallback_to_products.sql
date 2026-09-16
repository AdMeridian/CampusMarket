-- Migration: Add is_recent_fallback column to products table for curated homepage recent listings
ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS is_recent_fallback BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_products_recent_fallback ON public.products(is_recent_fallback, status, created_at DESC);
