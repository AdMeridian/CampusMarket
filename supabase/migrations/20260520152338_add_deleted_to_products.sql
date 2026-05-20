-- Migration: Add 'deleted' status to product_status enum and deleted_at column to products table

-- 1. Add 'deleted' value to the product_status enum type if it does not already exist
ALTER TYPE product_status ADD VALUE IF NOT EXISTS 'deleted';

-- 2. Add deleted_at column to products table if it doesn't exist
ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ DEFAULT NULL;
