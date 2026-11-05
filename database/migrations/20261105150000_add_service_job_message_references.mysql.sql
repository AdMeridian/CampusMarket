ALTER TABLE messages
    ADD COLUMN service_job_post_id INT NULL AFTER product_id,
    ADD CONSTRAINT fk_messages_service_job_post
        FOREIGN KEY (service_job_post_id) REFERENCES service_job_posts(id) ON DELETE SET NULL,
    ADD INDEX idx_messages_service_job_post_id (service_job_post_id);
