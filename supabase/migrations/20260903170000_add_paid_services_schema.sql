-- ================================================================
-- Paid Service Listings Schema
-- Adds service expiration timestamp, pending_payment & expired statuses,
-- and updates promotion_payments constraints for service monetization.
-- ================================================================

-- 1. Add service_expires_at column to products table
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS service_expires_at TIMESTAMPTZ NULL DEFAULT NULL;

-- 2. Add 'pending_payment' and 'expired' to product_status enum if not already present
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_enum WHERE enumlabel = 'pending_payment' AND enumtypid = 'product_status'::regtype) THEN
        ALTER TYPE product_status ADD VALUE 'pending_payment';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_enum WHERE enumlabel = 'expired' AND enumtypid = 'product_status'::regtype) THEN
        ALTER TYPE product_status ADD VALUE 'expired';
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        NULL;
END $$;

-- 3. Create index for fast active & unexpired service queries
CREATE INDEX IF NOT EXISTS idx_products_service_expiry
  ON products(listing_type, status, service_expires_at)
  WHERE listing_type = 'service';

-- Stripe browser returns and webhook retries may fulfill the same session concurrently.
CREATE UNIQUE INDEX IF NOT EXISTS uq_promotion_payments_transaction_ref
    ON promotion_payments(transaction_ref)
    WHERE transaction_ref IS NOT NULL;

-- 4. Update promotion_payments check constraint on payment_type if present
DO $$
BEGIN
    ALTER TABLE promotion_payments DROP CONSTRAINT IF EXISTS promotion_payments_payment_type_check;
    ALTER TABLE promotion_payments ADD CONSTRAINT promotion_payments_payment_type_check 
        CHECK (payment_type IN ('promotion', 'donation', 'service_listing', 'service_boost'));
EXCEPTION
    WHEN OTHERS THEN
        NULL;
END $$;
