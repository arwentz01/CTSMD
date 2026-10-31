-- Separate publication approvals and expiring retrieval capabilities. Public sales stay disabled.
CREATE TABLE IF NOT EXISTS commerce_storefront_performances (
 organization_id BIGINT UNSIGNED NOT NULL,
 performance_id BIGINT UNSIGNED NOT NULL,
 preview_approved TINYINT(1) NOT NULL DEFAULT 0,
 approved_by_user_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(performance_id),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id),
 FOREIGN KEY(approved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_order_links (
 organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 revoked_at DATETIME NULL,
 PRIMARY KEY(token_hash),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 FOREIGN KEY(created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

ALTER TABLE commerce_grants MODIFY role_code ENUM('administrator','box_office','seller','door','reporter','tester','storefront_tester') NOT NULL;
