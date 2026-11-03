-- 121: scoped ticket promotion codes. Disabled until Commerce promo services are deployed.
CREATE TABLE commerce_promo_codes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(48) NOT NULL,
 production_id BIGINT UNSIGNED NULL,
 discount_kind ENUM('amount','percent') NOT NULL,
 discount_value INT UNSIGNED NOT NULL,
 max_redemptions INT UNSIGNED NOT NULL DEFAULT 0,
 starts_at_utc DATETIME NULL,
 expires_at_utc DATETIME NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_commerce_promo_org_code (organization_id,code),
 UNIQUE KEY uq_commerce_promo_org_id (id,organization_id),
 INDEX idx_commerce_promo_expiry (organization_id,expires_at_utc),
 FOREIGN KEY (created_by_user_id) REFERENCES users(id),
 FOREIGN KEY (production_id) REFERENCES productions(id),
 CHECK(discount_value>0),
 CHECK(discount_kind='amount' OR discount_value<=10000),
 CHECK(max_redemptions<=1000000)
) ENGINE=InnoDB;
CREATE TABLE commerce_promo_cart_codes (
 cart_id BIGINT UNSIGNED NOT NULL,
 organization_id BIGINT UNSIGNED NOT NULL,
 promo_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(cart_id),
 FOREIGN KEY(cart_id,organization_id) REFERENCES commerce_shopping_carts(id,organization_id),
 FOREIGN KEY(promo_id,organization_id) REFERENCES commerce_promo_codes(id,organization_id)
) ENGINE=InnoDB;
-- One claimed promo per purchase; held claims are not silently reused while payment is unresolved.
CREATE TABLE commerce_promo_claims (
 purchase_id BIGINT UNSIGNED NOT NULL,
 organization_id BIGINT UNSIGNED NOT NULL,
 promo_id BIGINT UNSIGNED NOT NULL,
 state ENUM('reserved','redeemed','released') NOT NULL DEFAULT 'reserved',
 discount_minor INT UNSIGNED NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(purchase_id),
 INDEX idx_commerce_promo_capacity (promo_id,organization_id,state),
 FOREIGN KEY(purchase_id,organization_id) REFERENCES commerce_shopping_purchases(id,organization_id),
 FOREIGN KEY(promo_id,organization_id) REFERENCES commerce_promo_codes(id,organization_id),
 CHECK(discount_minor>0)
) ENGINE=InnoDB;
