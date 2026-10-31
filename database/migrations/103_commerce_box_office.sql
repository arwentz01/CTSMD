-- Additive Phase 2 staff tender evidence. No provider credentials or public sales enabled.
CREATE TABLE IF NOT EXISTS commerce_box_office_sales (
 organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 cashier_user_id BIGINT UNSIGNED NOT NULL,
 method ENUM('cash','external_terminal') NULL,
 cash_received_minor INT UNSIGNED NULL,
 change_due_minor INT UNSIGNED NULL,
 terminal_provider VARCHAR(100) NULL,
 terminal_reference VARCHAR(190) NULL,
 evidence_hash CHAR(64) NULL,
 confirmed_at DATETIME NULL,
 PRIMARY KEY(order_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 FOREIGN KEY(cashier_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
