-- Cart-wide purchase and verified provider evidence. No payment provider is enabled by this migration.
CREATE TABLE IF NOT EXISTS commerce_shopping_purchases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 cart_id BIGINT UNSIGNED NOT NULL,
 owner_user_id BIGINT UNSIGNED NOT NULL,
 public_reference CHAR(32) NOT NULL,
 state ENUM('prepared','pending','succeeded','canceled','exception','refunded') NOT NULL DEFAULT 'prepared',
 customer_name VARCHAR(190) NOT NULL,
 customer_email VARCHAR(254) NOT NULL,
 customer_phone VARCHAR(40) NULL,
 currency CHAR(3) NOT NULL,
 total_minor BIGINT UNSIGNED NOT NULL,
 snapshot_json JSON NOT NULL,
 request_json JSON NOT NULL,
 remote_session_id VARCHAR(255) NULL,
 remote_payment_id VARCHAR(255) NULL,
 checkout_url TEXT NULL,
 captured_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 last_error VARCHAR(80) NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_shopping_purchase_cart(cart_id),
 UNIQUE KEY uq_shopping_purchase_ref(public_reference),
 UNIQUE KEY uq_shopping_purchase_session(remote_session_id),
 UNIQUE KEY uq_shopping_purchase_payment(remote_payment_id),
 UNIQUE KEY uq_shopping_purchase_org(id,organization_id),
 FOREIGN KEY(cart_id,organization_id) REFERENCES commerce_shopping_carts(id,organization_id),
 FOREIGN KEY(owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_shopping_purchase_orders (
 organization_id BIGINT UNSIGNED NOT NULL,
 purchase_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(purchase_id,order_id),
 UNIQUE KEY uq_shopping_purchase_order(organization_id,order_id),
 FOREIGN KEY(purchase_id,organization_id) REFERENCES commerce_shopping_purchases(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id)
) ENGINE=InnoDB;
