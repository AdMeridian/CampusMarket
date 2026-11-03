-- Align an existing local MySQL database with the current marketplace pages.

CREATE TABLE IF NOT EXISTS countries (
    code CHAR(2) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    currency_symbol VARCHAR(5) NOT NULL DEFAULT '$',
    symbol_position VARCHAR(10) NOT NULL DEFAULT 'before',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO countries (code, name, default_currency, currency_symbol, symbol_position) VALUES
    ('TR', 'Turkey', 'TRY', '₺', 'after'),
    ('US', 'United States', 'USD', '$', 'before'),
    ('GB', 'United Kingdom', 'GBP', '£', 'before'),
    ('DE', 'Germany', 'EUR', '€', 'before'),
    ('CA', 'Canada', 'CAD', '$', 'before')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    default_currency = VALUES(default_currency),
    currency_symbol = VALUES(currency_symbol),
    symbol_position = VALUES(symbol_position);

CREATE TABLE IF NOT EXISTS universities (
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
    INDEX idx_universities_country (country_code),
    INDEX idx_universities_domain (domain_pattern),
    CONSTRAINT fk_universities_country
        FOREIGN KEY (country_code) REFERENCES countries(code) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO universities (country_code, name, slug, domain_pattern, city, is_northern_cyprus) VALUES
    ('TR', 'Middle East Technical University', 'metu', 'metu.edu.tr', 'Ankara', 0),
    ('TR', 'Bilkent University', 'bilkent', 'bilkent.edu.tr', 'Ankara', 0),
    ('GB', 'University of Oxford', 'oxford', 'ox.ac.uk', 'Oxford', 0),
    ('GB', 'University of Cambridge', 'cambridge', 'cam.ac.uk', 'Cambridge', 0),
    ('US', 'Harvard University', 'harvard', 'harvard.edu', 'Cambridge', 0),
    ('US', 'Stanford University', 'stanford', 'stanford.edu', 'Stanford', 0),
    ('DE', 'Technical University of Munich', 'tum', 'tum.de', 'Munich', 0),
    ('TR', 'Arkın University of Creative Arts and Design', 'arucad', 'arucad.edu.tr', 'Kyrenia', 1),
    ('TR', 'Bahçeşehir Cyprus University', 'bau-cyprus', 'baucyprus.edu.tr', 'Nicosia', 1),
    ('TR', 'Cyprus Health and Social Sciences University', 'kstu', 'kstu.edu.tr', 'Güzelyurt', 1),
    ('TR', 'Cyprus International University', 'ciu', 'ciu.edu.tr', 'Nicosia', 1),
    ('TR', 'Eastern Mediterranean University', 'emu', 'emu.edu.tr', 'Famagusta', 1),
    ('TR', 'European University of Lefke', 'eul', 'eul.edu.tr', 'Lefke', 1),
    ('TR', 'Final International University', 'final', 'final.edu.tr', 'Kyrenia', 1),
    ('TR', 'Girne American University', 'gau', 'gau.edu.tr', 'Kyrenia', 1),
    ('TR', 'Middle East Technical University Northern Cyprus Campus', 'metu-ncc', 'ncc.metu.edu.tr', 'Güzelyurt', 1),
    ('TR', 'Near East University', 'neu', 'neu.edu.tr', 'Nicosia', 1),
    ('TR', 'University of Kyrenia', 'kyrenia', 'kyrenia.edu.tr', 'Kyrenia', 1)
ON DUPLICATE KEY UPDATE
    country_code = VALUES(country_code),
    name = VALUES(name),
    domain_pattern = VALUES(domain_pattern),
    city = VALUES(city),
    is_active = 1,
    is_northern_cyprus = VALUES(is_northern_cyprus);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS preferred_language VARCHAR(5) NOT NULL DEFAULT 'en',
    ADD COLUMN IF NOT EXISTS university_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS other_university_name VARCHAR(200) NULL;

ALTER TABLE users
    ADD CONSTRAINT fk_users_university
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL;

ALTER TABLE products
    MODIFY COLUMN price DECIMAL(10, 2) NULL,
    MODIFY COLUMN status ENUM('active', 'sold', 'flagged', 'pending_approval', 'pending_payment', 'expired', 'deleted') NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS country_code CHAR(2) NULL,
    ADD COLUMN IF NOT EXISTS university_id BIGINT NULL,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

ALTER TABLE products
    ADD CONSTRAINT fk_products_country
        FOREIGN KEY (country_code) REFERENCES countries(code) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_products_university
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL;

ALTER TABLE tags
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by INT NULL;

ALTER TABLE tags
    ADD CONSTRAINT fk_tags_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE promotion_payments
    MODIFY COLUMN payment_type ENUM('promotion', 'donation', 'service_listing', 'service_boost') NOT NULL;

CREATE TABLE IF NOT EXISTS service_job_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poster_id INT NOT NULL,
    category_id INT NOT NULL,
    organization_name VARCHAR(120) NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    location_town VARCHAR(32) NOT NULL,
    budget_max DECIMAL(10, 2) NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_job_posts_poster
        FOREIGN KEY (poster_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_job_posts_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_service_job_posts_open_created (status, created_at),
    INDEX idx_service_job_posts_category (category_id, status, created_at),
    INDEX idx_service_job_posts_poster_created (poster_id, created_at)
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
    CONSTRAINT fk_system_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_system_logs_created_at (created_at),
    INDEX idx_system_logs_category (category),
    INDEX idx_system_logs_user_id (user_id)
) ENGINE=InnoDB;
