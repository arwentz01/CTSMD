-- Opt-in fallback for new scheduling only. No existing prices are backfilled or changed.
CREATE TABLE IF NOT EXISTS commerce_organization_pricing (
 organization_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 prices_json JSON NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id)
) ENGINE=InnoDB;
