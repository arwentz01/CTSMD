-- Test-mode provider only. Review SHOW CREATE TABLE on partially migrated hosts.
-- MariaDB uses DROP CONSTRAINT rather than MySQL's DROP CHECK.
-- Verify the actual CHECK name via information_schema.TABLE_CONSTRAINTS before executing:
-- SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
-- WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commerce_payment_attempts' AND CONSTRAINT_TYPE='CHECK';
-- If a host auto-generated a different CHECK name, replace this identifier accordingly.
ALTER TABLE commerce_payment_attempts DROP CONSTRAINT commerce_payment_attempts_chk_1;
ALTER TABLE commerce_payment_attempts
 ADD CONSTRAINT commerce_payment_provider_allowed CHECK(provider IN ('simulated','stripe_test')),
 MODIFY scenario ENUM('success','declined','canceled','delayed','timeout') NULL,
 ADD COLUMN expected_minor INT UNSIGNED NULL,
 ADD COLUMN currency CHAR(3) NULL,
 ADD COLUMN captured_minor INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN refunded_minor INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN remote_session_id VARCHAR(255) NULL,
 ADD COLUMN remote_payment_id VARCHAR(255) NULL,
 ADD COLUMN checkout_url TEXT NULL,
 ADD COLUMN request_json JSON NULL,
 ADD COLUMN provider_created_at_utc DATETIME NULL,
 ADD COLUMN last_checked_at_utc DATETIME NULL,
 ADD COLUMN last_error_code VARCHAR(80) NULL,
 ADD UNIQUE KEY commerce_remote_session(provider,remote_session_id),
 ADD UNIQUE KEY commerce_remote_payment(provider,remote_payment_id);
ALTER TABLE commerce_orders MODIFY state ENUM('draft','held','payment_pending','paid','failed','canceled','refunded','exception','refund_pending') NOT NULL DEFAULT 'draft';
ALTER TABLE commerce_organizations ADD COLUMN public_sales_enabled TINYINT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS commerce_provider_webhooks (
 organization_id BIGINT UNSIGNED NOT NULL,
 event_id VARCHAR(255) NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 object_id VARCHAR(255) NOT NULL,
 attempt_id BIGINT UNSIGNED NULL,
 payload_hash CHAR(64) NOT NULL,
 status ENUM('queued','processing','processed','failed','ignored') NOT NULL DEFAULT 'queued',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 available_at_utc DATETIME NOT NULL,
 last_error_code VARCHAR(80) NULL,
 received_at_utc DATETIME NOT NULL,
 PRIMARY KEY(organization_id,event_id),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(attempt_id,organization_id) REFERENCES commerce_payment_attempts(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_provider_refunds (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 attempt_id BIGINT UNSIGNED NOT NULL,
 remote_refund_id VARCHAR(255) NULL,
 request_key CHAR(64) NOT NULL,
 amount_minor INT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 status ENUM('pending','succeeded','failed','exception') NOT NULL DEFAULT 'pending',
 original_order_state VARCHAR(30) NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at_utc DATETIME NOT NULL,
 UNIQUE KEY(order_id), UNIQUE KEY(remote_refund_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 FOREIGN KEY(attempt_id,organization_id) REFERENCES commerce_payment_attempts(id,organization_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
