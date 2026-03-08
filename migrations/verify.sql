SHOW TABLES LIKE 'wp_khm_processed_webhook_events';
SHOW TABLES LIKE 'wp_khm_webhook_dead_letter';
SHOW TABLES LIKE 'wp_khm_email_queue';
SHOW INDEX FROM wp_khm_email_queue WHERE Key_name = 'uniq_email_idempotency';
SHOW COLUMNS FROM wp_khm_email_queue LIKE 'idempotency_key';
SHOW COLUMNS FROM wp_promotion_attribution LIKE 'consent';
SHOW COLUMNS FROM wp_promotion_attribution LIKE 'anonymized_at';
SELECT COUNT(*) AS processed_events_count FROM wp_khm_processed_webhook_events;
SELECT COUNT(*) AS dead_letter_open_count
FROM wp_khm_webhook_dead_letter
WHERE resolved_at IS NULL
   OR CAST(resolved_at AS CHAR(19)) = '0000-00-00 00:00:00';
SELECT COUNT(*) AS queued_email_count FROM wp_khm_email_queue;
