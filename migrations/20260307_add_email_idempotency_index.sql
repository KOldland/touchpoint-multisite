ALTER TABLE wp_khm_email_queue
ADD COLUMN idempotency_key VARCHAR(255) NULL;

CREATE UNIQUE INDEX uniq_email_idempotency
ON wp_khm_email_queue (idempotency_key(191));
