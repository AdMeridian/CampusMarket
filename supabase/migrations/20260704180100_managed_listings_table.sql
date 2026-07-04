-- Private owner contact for agent-managed products.

CREATE TABLE IF NOT EXISTS managed_listings (
    product_id BIGINT PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,
    owner_name VARCHAR(120) NOT NULL,
    owner_phone VARCHAR(20) NOT NULL,
    owner_email VARCHAR(100),
    owner_notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_managed_listings_owner_phone ON managed_listings(owner_phone);

ALTER TABLE managed_listings ENABLE ROW LEVEL SECURITY;

-- Server-side PHP only; no client-facing policies on owner contact data.
REVOKE ALL ON managed_listings FROM anon, authenticated;

UPDATE users
SET role = 'agent'
WHERE username = 'Campus_Market_Listings';
