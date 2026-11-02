-- Sandbox cart foundation. Does not enable public sales or change existing order/payment behavior.
CREATE TABLE IF NOT EXISTS commerce_carts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 tester_user_id BIGINT UNSIGNED NOT NULL,
 ownership_hash CHAR(64) NOT NULL,
 state ENUM('open','checkout_pending','completed','canceled') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY(id,organization_id),
 KEY(organization_id,tester_user_id,state),
 FOREIGN KEY(tester_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_cart_orders (
 organization_id BIGINT UNSIGNED NOT NULL,
 cart_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(cart_id,order_id),
 UNIQUE KEY(organization_id,order_id),
 FOREIGN KEY(cart_id,organization_id) REFERENCES commerce_carts(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id)
) ENGINE=InnoDB;
