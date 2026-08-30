-- Migration: Create email_campaigns and email_unsubscribes tables
-- Date: 2026-09-01

CREATE TABLE IF NOT EXISTS email_campaigns (
    id BIGSERIAL PRIMARY KEY,
    admin_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    subject VARCHAR(255) NOT NULL,
    preview_text VARCHAR(255) NULL,
    audience_type VARCHAR(50) NOT NULL DEFAULT 'all', -- 'all', 'sellers', 'buyers', 'inactive'
    template_preset VARCHAR(50) NOT NULL DEFAULT 'custom',
    body_html TEXT NOT NULL,
    total_recipients INT NOT NULL DEFAULT 0,
    successful_sends INT NOT NULL DEFAULT 0,
    failed_sends INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft', -- 'draft', 'sending', 'sent', 'failed'
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    sent_at TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS email_unsubscribes (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_unsubscribes_email ON email_unsubscribes(email);
CREATE INDEX IF NOT EXISTS idx_email_campaigns_created_at ON email_campaigns(created_at DESC);
