-- Migration to add Services feature support
CREATE TYPE category_type AS ENUM ('product', 'service');
ALTER TABLE categories ADD COLUMN type category_type NOT NULL DEFAULT 'product';

CREATE TYPE listing_type AS ENUM ('product', 'service');
ALTER TABLE products ADD COLUMN listing_type listing_type NOT NULL DEFAULT 'product';

CREATE TYPE pricing_model AS ENUM ('flat', 'hourly');
ALTER TABLE products ADD COLUMN pricing_model pricing_model NOT NULL DEFAULT 'flat';

-- Services do not have a physical condition
ALTER TABLE products ALTER COLUMN condition DROP NOT NULL;

-- Booking support
ALTER TABLE orders ADD COLUMN scheduled_start timestamptz;
ALTER TABLE orders ADD COLUMN scheduled_end timestamptz;
