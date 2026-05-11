-- ============================================================
-- Deal Handshake — Transaction Confirmation System
-- ============================================================

CREATE TABLE IF NOT EXISTS deal_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_confirmed_at DATETIME NULL,
    seller_confirmed_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    dismissed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_deal (product_id, buyer_id, seller_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_deal_confirmations_product ON deal_confirmations(product_id);
CREATE INDEX idx_deal_confirmations_seller ON deal_confirmations(seller_id);
CREATE INDEX idx_deal_confirmations_buyer ON deal_confirmations(buyer_id);

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
