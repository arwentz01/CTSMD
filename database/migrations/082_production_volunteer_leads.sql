CREATE TABLE IF NOT EXISTS production_volunteer_leads (
 production_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 appointed_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (production_id,user_id),
 CONSTRAINT fk_pvl_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
 CONSTRAINT fk_pvl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_pvl_appoint FOREIGN KEY (appointed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
