-- ============================================================
-- Deal Handshake - Transaction Confirmation System (PostgreSQL)
-- ============================================================

CREATE TABLE IF NOT EXISTS deal_confirmations (
    id BIGSERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_confirmed_at TIMESTAMPTZ NULL,
    seller_confirmed_at TIMESTAMPTZ NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    dismissed_by INT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_deal UNIQUE (product_id, buyer_id, seller_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_deal_confirmations_product ON deal_confirmations(product_id);
CREATE INDEX IF NOT EXISTS idx_deal_confirmations_seller ON deal_confirmations(seller_id);
CREATE INDEX IF NOT EXISTS idx_deal_confirmations_buyer ON deal_confirmations(buyer_id);

-- Keep updated_at in sync on row updates.
CREATE OR REPLACE FUNCTION set_deal_confirmations_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_set_deal_confirmations_updated_at ON deal_confirmations;
CREATE TRIGGER trg_set_deal_confirmations_updated_at
BEFORE UPDATE ON deal_confirmations
FOR EACH ROW
EXECUTE FUNCTION set_deal_confirmations_updated_at();

-- Completed deals admin view
CREATE OR REPLACE VIEW v_completed_deals AS
SELECT
    dc.id,
    dc.product_id,
    p.title AS product_title,
    p.price AS product_price,
    buyer.username AS buyer_username,
    seller.username AS seller_username,
    dc.buyer_confirmed_at,
    dc.seller_confirmed_at,
    dc.created_at
FROM deal_confirmations dc
JOIN products p ON dc.product_id = p.id
JOIN users buyer ON dc.buyer_id = buyer.id
JOIN users seller ON dc.seller_id = seller.id
WHERE dc.status = 'completed'
ORDER BY dc.seller_confirmed_at DESC;
