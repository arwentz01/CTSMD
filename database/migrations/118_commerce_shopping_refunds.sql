-- One durable full-purchase refund intent for a single combined Stripe payment.
ALTER TABLE commerce_shopping_purchases
 MODIFY state ENUM('prepared','pending','succeeded','canceled','exception','refund_pending','refunded') NOT NULL DEFAULT 'prepared',
 ADD COLUMN refunded_minor BIGINT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS commerce_shopping_refunds (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 purchase_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 request_key CHAR(64) NOT NULL,
 remote_refund_id VARCHAR(255) NULL,
 state ENUM('pending','succeeded','failed','exception') NOT NULL DEFAULT 'pending',
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_shopping_refund_purchase(purchase_id),
 UNIQUE KEY uq_shopping_refund_remote(remote_refund_id),
 FOREIGN KEY(purchase_id,organization_id) REFERENCES commerce_shopping_purchases(id,organization_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
