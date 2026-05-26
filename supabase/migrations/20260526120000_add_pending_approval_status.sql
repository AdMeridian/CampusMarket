BEGIN;
-- Add new value to existing enum type product_status
ALTER TYPE product_status ADD VALUE IF NOT EXISTS 'pending_approval';
COMMIT;
