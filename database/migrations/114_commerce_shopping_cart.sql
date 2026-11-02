-- Cart-first reservations for the restricted storefront. Does not enable sales or payments.
CREATE TABLE IF NOT EXISTS commerce_shopping_carts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 owner_user_id BIGINT UNSIGNED NOT NULL,
 public_reference CHAR(32) NOT NULL,
 state ENUM('open','payment_pending','completed','expired','canceled','exception') NOT NULL DEFAULT 'open',
 expires_at_utc DATETIME NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_shopping_cart_reference (public_reference),
 UNIQUE KEY uq_shopping_cart_org (id,organization_id),
 KEY idx_shopping_cart_owner (organization_id,owner_user_id,state),
 FOREIGN KEY(owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_shopping_cart_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 cart_id BIGINT UNSIGNED NOT NULL,
 performance_id BIGINT UNSIGNED NOT NULL,
 seat_key VARCHAR(80) NOT NULL,
 category VARCHAR(60) NOT NULL,
 unit_minor INT NOT NULL,
 currency CHAR(3) NOT NULL,
 seat_json JSON NOT NULL,
 state ENUM('held','expired','removed','purchased') NOT NULL DEFAULT 'held',
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_shopping_cart_seat (cart_id,performance_id,seat_key),
 KEY idx_shopping_seat (organization_id,performance_id,seat_key,state),
 FOREIGN KEY(cart_id,organization_id) REFERENCES commerce_shopping_carts(id,organization_id),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id),
 CHECK(unit_minor>=0)
) ENGINE=InnoDB;
