-- Idempotency for cart additions, especially unassigned general-admission tickets.
CREATE TABLE IF NOT EXISTS commerce_shopping_requests (
 organization_id BIGINT UNSIGNED NOT NULL,
 cart_id BIGINT UNSIGNED NOT NULL,
 request_key CHAR(32) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(cart_id,request_key),
 FOREIGN KEY(cart_id,organization_id) REFERENCES commerce_shopping_carts(id,organization_id)
) ENGINE=InnoDB;
