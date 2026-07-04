-- Seller-facing explanation when a listing is held for review or flagged by AI moderation.
ALTER TABLE products ADD COLUMN IF NOT EXISTS moderation_note TEXT NULL;
