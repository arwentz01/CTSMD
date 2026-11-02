-- Cart checkout transactions: schema-only foundation, no payment provider is enabled.
-- Run after 112_commerce_carts.sql in an explicitly approved environment.
CREATE TABLE IF NOT EXISTS commerce_cart_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 cart_id BIGINT UNSIGNED NOT NULL,
 reference CHAR(32) NOT NULL,
 state ENUM('prepared','pending','succeeded','failed','exception','refunded') NOT NULL DEFAULT 'prepared',
 currency CHAR(3) NOT NULL,
 expected_minor BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(40) NULL,
 provider_reference VARCHAR(190) NULL,
 provider_payment_id VARCHAR(190) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cart_payment_reference (reference),
 UNIQUE KEY uq_cart_payment (cart_id),
 UNIQUE KEY uq_cart_payment_provider (provider,provider_reference),
 KEY idx_cart_payment_org_state (organization_id,state),
 FOREIGN KEY (cart_id,organization_id) REFERENCES commerce_carts(id,organization_id),
 CHECK (expected_minor > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS commerce_cart_payment_orders (
 organization_id BIGINT UNSIGNED NOT NULL,
 payment_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 expected_minor INT NOT NULL,
 PRIMARY KEY(payment_id,order_id),
 UNIQUE KEY uq_cart_payment_order (organization_id,order_id),
 FOREIGN KEY (payment_id) REFERENCES commerce_cart_payments(id),
 FOREIGN KEY (order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 CHECK (expected_minor >= 0)
) ENGINE=InnoDB;
