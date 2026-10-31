-- Optional Connect Commerce. Additive only; no activation, real provider or worker.
CREATE TABLE IF NOT EXISTS commerce_organizations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_key VARCHAR(80) NOT NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 ticketing_enabled TINYINT NOT NULL DEFAULT 0,
 merchandise_enabled TINYINT NOT NULL DEFAULT 0,
 education_enabled TINYINT NOT NULL DEFAULT 0,
 hold_seconds INT NOT NULL DEFAULT 600,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 provider VARCHAR(30) NOT NULL DEFAULT 'simulated',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK (hold_seconds BETWEEN 60 AND 1800), CHECK (provider='simulated')
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_grants (
 organization_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 role_code ENUM('administrator','box_office','seller','door','reporter','tester') NOT NULL,
 PRIMARY KEY(organization_id,user_id,role_code),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_venues (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(190) NOT NULL, location VARCHAR(500) NOT NULL DEFAULT '',
 UNIQUE KEY(id,organization_id), FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_spaces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 venue_id BIGINT UNSIGNED NOT NULL, name VARCHAR(190) NOT NULL,
 UNIQUE KEY(id,organization_id), FOREIGN KEY(venue_id,organization_id) REFERENCES commerce_venues(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_layouts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 space_id BIGINT UNSIGNED NOT NULL, version_number INT NOT NULL,
 status ENUM('draft','published') NOT NULL DEFAULT 'draft', document_json JSON NOT NULL,
 published_at DATETIME NULL, UNIQUE KEY(id,organization_id), UNIQUE KEY(space_id,version_number),
 FOREIGN KEY(space_id,organization_id) REFERENCES commerce_spaces(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_performances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 production_id BIGINT UNSIGNED NOT NULL, layout_id BIGINT UNSIGNED NOT NULL,
 starts_at DATETIME NOT NULL, sales_status ENUM('closed','open') NOT NULL DEFAULT 'closed',
 checkin_enabled TINYINT NOT NULL DEFAULT 0, prices_json JSON NOT NULL,
 fee_minor INT NOT NULL DEFAULT 0, tax_basis_points INT NOT NULL DEFAULT 0,
 discount_minor INT NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL,
 UNIQUE KEY(id,organization_id), FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(layout_id,organization_id) REFERENCES commerce_layouts(id,organization_id),
 FOREIGN KEY(production_id) REFERENCES productions(id),
 CHECK(fee_minor>=0 AND tax_basis_points BETWEEN 0 AND 10000 AND discount_minor>=0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 performance_id BIGINT UNSIGNED NOT NULL, tester_user_id BIGINT UNSIGNED NOT NULL,
 public_reference CHAR(32) NOT NULL UNIQUE, ownership_hash CHAR(64) NOT NULL,
 idempotency_key VARCHAR(100) NOT NULL, request_hash CHAR(64) NOT NULL,
 state ENUM('draft','held','payment_pending','paid','failed','canceled','refunded','exception') NOT NULL DEFAULT 'draft',
 expires_at DATETIME NOT NULL, customer_name VARCHAR(190) NOT NULL, customer_email VARCHAR(254) NOT NULL,
 customer_phone VARCHAR(40) NULL, currency CHAR(3) NOT NULL,
 subtotal_minor INT NOT NULL, discount_minor INT NOT NULL, tax_minor INT NOT NULL, fee_minor INT NOT NULL, total_minor INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY(id,organization_id), UNIQUE KEY(organization_id,tester_user_id,idempotency_key),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id),
 FOREIGN KEY(tester_user_id) REFERENCES users(id), CHECK(total_minor>=0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_inventory (
 organization_id BIGINT UNSIGNED NOT NULL, performance_id BIGINT UNSIGNED NOT NULL,
 seat_key VARCHAR(80) NOT NULL, seat_json JSON NOT NULL,
 state ENUM('available','held','sold','blocked','house','complimentary','released') NOT NULL DEFAULT 'available',
 order_id BIGINT UNSIGNED NULL, reason VARCHAR(500) NULL,
 PRIMARY KEY(performance_id,seat_key),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_order_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL, seat_key VARCHAR(80) NOT NULL,
 product_type VARCHAR(30) NOT NULL DEFAULT 'ticket', category VARCHAR(60) NOT NULL,
 description VARCHAR(500) NOT NULL, unit_minor INT NOT NULL, seat_json JSON NOT NULL,
 UNIQUE KEY(order_id,seat_key), UNIQUE KEY(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id), CHECK(unit_minor>=0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_payment_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(30) NOT NULL DEFAULT 'simulated',
 provider_reference CHAR(32) NOT NULL UNIQUE,
 scenario ENUM('success','declined','canceled','delayed','timeout') NOT NULL,
 state ENUM('pending','succeeded','failed','canceled','unknown') NOT NULL DEFAULT 'pending',
 ready_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY(order_id), UNIQUE KEY(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id), CHECK(provider='simulated')
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_payment_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 attempt_id BIGINT UNSIGNED NOT NULL, event_key VARCHAR(100) NOT NULL,
 outcome VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY(organization_id,event_key), FOREIGN KEY(attempt_id,organization_id) REFERENCES commerce_payment_attempts(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL, performance_id BIGINT UNSIGNED NOT NULL,
 seat_key VARCHAR(80) NOT NULL, token CHAR(64) NOT NULL UNIQUE,
 state ENUM('valid','refunded','revoked') NOT NULL DEFAULT 'valid', checked_in_at DATETIME NULL,
 checked_in_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY(order_id,seat_key), UNIQUE KEY(id,organization_id),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
 detail_json JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id), FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
-- Published layout documents and performance seat references cannot be rewritten.
DELIMITER $$
CREATE TRIGGER commerce_layout_immutable_update BEFORE UPDATE ON commerce_layouts FOR EACH ROW
BEGIN
 IF OLD.status='published' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published Commerce layouts are immutable'; END IF;
END$$
CREATE TRIGGER commerce_layout_immutable_delete BEFORE DELETE ON commerce_layouts FOR EACH ROW
BEGIN
 IF OLD.status='published' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Published Commerce layouts are immutable'; END IF;
END$$
CREATE TRIGGER commerce_performance_layout_immutable BEFORE UPDATE ON commerce_performances FOR EACH ROW
BEGIN
 IF NEW.layout_id<>OLD.layout_id OR NEW.organization_id<>OLD.organization_id OR NEW.production_id<>OLD.production_id OR NEW.currency<>OLD.currency THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Commerce performance identity is immutable'; END IF;
END$$
DELIMITER ;
