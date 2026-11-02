-- ============================================================
-- CampusMarket — Database Reference Schema
-- 
-- NOTE: In production (Vercel + Supabase), PostgreSQL migrations
-- located in `supabase/migrations/` are the authoritative source of truth.
-- This file serves as a MySQL / local reference document.
-- ============================================================

CREATE DATABASE IF NOT EXISTS campusmarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campusmarket;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 1. users
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE countries (
    code CHAR(2) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    currency_symbol VARCHAR(5) NOT NULL DEFAULT '$',
    symbol_position VARCHAR(10) NOT NULL DEFAULT 'before',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE universities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    country_code CHAR(2) NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    domain_pattern VARCHAR(150) NOT NULL,
    city VARCHAR(100) NULL,
    logo_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_northern_cyprus TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_code) REFERENCES countries(code) ON DELETE RESTRICT,
    INDEX idx_universities_country (country_code),
    INDEX idx_universities_domain (domain_pattern)
) ENGINE=InnoDB;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    student_id    VARCHAR(20)  NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    phone         VARCHAR(20)  NULL,
    avatar        VARCHAR(255) NULL,
    account_status    ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    home_town         VARCHAR(32)  NULL,
    custom_home_town    VARCHAR(100) NULL,
    university_id     BIGINT NULL,
    other_university_name VARCHAR(200) NULL,
    preferred_language VARCHAR(5) NOT NULL DEFAULT 'en',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 2. categories
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE categories (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL,
    slug  VARCHAR(100) NOT NULL UNIQUE,
    `type`  ENUM('product', 'service') NOT NULL DEFAULT 'product'
) ENGINE=InnoDB;

