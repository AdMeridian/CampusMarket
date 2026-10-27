ALTER TABLE products
    MODIFY COLUMN price DECIMAL(10, 2) NULL,
    ADD CONSTRAINT chk_products_price_required_for_non_service
        CHECK (listing_type = 'service' OR price IS NOT NULL);
