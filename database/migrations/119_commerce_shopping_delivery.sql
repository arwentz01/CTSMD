-- One consolidated delivery per paid multi-performance purchase.
CREATE TABLE IF NOT EXISTS commerce_shopping_deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 purchase_id BIGINT UNSIGNED NOT NULL,
 email_queue_id BIGINT UNSIGNED NOT NULL,
 public_reference CHAR(32) NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 lease_token CHAR(64) NULL,
 lease_until_utc DATETIME NULL,
 available_at_utc DATETIME NOT NULL,
 last_error_code VARCHAR(80) NULL,
 captured_at_utc DATETIME NULL,
 created_at_utc DATETIME NOT NULL,
 UNIQUE KEY uq_shopping_delivery_purchase(organization_id,purchase_id),
 UNIQUE KEY uq_shopping_delivery_queue(email_queue_id),
 UNIQUE KEY uq_shopping_delivery_reference(public_reference),
 KEY idx_shopping_delivery_available(organization_id,available_at_utc),
 FOREIGN KEY(purchase_id,organization_id) REFERENCES commerce_shopping_purchases(id,organization_id),
 FOREIGN KEY(email_queue_id) REFERENCES email_queue(id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
