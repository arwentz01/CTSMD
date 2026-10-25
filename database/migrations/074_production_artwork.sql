-- One current image per production artwork role. Actual bytes remain in StorageService.
CREATE TABLE IF NOT EXISTS production_artwork (
 production_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL,
 stored_file_id BIGINT UNSIGNED NOT NULL,
 updated_by_user_id BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (production_id,role),
 INDEX idx_artwork_file (stored_file_id),
 CONSTRAINT fk_artwork_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
 CONSTRAINT fk_artwork_stored FOREIGN KEY (stored_file_id) REFERENCES stored_files(id),
 CONSTRAINT fk_artwork_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