-- 2b. services
CREATE TABLE services (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    icon       VARCHAR(64) NOT NULL DEFAULT 'service',
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Wanted item suggestions captured from zero-result searches
CREATE TABLE wanted_item_requests (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    requester_id         INT NOT NULL,
    search_term          VARCHAR(200) NOT NULL,
    details              TEXT NULL,
    category_id          INT NULL,
    location_town        VARCHAR(32) NULL,
    budget_max           DECIMAL(10,2) NULL,
    status               ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by_admin_id INT NULL,
    reviewed_at          DATETIME NULL,
    expires_at           DATETIME NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_wanted_requests_status (status),
    INDEX idx_wanted_requests_category (category_id),
    INDEX idx_wanted_requests_requester_created (requester_id, created_at)
) ENGINE=InnoDB;

-- Provider profiles used by the services pilot
CREATE TABLE provider_profiles (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL UNIQUE,
    bio                 TEXT NULL,
    raw_skills_input    TEXT NULL,
    parsed_skills       JSON NULL,
    primary_category_id INT NULL,
    portfolio_link      VARCHAR(500) NULL,
    enrollment_status   VARCHAR(30) NOT NULL DEFAULT 'active',
    is_verified         TINYINT(1) NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (primary_category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_provider_profiles_status (enrollment_status),
    INDEX idx_provider_profiles_category (primary_category_id)
) ENGINE=InnoDB;

-- One-off service jobs requested by individuals and businesses
CREATE TABLE service_job_posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    poster_id   INT NOT NULL,
    category_id INT NOT NULL,
    organization_name VARCHAR(120) NULL,
    title       VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    location_town VARCHAR(32) NOT NULL,
    budget_max  DECIMAL(10,2) NULL,
    status      ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poster_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_service_job_posts_open_created (status, created_at),
    INDEX idx_service_job_posts_category (category_id, status, created_at),
    INDEX idx_service_job_posts_poster_created (poster_id, created_at)
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 3. tags
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50)  NOT NULL UNIQUE,
    slug VARCHAR(50)  NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT            NOT NULL,
    category_id INT            NOT NULL,
    title       VARCHAR(200)   NOT NULL,
    description TEXT,
    price       DECIMAL(10,2)  NULL,
    price_currency VARCHAR(3)  NOT NULL DEFAULT 'TRY',
    discount_percent TINYINT   NOT NULL DEFAULT 0,
    discount_set_at DATETIME   NULL,
    `condition`   ENUM('new', 'like_new', 'used', 'poor') NULL DEFAULT 'used',
    status      ENUM('active', 'sold', 'flagged', 'pending_approval', 'pending_payment', 'expired', 'deleted') NOT NULL DEFAULT 'active',
    listing_type ENUM('product', 'service') NOT NULL DEFAULT 'product',
    pricing_model ENUM('flat', 'hourly') NOT NULL DEFAULT 'flat',
    moderation_note TEXT        NULL,
    location_town VARCHAR(32)  NULL,
    custom_location VARCHAR(100) NULL,
    country_code CHAR(2) NULL,
    university_id BIGINT NULL,
    deleted_at DATETIME NULL,
    delivery_days TINYINT UNSIGNED NULL DEFAULT NULL,
    revision_count TINYINT UNSIGNED NULL DEFAULT NULL,
    availability_status VARCHAR(20) NOT NULL DEFAULT 'available',
    availability_reset_at TIMESTAMP NULL DEFAULT NULL,
    portfolio_link VARCHAR(500) NULL DEFAULT NULL,
    service_expires_at DATETIME NULL DEFAULT NULL,
    is_featured TINYINT(1)     NOT NULL DEFAULT 0,
    featured_until DATETIME    NULL,
    is_recent_fallback TINYINT(1) NOT NULL DEFAULT 0,
    views       INT            NOT NULL DEFAULT 0,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    availability_confirmed_at DATETIME NULL,
    CONSTRAINT chk_products_price_required_for_non_service CHECK (listing_type = 'service' OR price IS NOT NULL),
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (country_code) REFERENCES countries(code) ON DELETE RESTRICT,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. product_images
-- ──────────────────────────────────────────
CREATE TABLE product_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT          NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN      NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────────
-- 6. product_tags (pivot)
-- ──────────────────────────────────────────
CREATE TABLE product_tags (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    tag_id     INT NOT NULL,
    UNIQUE KEY uq_product_tag (product_id, tag_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES tags(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 6b. product_categories (pivot)
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE product_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT 0,
    UNIQUE KEY uq_product_category (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 7. wishlists
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE wishlists (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 7b. product_views
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE product_views (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id    INT NOT NULL,
    viewed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7c. product_shares
CREATE TABLE product_shares (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id    INT NULL,
    channel    VARCHAR(32) NOT NULL DEFAULT 'other',
    shared_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 8. orders
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id      INT            NOT NULL,
    product_id    INT            NOT NULL,
    amount        DECIMAL(10,2)  NOT NULL,
    status        ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    meeting_point VARCHAR(255)   NULL,
    notes         TEXT           NULL,
    scheduled_start DATETIME     NULL,
    scheduled_end   DATETIME     NULL,
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 9. transactions
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT           NOT NULL UNIQUE,
    payment_method  ENUM('cash', 'venmo', 'zelle', 'other') NOT NULL DEFAULT 'cash',
    transaction_ref VARCHAR(255)  NULL,
    amount          DECIMAL(10,2) NOT NULL,
    status          ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 10. messages
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT     NOT NULL,
    receiver_id INT     NOT NULL,
    product_id  INT     NULL,
    body        TEXT    NOT NULL,
    is_read     BOOLEAN NOT NULL DEFAULT 0,
    deleted_by_sender TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
    reply_to_message_id INT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 11. ratings
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE ratings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT         NOT NULL,
    seller_id   INT         NOT NULL,
    product_id  INT         NOT NULL,
    rating      TINYINT     NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT        NULL,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rating (reviewer_id, product_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (seller_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 12. reports
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT  NOT NULL,
    product_id  INT  NULL,
    reported_user_id INT NULL,
    issue_type  VARCHAR(32) NULL,
    description TEXT NULL,
    reference_link TEXT NULL,
    reason      TEXT NOT NULL,
    status      ENUM('pending', 'reviewed', 'dismissed') NOT NULL DEFAULT 'pending',
    resolution  VARCHAR(32) NULL,
    resolved_at TIMESTAMP NULL,
    resolved_by_admin_id INT NULL,
    admin_notes TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 13. notifications
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    `type`         ENUM('message', 'order', 'wishlist', 'system') NOT NULL,
    title        VARCHAR(200) NOT NULL,
    body         TEXT         NOT NULL,
    is_read      BOOLEAN      NOT NULL DEFAULT 0,
    reference_id INT          NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- 14. email_verifications (Member 2)
-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ALTER TABLE users
    ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE email_verifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token       VARCHAR(128) NOT NULL UNIQUE,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────
-- 15. User Activity Tracking
-- ─────────────────────────────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL DEFAULT NULL;

-- PWA installation and standalone activity telemetry
CREATE TABLE IF NOT EXISTS pwa_installations (
    installation_id VARCHAR(36) PRIMARY KEY,
    user_id INT NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    install_event_at DATETIME NULL,
    last_standalone_at DATETIME NULL,
    platform VARCHAR(40) NULL,
    display_mode VARCHAR(30) NULL,
    user_agent TEXT NULL,
    INDEX idx_pwa_last_seen (last_seen_at),
    INDEX idx_pwa_user_id (user_id),
    CONSTRAINT fk_pwa_installations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Promotion payments (manual verification workflow)
CREATE TABLE IF NOT EXISTS promotion_payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    product_id      INT NULL,
    payment_type    ENUM('promotion', 'donation', 'service_listing', 'service_boost') NOT NULL,
    payment_method  ENUM('cash', 'venmo', 'zelle', 'other') NOT NULL DEFAULT 'other',
    amount          DECIMAL(10,2) NOT NULL,
    transaction_ref VARCHAR(255) NULL,
    notes           TEXT NULL,
    status          ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note      VARCHAR(255) NULL,
    approved_at     DATETIME NULL,
    approved_by     INT NULL,
    consumed_at     DATETIME NULL,
    consumed_for    VARCHAR(50) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_promo_user_status (user_id, status),
    INDEX idx_promo_product_status (product_id, status),
    UNIQUE KEY uq_promotion_transaction_ref (transaction_ref)
) ENGINE=InnoDB;
-- System Settings
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(255) NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'system',
    message TEXT NOT NULL,
    raw_trace LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    url TEXT NULL,
    request_method VARCHAR(10) NULL DEFAULT 'GET',
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_system_logs_created_at (created_at),
    INDEX idx_system_logs_category (category),
    INDEX idx_system_logs_user_id (user_id)
) ENGINE=InnoDB;
