-- Additive scheduling. Historical local times are NOT guessed/backfilled.
ALTER TABLE commerce_venues ADD COLUMN timezone_name VARCHAR(64) NULL;
ALTER TABLE commerce_performances
 ADD COLUMN starts_at_utc DATETIME NULL,
 ADD COLUMN timezone_name VARCHAR(64) NULL,
 ADD COLUMN utc_offset_minutes SMALLINT NULL,
 ADD COLUMN schedule_status ENUM('draft','scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled';
CREATE TABLE IF NOT EXISTS commerce_showtime_slots (
 organization_id BIGINT UNSIGNED NOT NULL,
 space_id BIGINT UNSIGNED NOT NULL,
 starts_at_utc DATETIME NOT NULL,
 performance_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(organization_id,space_id,starts_at_utc),
 UNIQUE KEY(performance_id),
 FOREIGN KEY(space_id,organization_id) REFERENCES commerce_spaces(id,organization_id),
 FOREIGN KEY(performance_id,organization_id) REFERENCES commerce_performances(id,organization_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_schedule_batches (
 organization_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 request_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 performance_ids JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(organization_id,actor_user_id,request_key),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_allocation_batches (
 organization_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 request_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 result_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(organization_id,actor_user_id,request_key),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
