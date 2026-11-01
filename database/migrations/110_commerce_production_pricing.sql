-- Production defaults are opt-in. Existing performance/order prices are not rewritten.
CREATE TABLE IF NOT EXISTS commerce_production_pricing (
 organization_id BIGINT UNSIGNED NOT NULL, production_id BIGINT UNSIGNED NOT NULL,
 prices_json JSON NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY(organization_id,production_id),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(production_id) REFERENCES productions(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_performance_pricing (
 organization_id BIGINT UNSIGNED NOT NULL, performance_id BIGINT UNSIGNED NOT NULL,
 pricing_mode ENUM('inherit','override') NOT NULL,
 PRIMARY KEY(organization_id,performance_id),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_pricing_batches (
 organization_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 request_key CHAR(32) NOT NULL, request_hash CHAR(64) NOT NULL, result_json JSON NOT NULL,
 PRIMARY KEY(organization_id,actor_user_id,request_key),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id), FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
