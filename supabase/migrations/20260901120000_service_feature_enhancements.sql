-- ================================================================
-- Service Feature Enhancements
-- Adds: delivery_days, revision_count, availability_status,
--       availability_reset_at, portfolio_link to products table
-- ================================================================

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS delivery_days TINYINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Estimated delivery in days (services only)',
  ADD COLUMN IF NOT EXISTS revision_count TINYINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Number of revisions included (services only, NULL = not set, 99 = unlimited)',
  ADD COLUMN IF NOT EXISTS availability_status VARCHAR(20) NOT NULL DEFAULT 'available'
    COMMENT 'Seller availability: available | busy | unavailable',
  ADD COLUMN IF NOT EXISTS availability_reset_at TIMESTAMPTZ NULL DEFAULT NULL
    COMMENT 'When to auto-reset availability_status back to available (NULL = manual only)',
  ADD COLUMN IF NOT EXISTS portfolio_link VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'External portfolio/social link shown on service listing';

-- Index for filtering by availability on the services browse page
CREATE INDEX IF NOT EXISTS idx_products_availability
  ON products(availability_status)
  WHERE listing_type = 'service';
